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

        $primaryStudentIds = $this->results
            ->filter(fn ($r) => $r->schoolClass?->isPrimaryStage())
            ->pluck('student_id')
            ->unique();
        $hasPrimary = $primaryStudentIds->isNotEmpty();
        $assessmentByStudent = collect();
        if ($hasPrimary) {
            $assessmentByStudent = \App\Models\AssessmentMark::whereIn('student_id', $primaryStudentIds)
                ->where('academic_session_id', $this->exam->academic_session_id)
                ->get()
                ->keyBy('student_id');
        }

        return new StreamedResponse(function () use ($hasPrimary, $assessmentByStudent) {
            $handle = fopen('php://output', 'w');
            $header = ['Position', 'Roll No', 'Student Name', 'Father Name', 'Class', 'Section', 'School',
                       'Obtained', 'Total', 'Percentage', 'Grade'];
            if ($hasPrimary) {
                array_push($header, 'Assessment', 'Assessment Total');
            }
            fputcsv($handle, $header);

            foreach ($this->results as $r) {
                $row = [
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
                ];
                if ($hasPrimary) {
                    $isPrimary = $r->schoolClass?->isPrimaryStage();
                    $am = $isPrimary ? $assessmentByStudent->get($r->student_id) : null;
                    array_push(
                        $row,
                        $am ? (float) $am->marks_obtained : ($isPrimary ? '' : '—'),
                        $am ? (float) $am->marks_total    : ($isPrimary ? '' : '—'),
                    );
                }
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 200, $headers);
    }
}
