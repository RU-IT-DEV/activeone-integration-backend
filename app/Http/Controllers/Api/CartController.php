<?php

namespace App\Http\Controllers\Api;

use App\Helper\IntellicareHelper;
use App\Helper\ShopifyHelper;
use App\Http\Controllers\Api\BaseController;
use App\Models\Product;
use App\Models\ShopifyCheckoutSession;
use Illuminate\Http\Request;

class CartController extends BaseController
{
    public function show(Request $request, ShopifyHelper $shopifyHelper, IntellicareHelper $intellicareHelper)
    {
        $data = $request->all();

        try {
            $session = ShopifyCheckoutSession::where('token', $request->cartToken)->first();

            if (!$session) {
                throw new \Exception("Invalid access.", 500);
            }

            $cart = $shopifyHelper->getCart($session->cart_token);

            $lineItems = data_get($cart, 'data.cart.lines.edges', []);

            $lineItems = array_map(function($item) use ($shopifyHelper) {
                $mitem = $item['node']['merchandise'];
                $product = $mitem['product'];
                if (isset($product['metafields'])) {
                    $productMetafields = $product['metafields'];
                    foreach ($productMetafields as $key => $metafield) {
                        if (!is_null($metafield)) {
                            if ($metafield['type'] == "metaobject_reference") {
                                $referenceValue = $shopifyHelper->getMetaobject($metafield['value']);
                                $str_typeValue = $referenceValue['displayName'];
                                if (str_contains($str_typeValue, 'OTC')) {
                                    $product['category']['name'] = "OTC";
                                } else {
                                    $product['category']['name'] = empty($str_typeValue) ? 'OTC':$str_typeValue;
                                }
                                $metafield['valueId'] = $metafield['value'];
                                $metafield['value'] = $str_typeValue ?? '';
                                // modify this metafield with type metaobject_reference
                                $productMetafields[$key] = $metafield;
                            } else {
                                if ($metafield['key'] == "medicine_code") {
                                    $product['code'] = $metafield['value'];
                                }
                                if ($metafield['key'] == "medicine_type") {
                                    $str_typeValue = strtolower($metafield['value']);
                                    if (str_contains($str_typeValue, 'otc')) {
                                        $product['category']['name'] = "OTC";
                                    } else {
                                        $product['category']['name'] = empty($str_typeValue) ? 'OTC':strtoupper($str_typeValue);
                                    }
                                }
                            }
                        }
                    }
                    $product['metafields'] = $productMetafields;
                    $mitem['product'] = $product;
                }

                $itemNodeCost = $item['node']['cost'];
                $product_Rprice = Product::where('sku', $mitem['sku'])->first();

                if ($product_Rprice) {
                    $amountToUse = $product_Rprice->amount;
                    if (!$product_Rprice->vatable) {
                        $amountToUse = $product_Rprice->no_vat_amount;
                    }
                    $item_tax_amount = $product_Rprice->amount - $product_Rprice->no_vat_amount;
                    $mitem['taxable'] = $product_Rprice->vatable === true;
                    $mitem['price']['amount'] = $product_Rprice->amount;
                    $mitem['price']['vatable'] = $product_Rprice->vatable;
                    $mitem['price']['tax_amount'] = number_format($item_tax_amount, 2, ".", "");
                    $mitem['price']['no_vat_amount'] = $product_Rprice->no_vat_amount;
                    
                    $totalPrice = $amountToUse * $item['node']['quantity'];
                    $noVat_totalPrice = $product_Rprice->no_vat_amount * $item['node']['quantity'];
                    $itemNodeCost['totalAmount']['amount'] = number_format($totalPrice, 2, ".", "");
                    $total_tax_amount = $totalPrice - $noVat_totalPrice;

                    $itemNodeCost = [
                        ...$itemNodeCost,
                        'taxableAmount' => number_format($total_tax_amount, 2, ".", ""),
                        'tax' => [
                            'amount' => number_format($total_tax_amount, 2, ".", ""),
                            'currencyCode' => $product_Rprice->currency
                        ],
                        'deductableToEmployee' => [
                            'amount' => number_format($noVat_totalPrice, 2, ".", "")
                        ]
                    ];
                }
                
                $item['node']['cost'] = $itemNodeCost;
                $item['node']['merchandise'] = $mitem;

                return $item;
            }, $lineItems);

            $diagnosis = data_get($lineItems, '*.node.merchandise.product.code', []);

            $cart['data']['cart']['lines']['edges'] = $lineItems;
            $cart['data']['arr_med_diagnosis'] = $diagnosis;

            if (empty($cart['data']['cart']['buyerIdentity']['customer'])) {
                $customer = $shopifyHelper->getCustomerDetails($session->shopify_customer_id);
                $cart['data']['cart']['buyerIdentity']['email'] = $customer['email'];
                $cart['data']['cart']['buyerIdentity']['phone'] = $customer['phone'];

                $customer = [
                    'id' => $customer['id'],
                    'firstName' => $customer['firstName'],
                    'lastName' => $customer['lastName'],
                    'email' => $customer['email'],
                    'phone' => $customer['phone'],
                    'metafields' => $customer['metafields']['nodes']
                ];

                $arr_personal_email = array_find($customer['metafields']['nodes'], function ($value) {
                    if ($value['key'] == "personal_email_address") {
                        return $value;
                    } else {
                        return false;
                    }
                });

                if (!empty($arr_personal_email)) {
                    $customer['personal_email'] = $arr_personal_email['value'];
                }

                $cart['data']['cart']['buyerIdentity']['customer'] = $customer;
            }
            
            $response = [
                ...$cart['data']
            ];
            return $this->sendResponse($response, "Success");
        } catch (\Exception $e) {
            if ($e->getCode() === 422) {
                return $this->sendError("Something went wrong. {$e->getMessage()}", [], 422);
            } else {
                return $this->sendError("Something went wrong. {$e->getMessage()}", [], $e->getCode());
            }
        }
    }
}
