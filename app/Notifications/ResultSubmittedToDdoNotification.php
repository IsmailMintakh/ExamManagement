<?php

namespace App\Notifications;

use App\Models\Exam;
use App\Models\School;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResultSubmittedToDdoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Exam $exam,
        public School $school,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url("/results/{$this->exam->id}/generate");

        return (new MailMessage)
            ->subject("Results Submitted for Review: {$this->exam->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->school->name} has submitted results for {$this->exam->name} for your review and approval.")
            ->line('Please review the submission and either approve it or return it for correction.')
            ->action('Review Submission', $url)
            ->line('Thank you for using the Exam Management System.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Result Submitted for Review',
            'message' => "{$this->school->name} submitted results for {$this->exam->name}",
            'exam_id' => $this->exam->id,
            'school_id' => $this->school->id,
            'icon' => 'document-check',
            'url' => "/results/{$this->exam->id}/generate",
        ];
    }
}
