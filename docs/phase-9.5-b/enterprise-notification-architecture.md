# Enterprise Notification Architecture — Phase 9.5-B

## Unified Notification Service

`UnifiedNotificationService` is the single entry point for all platform notifications.

```php
$notifications->send(
    tenantId: $tenantId,
    userId: $userId,
    type: 'ticket.assigned',
    title: 'Ticket Assigned',
    body: 'Ticket #123 assigned to you.',
    channels: ['in_app', 'email'],
    severity: 'info'
);
```

## Channels

| Channel  | Implementation                          | Requires             |
|----------|-----------------------------------------|----------------------|
| `in_app` | Cache-backed, polled via `/api/notifications` | Nothing       |
| `email`  | Laravel Mail via queue                   | SMTP config          |
| `sms`    | `tickets.sms_gateway_resolver` config   | SMS gateway          |

## Notification Preferences

Per-user, per-tenant preferences stored in cache:
- Channel enable/disable (email, SMS, in_app)
- Quiet hours (default 22:00–08:00)
- Digest mode (off / hourly / daily)
- Minimum severity filter

## Quiet Hours

When in quiet hours, non-critical notifications are queued for digest. Critical notifications bypass quiet hours.

## Ticket Notification Events

All workflow events emit notifications via `sendTicketEvent()`:

| Event       | Severity  | Default Channels      |
|-------------|-----------|-----------------------|
| `created`   | info      | in_app                |
| `assigned`  | info      | in_app, email         |
| `replied`   | info      | in_app                |
| `escalated` | warning   | in_app, email         |
| `sla_warn`  | warning   | in_app, email         |
| `sla_breach`| critical  | in_app, email, sms    |
| `reopened`  | info      | in_app                |
| `closed`    | success   | in_app                |

## Escalation Rules

`EscalationRuleEngine` evaluates escalation policies per tenant and event. Rules can be customized via `saveRule()`. Default rules cover SLA warnings, SLA breaches, escalation events, and unresponded tickets.

## Notification Center

`core-ui::notification-center` renders a bell icon with unread badge, dropdown panel, severity indicators, time-ago labels, and mark-read actions. Alpine.js powered with auto-refresh every 30 seconds.

## Delivery Telemetry

`NotificationDeliveryTracker` records delivery success/failure per channel per notification. Failure analytics available for 24-hour windows per tenant.

## API Endpoints

| Endpoint                          | Method | Purpose                  |
|-----------------------------------|--------|--------------------------|
| `/api/notifications`              | GET    | List unread notifications|
| `/api/notifications/{id}/read`    | POST   | Mark single as read      |
| `/api/notifications/mark-all-read`| POST   | Mark all as read         |
| `/api/notifications/preferences`  | PUT    | Update preferences       |
