# Email Setup

The Exam Management System uses Laravel notifications to send emails for key
events (marks submitted, results generated, results submitted to DDO, results
approved, results returned for correction).

All notification classes implement `ShouldQueue`, so emails are dispatched to
the queue worker rather than blocking the request.

## Configure SMTP

To enable real emails, add the following to your `.env` file:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io   # or your SMTP host (e.g., smtp.gmail.com)
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@exampro.com
MAIL_FROM_NAME="ExamPro System"
```

## Use logs (no real email)

For local development, route mail to the application log instead:

```
MAIL_MAILER=log
```

Sent emails will appear in `storage/logs/laravel.log`.

## Run the queue worker

Because notifications are queued, make sure a worker is running:

```
php artisan queue:work
```

The queue connection is configured by `QUEUE_CONNECTION` in `.env` (default
`database`). To run jobs synchronously during development, set
`QUEUE_CONNECTION=sync`.

## Notifications that send email

- `MarksSubmittedNotification`
- `ResultGeneratedNotification`
- `ResultSubmittedToDdoNotification`
- `ResultApprovedNotification`
- `ResultReturnedForCorrectionNotification`
