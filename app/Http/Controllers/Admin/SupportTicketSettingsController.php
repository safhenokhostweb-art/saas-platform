<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SupportTicketSettingsController extends Controller
{
    /**
     * Show the support ticket chat settings page.
     */
    public function index()
    {
        $settings = [
            'chat_customer_bg'   => Setting::get('support_chat_customer_bg', '#e3f2fd'),
            'chat_customer_text'  => Setting::get('support_chat_customer_text', '#1a1a2e'),
            'chat_staff_bg'       => Setting::get('support_chat_staff_bg', '#dcf8c6'),
            'chat_staff_text'     => Setting::get('support_chat_staff_text', '#1a1a2e'),
            'auto_close_days'     => Setting::get('support_auto_close_days', '30'),
            'default_priority'    => Setting::get('support_default_priority', 'normal'),
            'max_message_length'  => Setting::get('support_max_message_length', '5000'),
        ];

        return view('admin.support-tickets.settings', compact('settings'));
    }

    /**
     * Update the support ticket settings.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'chat_customer_bg'  => 'required|string|max:20',
            'chat_customer_text' => 'required|string|max:20',
            'chat_staff_bg'     => 'required|string|max:20',
            'chat_staff_text'   => 'required|string|max:20',
            'auto_close_days'   => 'nullable|integer|min:0|max:365',
            'default_priority'  => 'nullable|in:low,normal,high',
            'max_message_length'=> 'nullable|integer|min:100|max:10000',
        ]);

        $map = [
            'chat_customer_bg'  => 'support_chat_customer_bg',
            'chat_customer_text' => 'support_chat_customer_text',
            'chat_staff_bg'     => 'support_chat_staff_bg',
            'chat_staff_text'   => 'support_chat_staff_text',
            'auto_close_days'   => 'support_auto_close_days',
            'default_priority'  => 'support_default_priority',
            'max_message_length'=> 'support_max_message_length',
        ];

        foreach ($data as $key => $value) {
            if (isset($map[$key])) {
                Setting::updateOrCreate(
                    ['key' => $map[$key]],
                    ['value' => $value]
                );
            }
        }

        return redirect()->route('admin.support-tickets.settings')
            ->with('success', 'تنظیمات چت پشتیبانی با موفقیت به‌روزرسانی شد.');
    }
}
