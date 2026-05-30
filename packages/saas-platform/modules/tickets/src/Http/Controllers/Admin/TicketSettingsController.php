<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Ticket Module Settings — Phase 8.3
 *
 * Manages: chat appearance, behavior defaults, SMS config, SMS templates.
 */
class TicketSettingsController extends Controller
{
    private const KEYS = [
        // Chat appearance
        'chat_customer_bg'   => 'tickets.chat_customer_bg',
        'chat_customer_text' => 'tickets.chat_customer_text',
        'chat_staff_bg'      => 'tickets.chat_staff_bg',
        'chat_staff_text'    => 'tickets.chat_staff_text',

        // Behavior
        'auto_close_days'     => 'tickets.auto_close_days',
        'default_priority'    => 'tickets.default_priority',
        'max_message_length'  => 'tickets.max_message_length',

        // SMS toggles
        'sms_enabled'    => 'tickets.sms_enabled',
        'sms_admin_phone'=> 'tickets.sms_admin_phone',
        'sms_on_created' => 'tickets.sms_on_created',
        'sms_on_reply'   => 'tickets.sms_on_reply',
        'sms_on_status'  => 'tickets.sms_on_status',
        'sms_on_assigned'=> 'tickets.sms_on_assigned',

        // SMS templates
        'sms_template_created'        => 'tickets.sms_template_created',
        'sms_template_customer_reply' => 'tickets.sms_template_customer_reply',
        'sms_template_status'         => 'tickets.sms_template_status',
        'sms_template_assigned'       => 'tickets.sms_template_assigned',

        // Attachments
        'attachment_enabled'   => 'tickets.attachment_enabled',
        'attachment_max_size'  => 'tickets.attachment_max_size',
        'attachment_max_files' => 'tickets.attachment_max_files',
        'attachment_allowed'   => 'tickets.attachment_allowed',
    ];

    public function index()
    {
        $settings = [
            'chat_customer_bg'   => $this->setting('tickets.chat_customer_bg',   '#e3f2fd'),
            'chat_customer_text' => $this->setting('tickets.chat_customer_text',  '#1a1a2e'),
            'chat_staff_bg'      => $this->setting('tickets.chat_staff_bg',       '#dcf8c6'),
            'chat_staff_text'    => $this->setting('tickets.chat_staff_text',     '#1a1a2e'),

            'auto_close_days'    => $this->setting('tickets.auto_close_days',    7),
            'default_priority'   => $this->setting('tickets.default_priority',   'normal'),
            'max_message_length' => $this->setting('tickets.max_message_length', 5000),

            'sms_enabled'    => (bool) $this->setting('tickets.sms_enabled',    false),
            'sms_admin_phone'=> $this->setting('tickets.sms_admin_phone',   ''),
            'sms_on_created' => (bool) $this->setting('tickets.sms_on_created', true),
            'sms_on_reply'   => (bool) $this->setting('tickets.sms_on_reply',   true),
            'sms_on_status'  => (bool) $this->setting('tickets.sms_on_status',  false),
            'sms_on_assigned'=> (bool) $this->setting('tickets.sms_on_assigned',false),

            'sms_template_created'        => $this->setting('tickets.sms_template_created',
                'تیکت جدید {ticket_number} با موضوع «{subject}» از طرف {customer} ثبت شد.'),
            'sms_template_customer_reply' => $this->setting('tickets.sms_template_customer_reply',
                'پاسخ جدید از مشتری برای تیکت {ticket_number} — {customer}'),
            'sms_template_status'         => $this->setting('tickets.sms_template_status',
                'وضعیت تیکت {ticket_number} از {from_status} به {to_status} تغییر کرد.'),
            'sms_template_assigned'       => $this->setting('tickets.sms_template_assigned',
                'تیکت {ticket_number} با موضوع «{subject}» به شما تخصیص یافت.'),

            'attachment_enabled'   => (bool) $this->setting('tickets.attachment_enabled', true),
            'attachment_max_size'  => (int) $this->setting('tickets.attachment_max_size',  5120),
            'attachment_max_files' => (int) $this->setting('tickets.attachment_max_files', 5),
            'attachment_allowed'   => $this->setting('tickets.attachment_allowed',
                'jpg,jpeg,png,gif,pdf,doc,docx,zip,txt'),
        ];

        return view('tickets::admin.tickets.settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'chat_customer_bg'   => 'required|string|max:30',
            'chat_customer_text' => 'required|string|max:30',
            'chat_staff_bg'      => 'required|string|max:30',
            'chat_staff_text'    => 'required|string|max:30',

            'auto_close_days'    => 'nullable|integer|min:0|max:365',
            'default_priority'   => 'nullable|in:low,normal,high',
            'max_message_length' => 'nullable|integer|min:100|max:10000',

            'sms_enabled'    => 'nullable|boolean',
            'sms_admin_phone'=> 'nullable|string|max:20',
            'sms_on_created' => 'nullable|boolean',
            'sms_on_reply'   => 'nullable|boolean',
            'sms_on_status'  => 'nullable|boolean',
            'sms_on_assigned'=> 'nullable|boolean',

            'sms_template_created'        => 'nullable|string|max:500',
            'sms_template_customer_reply' => 'nullable|string|max:500',
            'sms_template_status'         => 'nullable|string|max:500',
            'sms_template_assigned'       => 'nullable|string|max:500',

            'attachment_enabled'   => 'nullable|boolean',
            'attachment_max_size'  => 'nullable|integer|min:256|max:102400',
            'attachment_max_files' => 'nullable|integer|min:1|max:20',
            'attachment_allowed'   => 'nullable|string|max:500',
        ]);

        // Checkboxes come as null when unchecked — force boolean
        foreach (['sms_enabled', 'sms_on_created', 'sms_on_reply', 'sms_on_status', 'sms_on_assigned', 'attachment_enabled'] as $boolKey) {
            $data[$boolKey] = $request->boolean($boolKey) ? '1' : '0';
        }

        foreach ($data as $field => $value) {
            if (isset(self::KEYS[$field])) {
                $this->saveSetting(self::KEYS[$field], $value ?? '');
            }
        }

        return redirect()->route('tickets.admin.settings')
            ->with('success', 'تنظیمات ماژول پشتیبانی با موفقیت ذخیره شد.');
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        $model = config('tickets.settings_model');
        return ($model && class_exists($model)) ? $model::get($key, $default) : $default;
    }

    private function saveSetting(string $key, mixed $value): void
    {
        $model = config('tickets.settings_model');
        if ($model && class_exists($model)) {
            $model::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
