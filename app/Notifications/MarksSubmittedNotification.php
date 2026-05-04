<?php

namespace App\Notifications;

use App\Models\Exam;
use App\Models\Subject;
use App\Models\Section;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Sync (not queued) — keeps the notification system functional on shared
// hosting where there's no `php artisan queue:work` process running.
// The cost is ~50-200ms added to the request that triggers it.
class MarksSubmittedNotification extends Notification
{

    public function __construct(
        public Exam $exam,
        public Subject $subject,
        public Section $section,
        public string $teacherName
    ) {}

    public function via(object $notifiable): array
    {
        // Database for in-app, mail for email digest, WebPush for instant
        // browser/desktop notifications.
        return ['database', 'mail', WebPushChannel::class];
    }

    /**
     * Browser push payload — kept short so it fits notification-card limits.
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Marks Submitted',
            'body'  => "{$this->teacherName} submitted {$this->subject->name} marks for {$this->section->full_name}.",
            'tag'   => "marks-submitted-{$this->exam->id}-{$this->subject->id}-{$this->section->id}",
            'url'   => "/marks/{$this->exam->id}/entry/{$this->subject->id}/{$this->section->id}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url("/marks/{$this->exam->id}/entry/{$this->subject->id}/{$this->section->id}");

        return (new MailMessage)
            ->subject("Marks Submitted: {$this->subject->name} - {$this->exam->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->teacherName} has submitted marks for {$this->subject->name} ({$this->section->full_name}) in {$this->exam->name}.")
            ->line('You can review the submitted marks using the link below.')
            ->action('Review Marks', $url)
            ->line('Thank you for using the Exam Management System.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Marks Submitted',
            'message' => "{$this->teacherName} submitted marks for {$this->subject->name} - {$this->section->full_name} in {$this->exam->name}",
            'exam_id' => $this->exam->id,
            'subject_id' => $this->subject->id,
            'section_id' => $this->section->id,
            'icon' => 'clipboard-check',
            'url' => "/marks/{$this->exam->id}/entry/{$this->subject->id}/{$this->section->id}",
        ];
    }
}
