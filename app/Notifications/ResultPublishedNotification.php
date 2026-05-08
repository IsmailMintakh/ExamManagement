<?php

namespace App\Notifications;

use App\Models\Exam;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to students and linked parent users when an exam's results are
 * officially published. The first notification ever wired for parents/
 * students — until publication, they had nothing to look at in the bell.
 *
 * Sync (not queued) so it works on shared hosting without a queue worker.
 */
class ResultPublishedNotification extends Notification
{
    public function __construct(
        public Exam $exam,
        public ?int $resultId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = $this->resultId
            ? url("/portal/results/{$this->resultId}")
            : url('/portal/results');

        $studentName = $this->studentNameFor($notifiable);

        return (new MailMessage)
            ->subject("Results Published: {$this->exam->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line($studentName
                ? "{$studentName}'s results for \"{$this->exam->name}\" are now available."
                : "Your results for \"{$this->exam->name}\" are now available.")
            ->action('View Results', $url)
            ->line('Open the Family Portal to see the full breakdown and download the report card.');
    }

    public function toArray(object $notifiable): array
    {
        $studentName = $this->studentNameFor($notifiable);

        return [
            'title' => 'Results Published',
            'message' => $studentName
                ? "{$studentName}'s {$this->exam->name} results are now available"
                : "{$this->exam->name} results are now available",
            'exam_id' => $this->exam->id,
            'result_id' => $this->resultId,
            'icon' => 'chart-bar',
            'url' => $this->resultId ? "/portal/results/{$this->resultId}" : '/portal/results',
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        $studentName = $this->studentNameFor($notifiable);

        return [
            'title' => 'Results Published',
            'body' => $studentName
                ? "{$studentName}'s {$this->exam->name} results are out"
                : "{$this->exam->name} results are out",
            'tag' => 'result-published-' . $this->exam->id,
            'url' => $this->resultId ? "/portal/results/{$this->resultId}" : '/portal/results',
        ];
    }

    /**
     * Try to find the student name to personalise the message. Helpful
     * for parents with multiple children — they see WHICH child's results
     * landed instead of a generic line. Returns null for student-self
     * notifications (their own name is in the greeting already).
     */
    protected function studentNameFor(object $notifiable): ?string
    {
        if (!method_exists($notifiable, 'children')) return null;
        if (!$this->resultId) return null;

        // Resolve the result's student via a single-row lookup (cheap).
        $student = \App\Models\Result::where('id', $this->resultId)->value('student_id');
        if (!$student) return null;

        $child = $notifiable->children()->where('students.id', $student)->first();
        return $child?->name;
    }
}
