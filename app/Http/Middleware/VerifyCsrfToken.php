<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Indicates whether the XSRF-TOKEN cookie should be set on the response.
     *
     * @var bool
     */
    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * You can leave this empty if you're bypassing CSRF globally.
     *
     * @var array
     */
    protected $except = [
        // You can add specific routes to exclude if desired.
    ];

    /**
     * Handle an incoming request.
     *
     * This method bypasses any CSRF token verification by immediately passing the request
     * to the next middleware.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Bypass CSRF token verification entirely
        return $next($request);
    }
}
