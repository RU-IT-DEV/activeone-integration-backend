<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductsController extends BaseController
{
    public function index(Request $request)
    {
        $perPage = max(1, min((int) $request->input('itemsPerPage', 10), 100));
        $sortBy = $request->input('sortBy', []);

        $products = new Product();

        $sortable = [
            'id' => 'id',
            'sku' => 'sku',
            'name' => 'name',
            'amount' => 'amount',
            'no_vat_amount' => 'no_vat_amount',
            'created_at' => 'created_at'
        ];

        if (is_array($sortBy)) {
            foreach ($sortBy as $sort) {
                $key = $sort['key'] ?? null;
                $direction = strtolower($sort['order'] ?? 'asc');

                if (isset($sortable[$key]) && in_array($direction, ['asc', 'desc'], true)) {
                    $products->orderBy($sortable[$key], $direction);
                }
            }
        }

        $products->paginate($perPage);

        return $this->sendResponse($products, "Products retrieved successfully.");
    }
}
