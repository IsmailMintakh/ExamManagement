<?php

namespace App\Notifications;

use App\Models\Exam;
use App\Models\ResultSubmission;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Sync (not queued) so it works on shared hosting without a queue worker.
class ResultApprovedNotification extends Notification
{

    public function __construct(
        public Exam $exam,
        public ResultSubmission $submission,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => '✓ Results Approved',
            'body'  => "DDO approved your {$this->exam->name} results — they're now finalized.",
            'tag'   => "result-approved-{$this->submission->id}",
            'url'   => "/results/{$this->exam->id}/section/{$this->submission->section_id}",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url("/results/{$this->exam->id}/section/{$this->submission->section_id}");

        return (new MailMessage)
            ->subject("Results Approved: {$this->exam->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your submission for {$this->exam->name} has been approved by the DDO and finalized.")
            ->line('The finalized results are now available for distribution and reporting.')
            ->action('View Approved Results', $url)
            ->line('Thank you for using the Exam Management System.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Results Approved',
            'message' => "Your submission for {$this->exam->name} has been approved by the DDO and finalized.",
            'exam_id' => $this->exam->id,
            'submission_id' => $this->submission->id,
            'school_id' => $this->submission->school_id,
            'icon' => 'check-badge',
            'url' => "/results/{$this->exam->id}/section/{$this->submission->section_id}",
        ];
    }
}
