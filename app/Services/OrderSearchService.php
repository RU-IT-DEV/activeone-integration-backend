<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OrderSearchService
{
    private const FIELDS = [
        'id' => 'orders.id',
        'customer_name' => 'orders.customer_name',
        'customer_email' => 'orders.customer_email',
        'shopify_order_name' => 'orders.shopify_order_name',
        'activeone_status' => 'orders.activeone_status',
        'intellicare_status' => 'orders.intellicare_status',
        'created_at' => 'orders.created_at',
        'totalAmount' => 'orders.totalAmount',
    ];

    private const LOG_FIELDS = [
        'validation_date',
        'validated_by',
    ];

    /**
     * Apply global search, legacy filters and structured advanced search.
     *
     * advanced_search example:
     * {
     *   "logic": "AND",
     *   "rules": [
     *     {
     *       "field": "customer_name",
     *       "operator": "contains",
     *       "value": "Juan",
     *       "logic": "AND"
     *     }
     *   ]
     * }
     */
    public function apply(Builder $query, Request $request): Builder
    {
        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            $this->applyGlobalSearch($query, $search);
        }

        $this->applyLegacyFilters($query, $request);

        $advancedSearch = $this->parseAdvancedSearch($request->input('advanced_search'));

        if ($advancedSearch && !empty($advancedSearch['rules'])) {
            $this->applyAdvancedSearch($query, $advancedSearch);
        }

        return $query;
    }

    private function applyGlobalSearch(Builder $query, string $search): void
    {
        $like = "%{$search}%";

        $query->where(function (Builder $query) use ($like) {
            $query->where('orders.id', 'like', $like)
                ->orWhere('orders.customer_name', 'like', $like)
                ->orWhere('orders.customer_email', 'like', $like)
                ->orWhere('orders.shopify_order_name', 'like', $like)
                ->orWhere('orders.activeone_status', 'like', $like)
                ->orWhere('orders.intellicare_status', 'like', $like)
                ->orWhere('orders.created_at', 'like', $like)
                ->orWhere('orders.totalAmount', 'like', $like)
                ->orWhereExists(function ($subquery) use ($like) {
                    $subquery->selectRaw('1')
                        ->from('order_logs')
                        ->whereColumn('order_logs.auditable_id', 'orders.id')
                        ->where('order_logs.table', 'orders')
                        ->whereIn('order_logs.action', ['approve', 'reject'])
                        ->where('order_logs.created_at', 'like', $like);
                })
                ->orWhereExists(function ($subquery) use ($like) {
                    $subquery->selectRaw('1')
                        ->from('order_logs')
                        ->join('users', 'users.id', '=', 'order_logs.auditable_by')
                        ->whereColumn('order_logs.auditable_id', 'orders.id')
                        ->where('order_logs.table', 'orders')
                        ->whereIn('order_logs.action', ['approve', 'reject'])
                        ->where('users.name', 'like', $like);
                });
        });
    }

    private function applyAdvancedSearch(Builder $query, array $search): void
    {
        $rules = array_values(array_filter(
            $search['rules'] ?? [],
            fn ($rule) => is_array($rule) && !empty($rule['field']) && isset($rule['value'])
        ));

        if (!$rules) {
            return;
        }

        $defaultLogic = strtoupper($search['logic'] ?? 'AND') === 'OR' ? 'OR' : 'AND';

        $query->where(function (Builder $group) use ($rules, $defaultLogic) {
            foreach ($rules as $index => $rule) {
                $logic = $index === 0
                    ? 'AND'
                    : (strtoupper($rule['logic'] ?? $defaultLogic) === 'OR' ? 'OR' : 'AND');

                $method = $logic === 'OR' ? 'orWhere' : 'where';

                $group->{$method}(function (Builder $condition) use ($rule) {
                    $this->applyRule($condition, $rule);
                });
            }
        });
    }

    private function applyRule(Builder $query, array $rule): void
    {
        $field = $rule['field'];
        $operator = strtolower((string) ($rule['operator'] ?? 'contains'));
        $value = $rule['value'] ?? null;
        $value2 = $rule['value2'] ?? null;

        if ($value === null || $value === '') {
            return;
        }

        if ($field === 'validation_date') {
            $this->applyLogDateRule($query, $operator, $value, $value2);
            return;
        }

        if ($field === 'validated_by') {
            $this->applyValidatedByRule($query, $operator, $value);
            return;
        }

        if (!isset(self::FIELDS[$field])) {
            return;
        }

        $column = self::FIELDS[$field];

        if (in_array($field, ['created_at'], true)) {
            $this->applyDateRule($query, $column, $operator, $value, $value2);
            return;
        }

        if ($field === 'totalAmount' || $field === 'id') {
            $this->applyNumberRule($query, $column, $operator, $value);
            return;
        }

        $this->applyTextRule($query, $column, $operator, $value);
    }

    private function applyTextRule(Builder $query, string $column, string $operator, $value): void
    {
        $value = trim((string) $value);

        match ($operator) {
            'is' => $query->where($column, '=', $value),
            'is_not' => $query->where($column, '!=', $value),
            'starts_with' => $query->where($column, 'like', $value . '%'),
            'not_contains' => $query->where($column, 'not like', '%' . $value . '%'),
            default => $query->where($column, 'like', '%' . $value . '%'),
        };
    }

    private function applyNumberRule(Builder $query, string $column, string $operator, $value): void
    {
        if (!is_numeric($value)) {
            return;
        }

        $comparison = match ($operator) {
            'is_not' => '!=',
            'gt' => '>',
            'gte' => '>=',
            'lt' => '<',
            'lte' => '<=',
            default => '=',
        };

        $query->where($column, $comparison, $value);
    }

    private function applyDateRule(Builder $query, string $column, string $operator, $value, $value2): void
    {
        switch ($operator) {
            case 'before':
                $query->whereDate($column, '<', $value);
                break;
            case 'after':
                $query->whereDate($column, '>', $value);
                break;
            case 'between':
                if ($value2 !== null && $value2 !== '') {
                    $query->whereDate($column, '>=', $value)
                        ->whereDate($column, '<=', $value2);
                }
                break;
            case 'is_not':
                $query->whereDate($column, '!=', $value);
                break;
            default:
                $query->whereDate($column, '=', $value);
                break;
        }
    }

    private function applyLogDateRule(Builder $query, string $operator, $value, $value2): void
    {
        $query->whereExists(function ($subquery) use ($operator, $value, $value2) {
            $subquery->selectRaw('1')
                ->from('order_logs')
                ->whereColumn('order_logs.auditable_id', 'orders.id')
                ->where('order_logs.table', 'orders')
                ->whereIn('order_logs.action', ['approve', 'reject']);

            $this->applyDateRuleToQuery($subquery, 'order_logs.created_at', $operator, $value, $value2);
        });
    }

    private function applyValidatedByRule(Builder $query, string $operator, $value): void
    {
        $query->whereExists(function ($subquery) use ($operator, $value) {
            $subquery->selectRaw('1')
                ->from('order_logs')
                ->join('users', 'users.id', '=', 'order_logs.auditable_by')
                ->whereColumn('order_logs.auditable_id', 'orders.id')
                ->where('order_logs.table', 'orders')
                ->whereIn('order_logs.action', ['approve', 'reject']);

            $this->applyTextRule($subquery, 'users.name', $operator, $value);
        });
    }

    private function applyDateRuleToQuery($query, string $column, string $operator, $value, $value2): void
    {
        switch ($operator) {
            case 'before':
                $query->whereDate($column, '<', $value);
                break;
            case 'after':
                $query->whereDate($column, '>', $value);
                break;
            case 'between':
                if ($value2 !== null && $value2 !== '') {
                    $query->whereDate($column, '>=', $value)
                        ->whereDate($column, '<=', $value2);
                }
                break;
            case 'is_not':
                $query->whereDate($column, '!=', $value);
                break;
            default:
                $query->whereDate($column, '=', $value);
                break;
        }
    }

    private function applyLegacyFilters(Builder $query, Request $request): void
    {
        $filters = (array) $request->input('filters', []);

        foreach (self::FIELDS as $key => $column) {
            $value = $filters[$key] ?? $request->input($key);

            if ($value === null || $value === '') {
                continue;
            }

            if ($key === 'created_at' && $this->isDate($value)) {
                $query->whereDate($column, $value);
                continue;
            }

            $query->where($column, 'like', '%' . trim((string) $value) . '%');
        }

        $validationDate = $filters['validation_date'] ?? $request->input('validation_date');
        if ($validationDate !== null && $validationDate !== '') {
            $this->applyLogDateRule($query, 'is', $validationDate, null);
        }

        $validatedBy = $filters['validated_by'] ?? $request->input('validated_by');
        if ($validatedBy !== null && $validatedBy !== '') {
            $this->applyValidatedByRule($query, 'contains', $validatedBy);
        }

        $this->applyLegacyRange($query, 'totalAmount', 'totalAmount_min', '>=', $filters, $request);
        $this->applyLegacyRange($query, 'totalAmount', 'totalAmount_max', '<=', $filters, $request);

        $createdFrom = $filters['created_from'] ?? $request->input('created_from');
        if ($createdFrom) {
            $query->whereDate('orders.created_at', '>=', $createdFrom);
        }

        $createdTo = $filters['created_to'] ?? $request->input('created_to');
        if ($createdTo) {
            $query->whereDate('orders.created_at', '<=', $createdTo);
        }

        $validationFrom = $filters['validation_from'] ?? $request->input('validation_from');
        if ($validationFrom) {
            $this->applyLogDateRule($query, 'after', $validationFrom, null);
        }

        $validationTo = $filters['validation_to'] ?? $request->input('validation_to');
        if ($validationTo) {
            $this->applyLogDateRule($query, 'before', $validationTo, null);
        }
    }

    private function applyLegacyRange(
        Builder $query,
        string $field,
        string $requestKey,
        string $operator,
        array $filters,
        Request $request
    ): void {
        $value = $filters[$requestKey] ?? $request->input($requestKey);

        if ($value !== null && $value !== '' && is_numeric($value)) {
            $query->where(self::FIELDS[$field], $operator, $value);
        }
    }

    private function parseAdvancedSearch($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function isDate($value): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value;
    }
}
