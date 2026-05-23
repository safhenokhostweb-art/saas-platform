<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
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
}
