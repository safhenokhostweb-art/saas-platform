<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\Jalali;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('activity_logs')) {
            return view('admin.activity-log.index', [
                'logs' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25),
                'users' => collect(),
                'actionOptions' => [],
            ])->with('error', 'جدول activity_logs وجود ندارد. php artisan migrate را اجرا کنید.');
        }

        $query = ActivityLog::with('user')->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('from')) {
            $from = Jalali::toCarbon($request->from);
            if ($from) {
                $query->whereDate('created_at', '>=', $from->format('Y-m-d'));
            }
        }
        if ($request->filled('to')) {
            $to = Jalali::toCarbon($request->to);
            if ($to) {
                $query->whereDate('created_at', '<=', $to->format('Y-m-d'));
            }
        }

        $logs = $query->paginate(25)->withQueryString();
        $users = User::orderBy('mobile')->get(['id', 'mobile']);

        $actionOptions = [
            'auth.login' => 'ورود به سیستم',
            'auth.logout' => 'خروج از سیستم',
            'customer.created' => 'ایجاد مشتری',
            'customer.updated' => 'ویرایش مشتری',
            'customer.deleted' => 'حذف مشتری',
            'site.created' => 'ایجاد سایت',
            'site.updated' => 'ویرایش سایت',
            'site.deleted' => 'حذف سایت',
            'form.created' => 'ایجاد فرم',
            'form.updated' => 'ویرایش فرم',
            'form.deleted' => 'حذف فرم',
            'user.toggled' => 'تغییر وضعیت کاربر',
            'module.updated' => 'ویرایش ماژول',
            'module.bulk_updated' => 'بروزرسانی گروهی ماژول‌ها',
            'entitlement_granted' => 'اعطای entitlement',
            'entitlement_revoked' => 'لغو entitlement',
            'module.activated' => 'فعال‌سازی ماژول پس از پرداخت (قدیمی)',
        ];

        return view('admin.activity-log.index', compact('logs', 'users', 'actionOptions'));
    }
}
