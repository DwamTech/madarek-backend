<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureVisitorCookie
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->hasCookie('visitor_id')) {
            $visitorId = (string) Str::uuid();
            $cookie = Cookie::make('visitor_id', $visitorId, 60 * 24 * 30); // 30 days
            $response->headers->setCookie($cookie);
        }

        return $response;
    }
}
