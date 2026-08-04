<?php

namespace App\Http\Controllers\Api;

use App\Helper\IntellicareHelper;
use App\Helper\ShopifyHelper;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;

class CartController extends BaseController
{
    public function show(Request $request, ShopifyHelper $shopifyHelper, IntellicareHelper $intellicareHelper)
    {
        $data = $request->all();

        try {
            $cart = $shopifyHelper->getCart($request->cartToken);

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
                                    $product['category']['name'] = empty($str_typeValue) ?? 'OTC';
                                }
                                $metafield['valueId'] = $metafield['value'];
                                $metafield['value'] = $str_typeValue ?? '';
                                // modify this metafield with type metaobject_reference
                                $productMetafields[$key] = $metafield;
                            } else {
                                if ($metafield['key'] == "medicine_code") {
                                    $product['code'] = $metafield['value'];
                                }
                            }
                        }
                    }
                    $product['metafields'] = $productMetafields;
                    $item['node']['merchandise']['product'] = $product;
                }

                return $item;
            }, $lineItems);

            $cart['data']['cart']['lines']['edges'] = $lineItems;
            $response = [
                ...$cart['data'],
                'botika_token' => $intellicareHelper->access_key
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
