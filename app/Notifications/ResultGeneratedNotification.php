<?php

namespace App\Notifications;

use App\Models\Exam;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Sync (not queued) so it works on shared hosting without a queue worker.
class ResultGeneratedNotification extends Notification
{

    public function __construct(
        public Exam $exam,
        public string $className,
        public string $sectionName,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url("/results/{$this->exam->id}/generate");

        return (new MailMessage)
            ->subject("Result Generated: {$this->exam->name} - {$this->className}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Results have been generated for {$this->className} - {$this->sectionName} in {$this->exam->name}.")
            ->line('You can view and verify the generated results from the dashboard.')
            ->action('View Results', $url)
            ->line('Thank you for using the Exam Management System.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Result Generated',
            'message' => "Results generated for {$this->className} - {$this->sectionName} in {$this->exam->name}",
            'exam_id' => $this->exam->id,
            'icon' => 'chart-bar',
            'url' => "/results/{$this->exam->id}/generate",
        ];
    }
}
