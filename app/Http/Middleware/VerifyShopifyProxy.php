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
        \Log::info('Shopify Proxy Config', [
            'api_secret_exists' => !empty(config('services.shopify.access_token')),
            'api_secret_length' => strlen(config('services.shopify.access_token') ?? ''),
            'api_secret_hash' => hash('sha256', config('services.shopify.access_token') ?? ''),
        ]);

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
            config('services.shopify.access_token')
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
