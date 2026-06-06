/**
 * SUPPORT-SUITE-01 — Section E
 * Service Worker for Web Push notifications (ticket replies)
 * Works when browser tab is closed or site is inactive
 */

const CACHE_VERSION = 'sp-tickets-sw-v1';

self.addEventListener('install', (e) => {
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil(clients.claim());
});

// ── Push event: triggered by server via Web Push API ─────────────────────────
self.addEventListener('push', (e) => {
    if (!e.data) return;

    let payload;
    try {
        payload = e.data.json();
    } catch {
        payload = { title: 'تیکت پشتیبانی', body: e.data.text(), url: '/admin/tickets' };
    }

    const title   = payload.title   || 'تیکت پشتیبانی — پیام جدید';
    const body    = payload.body    || 'پیام جدیدی دریافت شد';
    const url     = payload.url     || '/admin/tickets';
    const tag     = payload.tag     || 'ticket-reply';
    const ticketId = payload.ticket_id;

    const options = {
        body,
        icon:   '/favicon.ico',
        badge:  '/favicon.ico',
        dir:    'rtl',
        lang:   'fa',
        tag,
        renotify: true,
        data: { url, ticketId },
        actions: [
            { action: 'open',    title: 'مشاهده تیکت' },
            { action: 'dismiss', title: 'بستن' },
        ],
    };

    e.waitUntil(
        self.registration.showNotification(title, options)
    );
});

// ── Notification click: open or focus the ticket page ────────────────────────
self.addEventListener('notificationclick', (e) => {
    e.notification.close();

    if (e.action === 'dismiss') return;

    const targetUrl = e.notification.data?.url || '/admin/tickets';

    e.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windowClients) => {
            // Focus existing tab if already open
            for (const client of windowClients) {
                if (client.url.includes(targetUrl.split('?')[0]) && 'focus' in client) {
                    return client.focus();
                }
            }
            // Otherwise open a new tab
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});
