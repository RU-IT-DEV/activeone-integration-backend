<?php

namespace App\Http\Controllers\Api\Shopify;

use App\Helper\ShopifyHelper;
use App\Http\Controllers\Api\BaseController;
use App\Models\Order;
use App\Models\OrderDetails;
use Exception;
use Illuminate\Http\Request;

class ProductController extends BaseController
{
    public function show(Request $request, Order $order, ShopifyHelper $shopifyHelper) {
        $productId = $request->input('code');
        
        try {
            $product = $shopifyHelper->getProductBySku($productId);
    
            if (!$product) {
                throw new Exception('Product not found', 404);
            }
    
            $nodes = data_get($product, 'metafields.nodes', []);
            $nodes = array_map(function($node) use ($shopifyHelper) {
                if ($node['type'] == "metaobject_reference") {
                    $referenceValue = $shopifyHelper->getMetaobject($node['value']);
                    $str_typeValue = $referenceValue['displayName'];
                    $node['valueId'] = $node['value'];
                    $node['value'] = strtoupper($str_typeValue) ?? '';
                }
    
                return $node;
            }, $nodes);

            $product['metafields'] = $nodes;
            $product_type = array_values(array_filter($product['metafields'], function ($field) {
                return $field['type'] == "metaobject_reference";
            }));

            if (count($product_type) > 0) {
                $str_typeValue = $product_type[0]['value'];
                if (str_contains($str_typeValue, 'OTC')) {
                    $product['category']['name'] = "OTC";
                } else {
                    $product['category']['name'] = empty($str_typeValue) ? 'OTC':$str_typeValue;
                }
            }

            $variants = data_get($product, 'variants.nodes', []);
            if (count($variants) >= 1) {
                unset($product['variants']);
                $temp_arr = [
                    'variant' => $variants[0]['title'],
                    'variant_displayName' => $variants[0]['displayName'],
                    'sku' => $variants[0]['sku'],
                    'taxable' => $variants[0]['taxable'],
                    'amount' => $variants[0]['price'],
                    'selectedOptions' => $variants[0]['selectedOptions'],
                ];
                $product = array_merge($product, $temp_arr);
            }

            $dbProduct = $order->lineItems()->where('sku', $productId)->first();
            if ($dbProduct) {
                $product['quantity'] = $dbProduct->quantity;
                $product['reason'] = $dbProduct->reason;
                $product['dbExistId'] = $dbProduct->id;
                $product['dbExist'] = true;
            }
    
            return $this->sendResponse($product, "Product retrieved successfully");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), ['errors' => 'Error retrieving product'], 500);
        }
    }
    
    public function store(Request $request, Order $order)
    {
        $this->validate($request, [
            'id' => 'required',
            'title' => 'required|string',
            'sku' => 'required|string',
            'variant' => 'required|string',
            'category.name' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|min:4'
        ]);

        try {
            $obj_item = $request->all();
            $image = null; 
            $category = null;
            $taxable = filter_var(
                $obj_item['taxable'],
                FILTER_VALIDATE_BOOLEAN
            );

            if (isset($obj_item['featuredImage'])) {
                if (!is_null($obj_item['featuredImage'])) {
                    $image = $obj_item['featuredImage']['url'];
                }
            }

            if (array_key_exists('category', $obj_item)) {
                $category = $obj_item['category']['name'];
            }

            $isProduct_orderExist = $order->lineItems()->where('shopify_productId', $obj_item['id'])->first();
            if ($isProduct_orderExist) {
                $isProduct_orderExist->increment('quantity', $obj_item['quantity']);
                $isProduct_orderExist->save();

                return $this->sendResponse([
                    'type' => 'QTY_UPDATE',
                    'order_item' => $isProduct_orderExist
                ], "Successfuly updated quantity of an item");
            }

            $orderDetail = [
                'order_id' => $order->id,
                'shopify_productId' => $obj_item['id'], 
                'shopify_product_price' => $obj_item['amount'],
                'image_url' => $image,
                'quantity' => $obj_item['quantity'], 
                'sku' => $obj_item['sku'],
                'code' => $obj_item['sku'], 
                'title' => $obj_item['title'], 
                'type' => $category, 
                'variantTitle' => $obj_item['selectedOptions'][0]['value'],
                'unit' => $obj_item['selectedOptions'][0]['name'],
                'amount' => $obj_item['amount'], 
                'vat_amount' => $obj_item['amount'], 
                'no_vat_amount' => $obj_item['amount'], 
                'taxable' => $taxable,
                'is_prescribed' => true,
                'reason' => $obj_item['reason']
            ];
            $order->lineItems()->create($orderDetail);

            return $this->sendResponse([
                'type' => 'ADD',
                'order_item' => $orderDetail
            ], "Successfuly added an item");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), "Failed to save product.", 400);
        }
    }

    public function update(Request $request, Order $order, OrderDetails $orderDetail)
    {
        $this->validate($request, [
            'id' => 'required',
            'dbExistId' => 'required|exists:order_details,id',
            'title' => 'required|string',
            'sku' => 'required|string',
            'variant' => 'required|string',
            'category.name' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|min:4'
        ]);

        try {
            $data = $request->all();
            $orderDetail->update([
                'quantity' => $data['quantity'],
                'reason' => $data['reason']
            ]);

            return $this->sendResponse($orderDetail, "Success updated the product.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    public function remove(Request $request, Order $order, OrderDetails $orderDetail)
    {
        $orderDetail->delete();

        return $this->sendResponse([], "Item removed");
    }
}
