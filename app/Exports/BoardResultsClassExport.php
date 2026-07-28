<?php

namespace App\Exports;

use App\Models\BoardExam;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Class-wide board-result Excel export.
 *
 * Renders the same blade template used by the class-summary PDF, so
 * downloaders get the identical layout in Excel (all students × all
 * subjects with theory/practical/total/grade/pass columns + overall
 * percentage/grade/division/position/result). Formatting (borders,
 * frozen header, print setup) applied in AfterSheet.
 */
class BoardResultsClassExport implements FromView, WithTitle, WithEvents
{
    public function __construct(
        protected BoardExam $exam,
        protected Collection $results,
    ) {}

    public function view(): \Illuminate\Contracts\View\View
    {
        // Distinct subjects across every result — one column per subject
        // in the exported sheet.
        $subjects = collect();
        foreach ($this->results as $r) {
            foreach ($r->subjects as $sub) {
                if (!$subjects->firstWhere('id', $sub->subject_id)) {
                    $subjects->push((object) [
                        'id'   => $sub->subject_id,
                        'name' => $sub->subject?->name,
                        'code' => $sub->subject?->code,
                    ]);
                }
            }
        }

        return view('reports.board-result-summary-xlsx', [
            'exam'     => $this->exam,
            'results'  => $this->results,
            'subjects' => $subjects->values(),
        ]);
    }

    public function title(): string
    {
        return substr(($this->exam->schoolClass?->name ?? 'Class').' Board', 0, 30);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                // Full-grid style — thin borders + centred alignment.
                $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFCCCCCC'],
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                        'wrapText'   => true,
                    ],
                    'font' => ['size' => 9, 'name' => 'Calibri'],
                ]);

                // Title row.
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEEF2FF']],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(24);

                // Page setup — landscape A3, fit-to-1-page width.
                $sheet->getPageSetup()
                    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.3)->setRight(0.3)->setLeft(0.3)->setBottom(0.3);
            },
        ];
    }
}
