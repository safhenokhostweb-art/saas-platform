"<?php

namespace App\\Http\\Middleware;

use Closure;
use Illuminate\\Http\\Request;
use Symfony\\Component\\HttpFoundation\\Response;

class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * رعایت قانون ۴: احراز هویت و سطح دسترسی
     * فقط کاربرانی که is_admin=true هستند مجاز به عبور هستند.
     *
     * @param  \\Closure(\\Illuminate\\Http\\Request): (\\Symfony\\Component\\HttpFoundation\\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || !$request->user()->is_admin) {
            return response()->json([
                'success' => false,
                'message' => 'شما مجوز دسترسی به این بخش را ندارید. فقط ادمین‌ها مجاز هستند.',
            ], 403);
        }

        return $next($request);
    }
}"