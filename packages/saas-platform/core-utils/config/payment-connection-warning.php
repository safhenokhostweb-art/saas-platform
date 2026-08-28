<?php

$csv = static fn (string $value): array => array_values(array_filter(array_map(
    static fn (string $item): string => strtoupper(trim($item)),
    preg_split('/\s*,\s*/', $value) ?: []
)));

return [
    'enabled' => filter_var(env('PAYMENT_CONNECTION_WARNING_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'message' => env(
        'PAYMENT_CONNECTION_WARNING_MESSAGE',
        'اتصال شما خارج از ایران تشخیص داده شده است. برای جلوگیری از اختلال در اتصال به درگاه بانکی، پیشنهاد می‌شود فیلترشکن خود را خاموش کنید.'
    ),
    'recheck_button' => env('PAYMENT_CONNECTION_WARNING_RECHECK_BUTTON', 'فیلترشکن را خاموش کردم'),
    'continue_button' => env('PAYMENT_CONNECTION_WARNING_CONTINUE_BUTTON', 'ادامه به پرداخت'),
    'still_foreign_message' => env(
        'PAYMENT_CONNECTION_WARNING_STILL_FOREIGN_MESSAGE',
        'اتصال شما همچنان خارج از ایران تشخیص داده می‌شود.'
    ),
    'warning_countries' => $csv((string) env('PAYMENT_CONNECTION_WARNING_COUNTRIES', '*')),
    'exempt_countries' => $csv((string) env('PAYMENT_CONNECTION_WARNING_EXEMPT_COUNTRIES', 'IR')),

    // Nginx MUST overwrite this header from $realip_remote_addr. Never use
    // X-Forwarded-For, X-Real-IP, Ar-Real-Ip, or REMOTE_ADDR as the CDN peer.
    'country_header' => 'X-Country-Code',
    'origin_peer_header' => 'X-Origin-Peer-IP',
    // Enable only after an edge probe proves Arvan overwrites a client-supplied
    // X-Country-Code. Peer validation alone cannot prove header provenance.
    'country_header_verified' => filter_var(
        env('ARVAN_COUNTRY_HEADER_VERIFIED', false),
        FILTER_VALIDATE_BOOLEAN
    ),

    // Generated from ArvanCloud's official https://www.arvancloud.ir/en/ips.txt.
    // Production should point both Nginx generation and this resolver at the
    // same normalized source file. An empty/missing list deliberately fails open.
    'trusted_proxy_cidr_file' => env(
        'ARVAN_CDN_CIDR_FILE',
        DIRECTORY_SEPARATOR === '/' ? '/etc/nginx/snippets/arvan-cdn-cidrs.txt' : ''
    ),
    'trusted_proxy_cidrs' => $csv((string) env('ARVAN_CDN_CIDRS', '')),
];
