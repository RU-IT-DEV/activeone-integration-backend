<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class OrderSearchService
{
    /**
     * Apply global and field-specific filters to the orders query.
     *
     * Global search accepts ?search=...
     * Field filters can be passed as top-level query parameters or under ?filters[...]=...
     */
    public function apply(Builder $query, Request $request): Builder
    {
        $filters = (array) $request->input('filters', []);

        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $like = "%{$search}%";

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

        $fieldFilters = [
            'id' => 'orders.id',
            'customer_name' => 'orders.customer_name',
            'customer_email' => 'orders.customer_email',
            'shopify_order_name' => 'orders.shopify_order_name',
            'activeone_status' => 'orders.activeone_status',
            'intellicare_status' => 'orders.intellicare_status',
            'created_at' => 'orders.created_at',
            'totalAmount' => 'orders.totalAmount',
        ];

        foreach ($fieldFilters as $key => $column) {
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
            $query->whereExists(function ($subquery) use ($validationDate) {
                $subquery->selectRaw('1')
                    ->from('order_logs')
                    ->whereColumn('order_logs.auditable_id', 'orders.id')
                    ->where('order_logs.table', 'orders')
                    ->whereIn('order_logs.action', ['approve', 'reject'])
                    ->whereDate('order_logs.created_at', $validationDate);
            });
        }

        $validatedBy = $filters['validated_by'] ?? $request->input('validated_by');
        if ($validatedBy !== null && $validatedBy !== '') {
            $query->whereExists(function ($subquery) use ($validatedBy) {
                $subquery->selectRaw('1')
                    ->from('order_logs')
                    ->join('users', 'users.id', '=', 'order_logs.auditable_by')
                    ->whereColumn('order_logs.auditable_id', 'orders.id')
                    ->where('order_logs.table', 'orders')
                    ->whereIn('order_logs.action', ['approve', 'reject'])
                    ->where('users.name', 'like', '%' . trim((string) $validatedBy) . '%');
            });
        }

        $amountMin = $filters['totalAmount_min'] ?? $request->input('totalAmount_min');
        if ($amountMin !== null && $amountMin !== '' && is_numeric($amountMin)) {
            $query->where('orders.totalAmount', '>=', $amountMin);
        }

        $amountMax = $filters['totalAmount_max'] ?? $request->input('totalAmount_max');
        if ($amountMax !== null && $amountMax !== '' && is_numeric($amountMax)) {
            $query->where('orders.totalAmount', '<=', $amountMax);
        }

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
            $query->whereExists(function ($subquery) use ($validationFrom) {
                $subquery->selectRaw('1')
                    ->from('order_logs')
                    ->whereColumn('order_logs.auditable_id', 'orders.id')
                    ->where('order_logs.table', 'orders')
                    ->whereIn('order_logs.action', ['approve', 'reject'])
                    ->whereDate('order_logs.created_at', '>=', $validationFrom);
            });
        }

        $validationTo = $filters['validation_to'] ?? $request->input('validation_to');
        if ($validationTo) {
            $query->whereExists(function ($subquery) use ($validationTo) {
                $subquery->selectRaw('1')
                    ->from('order_logs')
                    ->whereColumn('order_logs.auditable_id', 'orders.id')
                    ->where('order_logs.table', 'orders')
                    ->whereIn('order_logs.action', ['approve', 'reject'])
                    ->whereDate('order_logs.created_at', '<=', $validationTo);
            });
        }

        return $query;
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
