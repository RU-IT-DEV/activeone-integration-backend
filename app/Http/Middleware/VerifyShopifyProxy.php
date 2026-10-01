<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyShopifyProxy
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $params = $request->query();

        $signature = $params['signature'] ?? null;

        if (!$signature) {
            abort(401, 'Missing Shopify proxy signature.');
        }

        unset($params['signature']);

        ksort($params);

        $message = collect($params)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode('');

        $calculated = hash_hmac(
            'sha256',
            $message,
            config('services.shopify.api_secret')
        );

        \Log::info('Shopify Proxy Signature', [
            'params' => $params,
            'message' => $message,
            'received' => $signature,
            'calculated' => $calculated,
        ]);
        
        if (!hash_equals($calculated, $signature)) {
            abort(401, 'Invalid Shopify proxy signature.');
        }

        return $next($request);
    }
}
