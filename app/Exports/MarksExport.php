<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarksExport
{
    public function __construct(
        protected Collection $marks,
        protected $exam,
    ) {}

    public function download(string $filename): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return new StreamedResponse(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Roll No', 'Student Name', 'Class', 'Section', 'Subject', 'Total Marks', 'Obtained', 'Absent', 'Status']);

            foreach ($this->marks as $m) {
                fputcsv($handle, [
                    $m->student?->roll_no,
                    $m->student?->name,
                    $m->schoolClass?->name,
                    $m->section?->name,
                    $m->subject?->name,
                    $m->total_marks,
                    $m->marks_obtained,
                    $m->is_absent ? 'Yes' : 'No',
                    $m->status,
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }
}
