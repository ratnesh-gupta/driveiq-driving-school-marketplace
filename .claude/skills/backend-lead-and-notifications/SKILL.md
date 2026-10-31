---
name: backend-lead-and-notifications
description: Work on the DriveQ lead engine and notification stack (inquiries, status lifecycle, response-time, reminders, in-app/mail/WhatsApp notifications, messaging driver layer, outreach). Use when changing lead flow or adding a notification, template or messaging driver.
---

# Lead engine & notifications

## Lead lifecycle (files)
- Create: `POST /api/inquiries` -> `InquiryController::store` (`StoreInquiryRequest`: school must be `listing_status=published`, status forced `pending`, `channel` defaults `form`, WhatsApp opt-in stored with ip/time + `Consent` row for signed-in users) -> `event(new InquiryCreated)`.
- Listeners (both `ShouldQueue`, `app/Listeners`): `SendInquiryCreatedNotification` (staff: `NotificationService::notifySchool` in-app + `NewLeadNotification` via `channelsFor($prefs)`) and `SendInquiryConfirmation` (learner email with one-time review token from `ReviewEligibilityService`, WhatsApp if opted in and school enabled it).
- Settings: `SchoolSetting::forSchool($id)['notifications']` keys `new_inquiry, in_app, email, whatsapp, reminder_after_minutes`.
- Statuses: `Inquiry::STATUSES / OPEN_STATUSES / RESPONDED_STATUSES` (`pending -> contacted -> follow_up -> interested -> converted | lost`). Change status only through `InquiryController::update` (history row `LeadStatusHistory`, `markResponded()`, `AuditLog`); `converted` goes through `LearnerController::convertInquiry` when the school has the `learners` feature. Notes/follow-ups/timeline: `LeadNoteController`.
- Metrics: `Inquiry::markResponded()` (first response only), `LeadResponseStats::refreshSchoolBadges()` hourly -> `schools.typical_response_minutes`.
- Reminder: `php artisan driveiq:lead-reminders` every 15 min, once per lead (`reminder_sent_at`), 7-day lookback.

## Add an in-app + mail notification
1. Event if none fits (`app/Events`), queued listener in `app/Listeners` (events are auto-discovered; no manual registration seen).
2. Staff recipients: always `NotificationService::schoolStaff($schoolId)` (role `school`, active only). Never notify instructors/learners with lead data.
3. Mail: `app/Notifications/<Name>.php` implementing `ShouldQueue` with `Queueable`; link to `config('app.frontend_url')`. Outreach mail is separate (`OutreachEmail`, mailer `outreach`).
4. In-app: `NotificationService::notify()` writes `AppNotification` and fires broadcast `NotificationCreated` (private `user.{id}` and `school.{id}`; see risk in backend/CLAUDE.md before enabling Reverb).

## Add a WhatsApp/SMS message
1. Template in `config/messaging.php` `templates` (`text` with `{{vars}}`, ordered `vars`, `provider_template_id`, `dlt_template_id` env keys). Unknown template throws.
2. Notification: `via()` includes `'whatsapp'`, implement `toWhatsApp(): Message` -> `new Message($template, $vars, $relatedType, $relatedId, $schoolId)`; channel `App\Notifications\Channels\WhatsAppChannel` is registered in `AppServiceProvider`.
3. Recipient must opt in: `User::routeNotificationForWhatsapp()` returns phone only with `whatsapp_opt_in_at` and active account; anonymous learners use `Notification::route('whatsapp', $phone)` after storing consent on the inquiry.
4. `Messenger::send` guarantees valid E.164 (`Phone`), one send per (template, related, recipient), masked log in `outbound_messages` (admin view `OutboundMessageController`, 90-day retention). Don't bypass it.
5. New provider: class implementing `App\Messaging\MessageSender::send($channel,$to,$template,$vars,$text): SendResult`, register in `messaging.drivers`, set `MESSAGING_DRIVER`; register DLT/WhatsApp template ids. Tests use `Tests\Support\FakeMessageSender`.

## Tests
`LeadLifecycleTest, LeadNotesTest, LeadRemindersTest, LeadResponseTimeTest, NewLeadMailTest, NotificationTest, WhatsAppLeadAlertsTest, WhatsAppOptInTest, MessagingLayerTest, OutreachTest`. Use `Mail::fake()`, `Notification::fake()`, bind `FakeMessageSender` (see skill `backend-testing`).
