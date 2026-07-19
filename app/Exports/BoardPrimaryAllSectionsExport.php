<?php

namespace App\Exports;

use App\Models\Exam;
use App\Models\User;
use App\Services\BoardPrimaryReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * All-primary-sections Excel export.
 *
 * One workbook, one sheet per section. Each sheet uses the same
 * BoardPrimaryResultExport that powers the single-section download —
 * identical formatting, one-page fit, teacher rotation, everything.
 * Tabs at the bottom of the workbook are ordered left-to-right by
 * class sort_order then section name.
 */
class BoardPrimaryAllSectionsExport implements WithMultipleSheets
{
    public function __construct(
        protected Exam $exam,
        protected Collection $sections,   // Section models, already ordered
        protected ?User $user = null,
    ) {}

    /**
     * Build one sheet per section using the shared payload service.
     * Returns an array of BoardPrimaryResultExport instances — Maatwebsite
     * calls each one's title() and view() in order.
     */
    public function sheets(): array
    {
        $service = app(BoardPrimaryReportService::class);
        $sheets = [];
        foreach ($this->sections as $section) {
            $payload = $service->buildPayload($this->exam, $section, $this->user);
            $sheets[] = new BoardPrimaryResultExport($payload);
        }
        return $sheets;
    }
}
