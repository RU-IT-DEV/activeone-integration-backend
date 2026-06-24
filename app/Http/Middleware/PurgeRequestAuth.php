<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PurgeRequestAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if the request method is PURGE
        if ($request->method() !== 'PURGE') {
            return response()->json(['message' => 'Method Not Allowed'], 405);
        }

        // Get the API token from the request header
        $apiToken = $request->header('X-Purge-Token');

        // Check if the token is provided
        if (!$apiToken) {
            return response()->json(['message' => 'Unauthorized: API token is missing'], 401);
        }

        // Check if the token is valid (compare with a stored token)
        if ($apiToken !== config('app.purge_api_token')) {
            return response()->json(['message' => 'Unauthorized: Invalid API token'], 401);
        }

        return $next($request);
    }
}