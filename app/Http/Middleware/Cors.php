<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        $headers = [
            'Access-Control-Allow-Origin' => '*', // replace '*' with specific origin when using credentials
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, Accept, X-CSRF-TOKEN',
            'Access-Control-Allow-Credentials' => 'true',
        ];

        // Handle preflight / OPTIONS early so Laravel route matching does not block it
        if ($request->getMethod() === 'OPTIONS') {
            return response()->json(null, 200, $headers);
        }

        $response = $next($request);

        // Ensure headers are set on any response type
        if ($response instanceof BinaryFileResponse || $response instanceof SymfonyResponse) {
            foreach ($headers as $key => $value) {
                $response->headers->set($key, $value);
            }
        } else {
            // For any other response-like objects that implement header()
            foreach ($headers as $key => $value) {
                $response->header($key, $value);
            }
        }

        return $response;
    }
}
