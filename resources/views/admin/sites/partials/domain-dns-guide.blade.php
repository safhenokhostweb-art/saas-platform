{{-- DNS setup guide for a customer's own domain. Also rendered client-side in onboarding step 5. --}}
<section class="card" aria-labelledby="dom-guide" data-testid="dns-guide">
    <div class="card-body space-y-3">
        <h2 id="dom-guide" class="text-base font-bold text-gray-900 mb-0">راهنمای تنظیم DNS دامنهٔ <span class="font-mono" dir="ltr">{{ $domain }}</span></h2>
        @if($serverIp)
            <p class="text-sm text-gray-600 mb-0">در پنل DNS دامنه این رکوردها را بسازید (TTL پیشنهادی: ۳۶۰۰ یا کمتر):</p>
            <div class="overflow-x-auto">
                <table class="table table-sm text-sm mb-0">
                    <thead><tr><th>نوع</th><th>نام (Host)</th><th>مقدار (Value)</th></tr></thead>
                    <tbody>
                        <tr><td class="font-mono">A</td><td class="font-mono" dir="ltr">@</td><td class="font-mono" dir="ltr">{{ $serverIp }}</td></tr>
                        @if($withWww)
                            <tr><td class="font-mono">A</td><td class="font-mono" dir="ltr">www</td><td class="font-mono" dir="ltr">{{ $serverIp }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        @else
            <div class="alert alert-warning text-sm mb-0">IP عمومی سرور این سایت مشخص نیست؛ در تنظیمات سرور، آدرس (host) را IP عمومی آن قرار دهید.</div>
        @endif
        <ul class="text-sm text-gray-600 leading-7 list-disc pr-5 mb-0">
            <li><strong>دامنه‌های ‎.ir‎ (ایرنیک):</strong> ایرنیک فقط NS (سرورهای نام) دامنه را نگه می‌دارد. رکوردهای A را در سرویسی بسازید که NSهای ثبت‌شده در پنل nic.ir به آن تعلق دارند (مثلاً DNS ابرآروان یا هاست شما). تغییر NS در ایرنیک ممکن است تا ۲۴ ساعت طول بکشد.</li>
            <li><strong>دامنه‌های بین‌المللی (‎.com / .net / .org‎):</strong> رکوردها را در بخش DNS Management ثبت‌کننده (Registrar) یا سرویس DNSی که NS دامنه به آن اشاره می‌کند بسازید.</li>
            <li>اگر دامنه رکورد <span class="font-mono">AAAA</span> (IPv6) دارد که متعلق به این سرور نیست، آن را حذف کنید؛ وگرنه گواهی SSL صادر نمی‌شود.</li>
            <li>اگر دامنه را پشت CDN (مثلاً ابر نارنجی آروان) قرار می‌دهید، مبدأ (Origin) را همین IP بگذارید و تا صدور SSL، «ریدایرکت HTTPS» CDN را خاموش نگه دارید.</li>
        </ul>
    </div>
</section>
