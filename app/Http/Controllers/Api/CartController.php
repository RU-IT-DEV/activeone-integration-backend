<?php

namespace App\Http\Controllers\Api;

use App\Helper\IntellicareHelper;
use App\Helper\ShopifyHelper;
use App\Http\Controllers\Api\BaseController;
use App\Models\ShopifyCheckoutSession;
use Illuminate\Http\Request;

class CartController extends BaseController
{
    public function show(Request $request, ShopifyHelper $shopifyHelper)
    {
        $data = $request->all();

        try {
            $session = ShopifyCheckoutSession::where('token', $request->cartToken)->first();

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
                    $item['node']['merchandise']['product'] = $product;
                }

                return $item;
            }, $lineItems);

            $diagnosis = data_get($lineItems, '*.node.merchandise.product.code', []);

            $cart['data']['cart']['lines']['edges'] = $lineItems;
            $cart['data']['arr_med_diagnosis'] = $diagnosis;

            $customer = [];
            if (empty($cart['data']['cart']['buyerIdentity'])) {
                $customer = $shopifyHelper->getCustomerDetails($session->shopify_customer_id);
            } else {
                $customer = $cart['data']['cart']['buyerIdentity']['customer'];
                if (empty($cart['data']['cart']['buyerIdentity']['customer'])) {
                    $customer = $shopifyHelper->getCustomerDetails($session->shopify_customer_id);
                    
                    $customer = [
                        'id' => $customer['id'],
                        'firstName' => $customer['firstName'],
                        'lastName' => $customer['lastName'],
                        'email' => $customer['email'],
                        'phone' => $customer['phone'],
                        'metafields' => $customer['metafields']['nodes']
                    ];
                }
            }
            
            $cart['data']['cart']['buyerIdentity']['email'] = $customer['email'];
            $cart['data']['cart']['buyerIdentity']['phone'] = $customer['phone'];


            $cart['data']['cart']['buyerIdentity']['customer'] = $customer;

            $arr_personal_email = array_find($customer['metafields'], function ($value) {
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
            
            $response = [
                ...$cart['data']
            ];
            return $this->sendResponse($response, "Success");
        } catch (\Exception $e) {
            if ($e->getCode() === 422) {
                return $this->sendError("Something went wrong. {$e->getMessage()}", [], 422);
            } else {
                return $this->sendError("Something went wrong. {$e->getMessage()}", []);
            }
        }
    }
}
