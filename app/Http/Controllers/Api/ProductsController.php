<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductsController extends BaseController
{
    /**
     * Return a searchable, paginated product catalog for the admin UI.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'itemsPerPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sortBy' => ['nullable', 'array'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? $validated['itemsPerPage'] ?? 10);
        $query = Product::query();

        if (!empty($validated['search'])) {
            $term = trim($validated['search']);
            $query->where(function ($builder) use ($term) {
                $builder->where('sku', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('netsuite_item_id', 'like', "%{$term}%");
            });
        }

        $sortable = [
            'id' => 'id',
            'sku' => 'sku',
            'name' => 'name',
            'unit_price' => 'amount',
            'amount' => 'amount',
            'no_vat_amount' => 'no_vat_amount',
            'vat_percentage' => 'vat_percentage',
            'created_at' => 'created_at',
        ];

        foreach (($validated['sortBy'] ?? []) as $sort) {
            $key = $sort['key'] ?? null;
            $direction = strtolower($sort['order'] ?? 'asc');
            if (isset($sortable[$key]) && in_array($direction, ['asc', 'desc'], true)) {
                $query->orderBy($sortable[$key], $direction);
            }
        }

        $paginator = $query->orderBy('name')->paginate($perPage)->withQueryString();
        $paginator->getCollection()->transform(fn (Product $product) => $this->serializeProduct($product));

        return $this->sendResponse($paginator, 'Products retrieved successfully.');
    }

    public function store(Request $request)
    {
        $data = $this->validatedProduct($request);
        $product = DB::transaction(function () use ($data) {
            return Product::create($this->toDatabaseAttributes($data));
        });

        return $this->sendResponse($this->serializeProduct($product), 'Product created successfully.');
    }

    public function show(Product $product)
    {
        return $this->sendResponse($this->serializeProduct($product), 'Product retrieved successfully.');
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validatedProduct($request, $product);
        DB::transaction(function () use ($product, $data) {
            $product->update($this->toDatabaseAttributes($data));
        });

        return $this->sendResponse($this->serializeProduct($product->refresh()), 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        // Products referenced by orders should generally be deactivated instead.
        $product->delete();

        return $this->sendResponse(['id' => $product->id], 'Product deleted successfully.');
    }

    private function validatedProduct(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($product?->id)],
            'netsuite_item_id' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'vat_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'no_vat_amount' => ['nullable', 'numeric', 'min:0'],
            'vatable' => ['required', 'boolean'],
            'currency' => ['required', 'string', 'size:3', Rule::in(['PHP', 'USD'])],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function toDatabaseAttributes(array $data): array
    {
        $unitPrice = round((float) $data['unit_price'], 2);
        $vatPercentage = round((float) $data['vat_percentage'], 4);
        $vatable = filter_var($data['vatable'], FILTER_VALIDATE_BOOLEAN);
        // Calculate server-side so a client cannot submit a mismatched net amount.
        $noVatAmount = $vatable
            ? round($unitPrice / (1 + ($vatPercentage / 100)), 2)
            : $unitPrice;

        return [
            'sku' => $data['sku'],
            'netsuite_item_id' => $data['netsuite_item_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'amount' => $unitPrice,
            'vat_percentage' => $vatPercentage,
            'no_vat_amount' => $noVatAmount,
            'vatable' => in_array($vatable, ["true", "1"]),
            'currency' => $data['currency'],
            'status' => in_array($data['is_active'], ["true","1"]),
        ];
    }

    private function serializeProduct(Product $product): array
    {
        $data = $product->toArray();
        $data['unit_price'] = $product->amount === null ? null : (float) $product->amount;
        $data['vat_percentage'] = $product->vat_percentage === null ? 12 : (float) $product->vat_percentage;
        $data['no_vat_amount'] = $product->no_vat_amount === null ? null : (float) $product->no_vat_amount;
        $data['vatable'] = (bool) $product->vatable;
        $data['is_active'] = in_array(strtolower((string) $product->status), ['active', '1', 'true'], true);
        return $data;
    }
}
