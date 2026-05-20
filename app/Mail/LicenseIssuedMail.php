<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * ایمیل ارسال کلید لایسنس به مشتری پس از خرید و پرداخت موفق.
 */
class LicenseIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $licenseKey,
        public string $customerName,
        public string $siteTitle,
        public ?string $domain,
        public ?string $expiresAt
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'کلید لایسنس شما — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.license-issued',
        );
    }
}
