# Daily Reminders

Daily reminders send in-app notifications for recurring entries and credits/debts that are due soon, due today, or overdue. If push notifications are enabled and the user has an active device token, the same reminder is also sent as a push notification. Email is not used for these reminders.

## Commands

- `php artisan reminders:daily`
- `php artisan reminders:recurring-due`
- `php artisan reminders:credits-debts-due`

The scheduler runs `reminders:daily` once per day at `REMINDERS_DAILY_RUN_TIME`, default `08:00`.

## Environment

- `REMINDERS_ENABLED=true`
- `REMINDERS_DUE_SOON_DAYS=3`
- `REMINDERS_OVERDUE_REPEAT_DAILY=true`
- `REMINDERS_DAILY_RUN_TIME=08:00`

Credits/debts reminders also respect `FEATURE_CREDITS_DEBTS_ENABLED`.

## Delivery Policy

Reminder notifications are delivered through the existing outbound communication pipeline using the database channel only. Push delivery is attempted only when the push feature is enabled, the user preference allows push, and an active device token exists.

Deduplication is stored in `reminder_deliveries`. Upcoming and due-today reminders are sent once per item and due date. Overdue reminders repeat at most once per day when `REMINDERS_OVERDUE_REPEAT_DAILY=true`; when false, each overdue item is notified only once for its due date.
