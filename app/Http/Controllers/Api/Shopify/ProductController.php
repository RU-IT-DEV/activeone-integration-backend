<?php

namespace App\Http\Controllers\Api\Shopify;

use App\Helper\ShopifyHelper;
use App\Http\Controllers\Api\BaseController;
use App\Models\Order;
use App\Models\OrderDetails;
use App\Models\Product;
use App\Services\OrderLogService;
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
                return $field['type'] == "metaobject_reference" || $field['key'] == "medicine_type";
            }));

            $product_code = array_values(array_filter($product['metafields'], function ($field) {
                return $field['key'] == "medicine_code";
            }));

            if (count($product_type) > 0) {
                $str_typeValue = strtolower($product_type[0]['value']);
                if (str_contains($str_typeValue, 'otc')) {
                    $product['category']['name'] = "OTC";
                } else {
                    $product['category']['name'] = empty($str_typeValue) ? 'OTC':strtoupper($str_typeValue);
                }
            }

            if (count($product_code) > 0) {
                $str_codeValue = $product_code[0]['value'];
                $product['icd'] = $str_codeValue;
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

            $product_Rprice = Product::where('sku', $product['sku'])->first();

            if ($product_Rprice) {
                $amountToUse = $product_Rprice->amount;
                if (!$product_Rprice->vatable) {
                    $amountToUse = $product_Rprice->no_vat_amount;
                }
                $item_tax_amount = $product_Rprice->amount - $product_Rprice->no_vat_amount;
                $product['taxable'] = $product_Rprice->vatable === true;
                $product['amount'] = $amountToUse;
                $product['price']['amount'] = $product_Rprice->amount;
                $product['price']['vatable'] = $product_Rprice->vatable;
                $product['price']['tax_amount'] = number_format($item_tax_amount, 2, ".", "");
                $product['price']['no_vat_amount'] = $product_Rprice->no_vat_amount;
            }
    
            return $this->sendResponse($product, "Product retrieved successfully");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), ['errors' => 'Error retrieving product'], 500);
        }
    }
    
    public function store(
        Request $request, 
        Order $order, 
        OrderLogService $orderLogService
    ) {
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

            $logType = "ADD"; $order_item = [];
            $response_msg = "Successfuly added an item";
            $order_totalAmount = $obj_item['amount'] * $obj_item['quantity'];

            $order->totalAmount += $order_totalAmount;
            $order->save();

            $isProduct_orderExist = $order->lineItems()->where('shopify_productId', $obj_item['id'])->first();
            if ($isProduct_orderExist) {
                $isProduct_orderExist->increment('quantity', $obj_item['quantity']);
                $isProduct_orderExist->save();

                $logType = "QTY_UPDATE";
                $order_item = $isProduct_orderExist;
                $response_msg = "Successfuly updated quantity of an item.";
            } else {
                $orderDetail = [
                    'order_id' => $order->id,
                    'shopify_productId' => $obj_item['id'], 
                    'shopify_product_price' => $obj_item['amount'],
                    'image_url' => $image,
                    'quantity' => $obj_item['quantity'], 
                    'sku' => $obj_item['sku'],
                    'code' => $obj_item['sku'], 
                    'icd' => $obj_item['icd'],
                    'icdcode' => $obj_item['icdcode'],
                    'title' => $obj_item['title'], 
                    'type' => $category, 
                    'variantTitle' => $obj_item['selectedOptions'][0]['value'],
                    'unit' => $obj_item['selectedOptions'][0]['name'],
                    'amount' => $obj_item['amount'], 
                    'vat_amount' => $obj_item['price']['vat_amount'], 
                    'no_vat_amount' => $obj_item['price']['no_vat_amount'], 
                    'taxable' => $taxable,
                    'is_prescribed' => true,
                    'reason' => $obj_item['reason']
                ];
                $lineItem = $order->lineItems()->create($orderDetail);
    
                $orderLogService->orderDetails->store($lineItem->id, $lineItem);
                $order_item = $lineItem;
            }

            $orderLogService->update(
                $order->id,
                ["id" => $order->id, "totalAmount" => $order->totalAmount],
                "Order Total Amount has been updated; PHP {$order->totalAmount} ."
            );
            return $this->sendResponse([
                'type' => $logType,
                'totalAmount' => $order->totalAmount,
                'order_item' => $order_item
            ], $response_msg);
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), "Failed to save product.", 400);
        }
    }

    public function update(
        Request $request, 
        Order $order, 
        OrderDetails $orderDetail, 
        OrderLogService $orderLogService
    ) {
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

            $order_totalAmount = $orderDetail->amount * $data['quantity'];

            $order->totalAmount += $order_totalAmount;
            $order->save();

            $additional_summary = "Quantity updated to {$data['quantity']}, Reason updated to {$data['reason']}";
            $orderLogService->orderDetails->update($orderDetail->id, $orderDetail, $additional_summary);

            $orderLogService->update(
                $order->id,
                ["id" => $order->id, "totalAmount" => $order->totalAmount],
                "Order Total Amount has been updated; PHP {$order->totalAmount} ."
            );

            return $this->sendResponse([
                'order_item' => $orderDetail,
                'totalAmount' => $order->totalAmount
            ], "Success updated the product.");
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), [], 400);
        }
    }

    public function remove(
        Request $request, 
        Order $order, 
        OrderDetails $orderDetail, 
        OrderLogService $orderLogService
    ) {
        $reason = $request->reason;
        $orderDetail->reason = $reason;
        $orderDetail->save();
        
        $item_totalAmount = $orderDetail->amount * $orderDetail->quantity;
        $order->totalAmount -= $item_totalAmount;
        
        $orderLogService->orderDetails->delete($orderDetail->id, $orderDetail);
        $orderDetail->delete();
        
        $orderLogService->update(
            $order->id,
            ["id" => $order->id, "totalAmount" => $order->totalAmount],
            "Order Total Amount has been updated; PHP {$order->totalAmount} ."
        );
        return $this->sendResponse([
            "totalAmount" => $order->totalAmount
        ], "Item removed");
    }
}
