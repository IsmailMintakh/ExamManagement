<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AwardListExport
{
    public function __construct(
        protected Collection $results,
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
            fputcsv($handle, ['Position', 'Roll No', 'Student Name', 'Father Name', 'Class', 'Section', 'School', 'Obtained', 'Total', 'Percentage', 'Grade']);

            foreach ($this->results as $r) {
                fputcsv($handle, [
                    $r->position,
                    $r->student?->roll_no,
                    $r->student?->name,
                    $r->student?->father_name,
                    $r->schoolClass?->name,
                    $r->section?->name,
                    $r->school?->name,
                    $r->obtained_marks,
                    $r->total_marks,
                    $r->percentage . '%',
                    $r->grade,
                ]);
            }
            fclose($handle);
        }, 200, $headers);
    }
}
