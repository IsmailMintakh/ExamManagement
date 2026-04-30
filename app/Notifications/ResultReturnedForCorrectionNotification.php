<?php

namespace App\Notifications;

use App\Models\Exam;
use App\Models\ResultSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResultReturnedForCorrectionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Exam $exam,
        public ResultSubmission $submission,
        public string $remarks,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url("/results/{$this->exam->id}/generate");

        return (new MailMessage)
            ->subject("Action Required: Results Returned for Correction - {$this->exam->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("The DDO has returned the results for {$this->exam->name} for correction.")
            ->line("DDO Remarks: {$this->remarks}")
            ->line('Please address the remarks and resubmit the results for review.')
            ->action('Address Corrections', $url)
            ->line('Thank you for using the Exam Management System.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Results Returned for Correction',
            'message' => "DDO returned results for {$this->exam->name}. Remarks: {$this->remarks}",
            'exam_id' => $this->exam->id,
            'submission_id' => $this->submission->id,
            'school_id' => $this->submission->school_id,
            'remarks' => $this->remarks,
            'icon' => 'exclamation-triangle',
            'url' => "/results/{$this->exam->id}/generate",
        ];
    }
}
