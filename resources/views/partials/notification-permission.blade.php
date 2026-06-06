{{--
 | Notification Permission Banner — SaaS Platform.
 | @include('partials.notification-permission', ['context' => 'platform_admin'])
--}}
@php $ctx = $context ?? 'platform_admin'; @endphp

<div id="notif-perm-banner-{{ $ctx }}"
     style="display:none;position:fixed;bottom:1.25rem;left:50%;transform:translateX(-50%);width:calc(100% - 2rem);max-width:540px;z-index:9500;
            background:linear-gradient(135deg,#1e1b4b 0%,#312e81 100%);color:#fff;
            border-radius:1.125rem;box-shadow:0 12px 40px rgba(0,0,0,.4);
            padding:1.125rem 1.25rem;animation:npbSlide .35s cubic-bezier(.16,1,.3,1)"
     role="dialog" aria-label="فعال‌سازی اعلان‌های مرورگر">
    <style>
    @keyframes npbSlide{from{opacity:0;transform:translateX(-50%) translateY(1.5rem)}to{opacity:1;transform:translateX(-50%) translateY(0)}}
    .npb-btn{padding:.45rem 1rem;border:none;border-radius:.65rem;font-size:.8rem;font-weight:700;cursor:pointer;transition:opacity .15s}
    .npb-btn:hover{opacity:.85}
    </style>
    <div style="display:flex;align-items:flex-start;gap:.875rem">
        <div style="width:2.5rem;height:2.5rem;border-radius:50%;background:rgba(99,102,241,.4);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px">
            <svg style="width:1.2rem;height:1.2rem" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
        </div>
        <div style="flex:1;min-width:0">
            <p style="font-weight:700;font-size:.9rem;margin:0 0 .3rem 0">اعلان‌های مرورگر را فعال کنید 🔔</p>
            <p style="font-size:.78rem;opacity:.82;line-height:1.6;margin:0 0 .75rem 0">
                برای دریافت فوری اعلان تیکت‌های جدید و پیام‌های مشتریان، اعلان‌ها را فعال کنید.
            </p>
            <div id="npb-steps-{{ $ctx }}" style="font-size:.73rem;opacity:.7;line-height:1.7;margin-bottom:.85rem;display:none;background:rgba(255,255,255,.08);padding:.6rem .8rem;border-radius:.6rem">
                <strong>Chrome / Edge:</strong> روی آیکون 🔒 کنار آدرس کلیک کنید ← «اعلان‌ها» ← «مجاز»<br>
                <strong>Firefox:</strong> آیکون سپر 🛡 در نوار آدرس ← «مجوزهای سایت» ← «اعلان‌ها مجاز»
            </div>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap">
                <button class="npb-btn" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff" onclick="window._npbRequest('{{ $ctx }}')">
                    🔔 فعال‌سازی
                </button>
                <button class="npb-btn" style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.22);color:rgba(255,255,255,.85)" onclick="window._npbDismiss('{{ $ctx }}')">
                    بعداً
                </button>
                <button onclick="var s=document.getElementById('npb-steps-{{ $ctx }}');s.style.display=s.style.display==='none'?'block':'none'"
                        style="background:none;border:none;color:rgba(255,255,255,.6);font-size:.73rem;cursor:pointer;text-decoration:underline;padding:0;align-self:center">
                    راهنمای دستی
                </button>
            </div>
        </div>
        <button onclick="window._npbDismiss('{{ $ctx }}')"
                style="background:rgba(255,255,255,.15);border:none;border-radius:50%;width:1.75rem;height:1.75rem;color:#fff;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;line-height:1">×</button>
    </div>
</div>

<script>
(function(){
    var CTX = '{{ $ctx }}';
    var SK  = 'npb_d_' + CTX;
    function banner() { return document.getElementById('notif-perm-banner-' + CTX); }
    window._npbRequest = window._npbRequest || {};
    window._npbRequest[CTX] = function() {
        if (!('Notification' in window)) { window._npbDismiss(CTX); return; }
        Notification.requestPermission().then(function(p) {
            window._npbDismiss(CTX);
            if (p === 'granted') {
                try { new Notification('اعلان‌ها فعال شدند ✓', {body:'از این پس پیام‌های جدید را فوری دریافت می‌کنید.',icon:'/favicon.ico',dir:'rtl',tag:'npb-'+CTX}); } catch(e){}
            }
        });
    };
    // Unified request helper used by button onclick
    window._npbRequest = function(ctx) {
        if (window._npbRequest[ctx]) window._npbRequest[ctx]();
    };
    window._npbDismiss = function(ctx) {
        if (ctx !== CTX) return;
        localStorage.setItem(SK, '1');
        var b = banner();
        if (b) { b.style.opacity='0'; b.style.transition='opacity .25s'; setTimeout(function(){b.style.display='none';},260); }
    };
    if (!('Notification' in window)) return;
    if (Notification.permission !== 'default') return;
    if (localStorage.getItem(SK) === '1') return;

    var _shown = false;
    var autoT  = setTimeout(function(){
        if (_shown) return; _shown = true;
        var b = banner(); if (b) b.style.display = 'block';
    }, 4000);

    ['click','keydown','scroll'].forEach(function(ev) {
        document.addEventListener(ev, function h() {
            clearTimeout(autoT);
            if (_shown) { document.removeEventListener(ev,h); return; }
            _shown = true;
            document.removeEventListener(ev,h);
            if (Notification.permission !== 'default') return;
            if (localStorage.getItem(SK) === '1') return;
            var b = banner(); if (b) b.style.display = 'block';
            Notification.requestPermission().then(function(p){
                if (p !== 'default') window._npbDismiss(CTX);
            }).catch(function(){});
        }, {once:true, passive:true});
    });
})();
</script>
