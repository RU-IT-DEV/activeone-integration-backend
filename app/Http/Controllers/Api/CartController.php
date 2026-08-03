<?php

namespace App\Http\Controllers\Api;

use App\Helper\ShopifyHelper;
use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;

class CartController extends BaseController
{
    public function show(Request $request, ShopifyHelper $shopifyHelper)
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
                                $metafield['valueId'] = $metafield['value'];
                                $metafield['value'] = $referenceValue['displayName'] ?? '';
                                $productMetafields[$key] = $metafield;
                            }
                        }
                    }
                    $item['node']['merchandise']['product']['metafields'] = $productMetafields;
                }

                return $item;
            }, $lineItems);

            $cart['data']['cart']['lines']['edges'] = $lineItems;
            return $this->sendResponse($cart['data'], "Success");
        } catch (\Exception $e) {
            if ($e->getCode() === 422) {
                return $this->sendError("Something went wrong. {$e->getMessage()}", [], 422);
            } else {
                return $this->sendError("Something went wrong. {$e->getMessage()}", []);
            }
        }
    }
}
