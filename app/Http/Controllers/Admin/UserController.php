<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['profile', 'customer'])->orderByDesc('created_at');

        if ($request->filled('mobile')) {
            $query->where('mobile', 'like', '%' . $request->mobile . '%');
        }
        if ($request->filled('status') && Schema::hasColumn('users', 'is_active')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            }
            if ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $users = $query->paginate(15)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function toggleActive(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'نمی‌توانید اکانت خودتان را غیرفعال کنید.');
        }

        if (!Schema::hasColumn('users', 'is_active')) {
            return back()->with('error', 'ستون is_active در جدول users وجود ندارد. php artisan migrate را اجرا کنید.');
        }

        $user->update(['is_active' => !$user->is_active]);
        $message = $user->is_active ? 'کاربر فعال شد.' : 'کاربر غیرفعال شد و امکان ورود ندارد.';
        ActivityLogger::log('user.toggled', $message . ' (موبایل: ' . $user->mobile . ')', $user);

        return back()->with('success', $message);
    }
}
