<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Setting;

class EnsureProfileIsComplete
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // فقط وقتی هر دو فعال باشند: نمایش فرم + اجبار تکمیل
        $force = Setting::get('force_complete_profile', '1');
        $formEnabled = Setting::get('profile_form_enabled', '1');

        if ($formEnabled !== '1' || $force !== '1') {
            return $next($request);
        }

        // پروفایل ناقص است → هدایت به فرم تکمیل
        if (! $user->profile || ! $user->profile->is_complete) {
            if (! $request->routeIs('profile.complete', 'profile.store')) {
                return redirect()->route('profile.complete');
            }
        }

        return $next($request);
    }
}
