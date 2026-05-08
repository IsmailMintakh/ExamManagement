<?php

namespace App\Notifications;

use App\Models\Exam;
use App\Models\Result;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent when a published result is amended (mark correction). Different
 * from ResultPublishedNotification so parents/students can tell at a glance
 * that something CHANGED about a result they may have already seen.
 *
 * Sync (not queued) so it works without a queue worker.
 */
class ResultAmendedNotification extends Notification
{
    public function __construct(
        public Result $result,
        public string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url("/portal/results/{$this->result->id}");
        $examName = $this->result->exam?->name ?? 'an exam';
        $studentName = $this->studentNameFor($notifiable);

        return (new MailMessage)
            ->subject("Result Amended: {$examName}")
            ->greeting("Hello {$notifiable->name},")
            ->line($studentName
                ? "{$studentName}'s result for \"{$examName}\" has been amended."
                : "Your result for \"{$examName}\" has been amended.")
            ->line("Reason: {$this->reason}")
            ->action('View Updated Result', $url)
            ->line('The amendment is also recorded in the result audit trail.');
    }

    public function toArray(object $notifiable): array
    {
        $studentName = $this->studentNameFor($notifiable);
        $examName = $this->result->exam?->name ?? 'Exam';

        return [
            'title' => 'Result Amended',
            'message' => $studentName
                ? "{$studentName}'s {$examName} result was amended ({$this->reason})"
                : "Your {$examName} result was amended ({$this->reason})",
            'exam_id' => $this->result->exam_id,
            'result_id' => $this->result->id,
            'reason' => $this->reason,
            'icon' => 'arrow-path',
            'url' => "/portal/results/{$this->result->id}",
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        $studentName = $this->studentNameFor($notifiable);
        $examName = $this->result->exam?->name ?? 'Exam';

        return [
            'title' => 'Result Amended',
            'body' => $studentName
                ? "{$studentName}'s {$examName} result was updated"
                : "{$examName} result was updated",
            'tag' => 'result-amended-' . $this->result->id,
            'url' => "/portal/results/{$this->result->id}",
            'requireInteraction' => true,
        ];
    }

    protected function studentNameFor(object $notifiable): ?string
    {
        if (!method_exists($notifiable, 'children')) return null;
        $child = $notifiable->children()->where('students.id', $this->result->student_id)->first();
        return $child?->name;
    }
}
