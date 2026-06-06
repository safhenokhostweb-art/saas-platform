{{--
 | Admin Ticket Notifier — polls for new customer messages/tickets.
 | Included in the admin layout to provide global notification banner.
 | Works on all admin pages (not just the ticket list).
--}}
@php
use SaaSPlatform\Modules\Tickets\Models\Ticket as _NTkt;
use SaaSPlatform\Modules\Tickets\Models\TicketReply as _NRpl;

$_ntLastId = _NTkt::withoutTenantIsolation()->max('id') ?? 0;
@endphp

<div id="atnotif-banner"
     style="display:none;position:fixed;top:0;right:0;left:0;z-index:9200;padding:.65rem 1.25rem;background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 100%);color:#fff;box-shadow:0 4px 24px rgba(99,102,241,.45);animation:atnBannerSlide .35s ease"
     role="alert">
    <div style="max-width:1200px;margin:0 auto;display:flex;align-items:center;gap:.875rem">
        <svg style="width:1.1rem;height:1.1rem;flex-shrink:0;animation:bell .8s ease infinite alternate" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <span id="atnotif-text" style="flex:1;font-size:.83rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span>
        <a id="atnotif-link" href="#" style="padding:.25rem .85rem;background:rgba(255,255,255,.9);color:#4f46e5;border-radius:.4rem;font-size:.75rem;font-weight:700;text-decoration:none;white-space:nowrap;flex-shrink:0">مشاهده</a>
        <button onclick="document.getElementById('atnotif-banner').style.display='none'"
                style="display:flex;align-items:center;justify-content:center;width:1.6rem;height:1.6rem;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.3);border-radius:50%;color:#fff;font-size:1.05rem;cursor:pointer;padding:0;flex-shrink:0"
                aria-label="بستن">×</button>
    </div>
</div>
<style>
@keyframes atnBannerSlide{from{transform:translateY(-100%);opacity:0}to{transform:translateY(0);opacity:1}}
@keyframes bell{from{transform:rotate(-10deg)}to{transform:rotate(10deg)}}
</style>
<script>
(function(){
    var lastId               = {{ (int) $_ntLastId }};
    var banner               = document.getElementById('atnotif-banner');
    var btnText              = document.getElementById('atnotif-text');
    var btnLink              = document.getElementById('atnotif-link');
    var dimT                 = null;

    // ─── Guard: آخرین تعداد "منتظر پاسخ" که دیده شد ────────────────────────
    // مقدار اولیه -1 یعنی هنوز اولین بررسی انجام نشده.
    // بعد از اولین بررسی، مقدار واقعی ذخیره می‌شود اما بنر نشان داده نمی‌شود.
    // بنر فقط وقتی ظاهر می‌شود که count نسبت به آخرین مقدار *افزایش* یافته باشد.
    var lastSeenAwaitingCount = -1;
    var isFirstCheck          = true;

    // بنر "منتظر پاسخ" را کاربر بسته — تا افزایش بعدی نشان نده
    document.querySelector('[onclick*="atnotif-banner"]') &&
        document.querySelector('[onclick*="atnotif-banner"]').addEventListener('click', function(){
            lastSeenAwaitingCount = Math.max(lastSeenAwaitingCount, 0);
        });

    function show(text, url) {
        if (!banner || !btnText || !btnLink) return;
        btnText.textContent = text;
        btnLink.href = url || '{{ route("tickets.admin.index") }}';
        banner.style.display = 'block';
        banner.style.animation = 'none';
        void banner.offsetWidth;
        banner.style.animation = 'atnBannerSlide .35s ease';
        clearTimeout(dimT);
        dimT = setTimeout(function(){ if (banner) banner.style.display = 'none'; }, 9000);
        if (window.CS) window.CS.play('new_ticket');
        if (!document.hasFocus()) {
            var orig = document.title.replace(/^\(\d+\)\s*/, '');
            document.title = '🔔 ' + orig;
            window.addEventListener('focus', function(){ document.title = orig; }, {once:true});
        }
        if ('Notification' in window && Notification.permission === 'granted') {
            try { new Notification('تیکت — پیام جدید', {body: text, icon: '/favicon.ico', dir: 'rtl', tag: 'admin-ticket'}); } catch(e) {}
        }
    }

    function check() {
        fetch('{{ route("tickets.admin.check.new") }}?last_id=' + lastId, {
            headers: {'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json',
                      'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') || {}).content || ''}
        })
        .then(function(r){ return r.ok ? r.json() : null; })
        .then(function(data) {
            if (!data) return;

            // ─── تیکت‌های کاملاً جدید (ID بالاتر) ─────────────────────────
            if (data.last_id > lastId) lastId = data.last_id;
            if (data.new_count > 0) {
                var t    = data.tickets[0];
                var text = 'تیکت جدید: ' + (t.subject || '') + (t.customer_name ? ' — ' + t.customer_name : '');
                if (data.new_count > 1) text += ' (+' + (data.new_count - 1) + ' مورد دیگر)';
                show(text, t.url);
            }

            // ─── پیام‌های مشتری منتظر پاسخ ──────────────────────────────────
            // قانون: بنر فقط اگر تعداد نسبت به آخرین مقدار دیده‌شده افزایش یابد.
            var nowAwaiting = data.awaiting_reply || 0;

            if (isFirstCheck) {
                // اولین بررسی: فقط baseline را ثبت کن، بنر نشان نده
                lastSeenAwaitingCount = nowAwaiting;
                isFirstCheck          = false;
            } else if (nowAwaiting > lastSeenAwaitingCount) {
                // تعداد افزایش یافت → نشان بده
                var diff = nowAwaiting - Math.max(lastSeenAwaitingCount, 0);
                show(diff + ' پیام جدید مشتری منتظر پاسخ شماست', '{{ route("tickets.admin.index") }}');
                lastSeenAwaitingCount = nowAwaiting;
            } else {
                // تعداد کاهش یافت یا ثابت ماند — فقط baseline را بروز کن، بنر نشان نده
                lastSeenAwaitingCount = nowAwaiting;
            }
        })
        .catch(function(){});
    }

    setTimeout(check, 8000);
    setInterval(check, 20000);
})();
</script>
