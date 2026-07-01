<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResultsExport
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

        // Bulk-load assessment marks for any primary students so the CSV can
        // include the Assessment column without N+1 queries. We expose the
        // columns whenever ANY result in the export is primary, with empty
        // cells for non-primary rows — keeps the CSV shape stable for Excel.
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

        // Resolve a branded header for the CSV — CSV can't embed a raster
        // logo, so we surface the school name, address and exam title as
        // leading meta rows so the file identifies itself when opened.
        $school = $this->results->first()?->school ?? null;

        return new StreamedResponse(function () use ($hasPrimary, $assessmentByStudent, $school) {
            $handle = fopen('php://output', 'w');
            // Leading branding block. Excel/Sheets treat these as normal
            // rows above the data table.
            if ($school) {
                fputcsv($handle, [$school->name]);
                if (!empty($school->address)) fputcsv($handle, [$school->address]);
            }
            fputcsv($handle, ['Exam Result Sheet — ' . $this->exam->name]);
            fputcsv($handle, ['Generated: ' . now()->format('d M Y, H:i')]);
            fputcsv($handle, []);

            $header = ['Position', 'Roll No', 'Student Name', 'Father Name', 'Class', 'Section', 'School',
                       'Total Marks', 'Obtained Marks', 'Percentage', 'Grade', 'Status'];
            if ($hasPrimary) {
                array_push($header, 'Assessment', 'Assessment Total', 'Assessment Status');
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
                    $r->total_marks,
                    $r->obtained_marks,
                    $r->percentage . '%',
                    $r->grade,
                    $r->is_passed ? 'PASS' : 'RETRY',
                ];
                if ($hasPrimary) {
                    $isPrimary = $r->schoolClass?->isPrimaryStage();
                    $am = $isPrimary ? $assessmentByStudent->get($r->student_id) : null;
                    array_push(
                        $row,
                        $am ? (float) $am->marks_obtained : ($isPrimary ? '' : '—'),
                        $am ? (float) $am->marks_total    : ($isPrimary ? '' : '—'),
                        $am ? ($am->isPassed() ? 'PASS' : 'RETRY') : ($isPrimary ? 'NOT ENTERED' : '—'),
                    );
                }
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 200, $headers);
    }
}
