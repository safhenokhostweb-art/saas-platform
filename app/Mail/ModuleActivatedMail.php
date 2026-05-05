<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * ایمیل اطلاع‌رسانی به tenant پس از فعال‌سازی ماژول (خرید و پرداخت موفق).
 */
class ModuleActivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $siteTitle,
        public string $moduleTitle,
        public ?string $domain
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'ماژول «' . $this->moduleTitle . '» برای سایت شما فعال شد — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.module-activated',
        );
    }
}
