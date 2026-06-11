<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSiteAgent;
use App\Models\Site;
use App\Services\ActivityLogger;
use App\Services\PlatformSiteAgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlatformSiteAgentController extends Controller
{
    public function __construct(private PlatformSiteAgentService $agentService) {}

    /**
     * نمایش وضعیت agent برای یک سایت (و فرم provisioning).
     */
    public function show(Site $site)
    {
        $agent = PlatformSiteAgent::where('site_id', $site->id)
            ->with('provisionedBy')
            ->latest()
            ->first();

        $connectionOk = null;
        if ($site->db_host && $site->db_name) {
            $connectionOk = $this->agentService->testConnection($site);
        }

        return view('admin.sites.agents.show', compact('site', 'agent', 'connectionOk'));
    }

    /**
     * ایجاد یا بازنشانی agent.
     */
    public function provision(Request $request, Site $site)
    {
        $request->validate([
            'mobile' => ['required', 'string', 'regex:/^09[0-9]{9}$/'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex'    => 'فرمت موبایل صحیح نیست (مثال: 09123456789).',
        ]);

        try {
            $result = $this->agentService->provision(
                $site,
                $request->input('mobile'),
                Auth::user(),
                $request->input('notes')
            );

            ActivityLogger::log(
                'site_agent.provisioned',
                ($result['is_new'] ? 'ایجاد' : 'بازنشانی') . ' ادمین پلتفرم برای سایت: ' . $site->title,
                $site,
                ['mobile' => $request->input('mobile'), 'agent_id' => $result['agent']->id]
            );

            return redirect()
                ->route('admin.sites.agents.show', $site)
                ->with('agent_password', $result['password'])
                ->with('success', $result['is_new']
                    ? 'ادمین پلتفرم با موفقیت در سایت مشتری ایجاد شد.'
                    : 'رمز عبور ادمین پلتفرم بازنشانی شد.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'خطا در اتصال به دیتابیس سایت: ' . $e->getMessage()]);
        }
    }

    /**
     * نمایش رمز عبور ذخیره‌شده (یک‌بار برای هر درخواست).
     */
    public function revealPassword(Site $site)
    {
        $agent = PlatformSiteAgent::where('site_id', $site->id)
            ->where('is_active', true)
            ->firstOrFail();

        $password = $this->agentService->decryptPassword($agent);

        ActivityLogger::log(
            'site_agent.password_viewed',
            'مشاهده رمز عبور ادمین پلتفرم برای سایت: ' . $site->title,
            $site,
            ['agent_id' => $agent->id]
        );

        return response()->json(['password' => $password]);
    }

    /**
     * ثبت دسترسی دستی (وقتی ادمین پلتفرم می‌خواهد ورود به سایت را لاگ کند).
     */
    public function logAccess(Site $site)
    {
        $agent = PlatformSiteAgent::where('site_id', $site->id)
            ->where('is_active', true)
            ->firstOrFail();

        $this->agentService->recordAccess($agent, $site);

        ActivityLogger::log(
            'site_agent.access_logged',
            'ثبت دسترسی به سایت مشتری: ' . $site->title,
            $site,
            ['agent_id' => $agent->id]
        );

        return back()->with('success', 'دسترسی با موفقیت ثبت شد.');
    }

    /**
     * لغو دسترسی agent.
     */
    public function revoke(Site $site)
    {
        $agent = PlatformSiteAgent::where('site_id', $site->id)
            ->where('is_active', true)
            ->firstOrFail();

        try {
            $this->agentService->revoke($agent);

            ActivityLogger::log(
                'site_agent.revoked',
                'لغو دسترسی ادمین پلتفرم از سایت: ' . $site->title,
                $site,
                ['agent_id' => $agent->id, 'mobile' => $agent->mobile]
            );

            return redirect()
                ->route('admin.sites.agents.show', $site)
                ->with('success', 'دسترسی ادمین پلتفرم لغو شد.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'خطا در لغو دسترسی: ' . $e->getMessage()]);
        }
    }
}
