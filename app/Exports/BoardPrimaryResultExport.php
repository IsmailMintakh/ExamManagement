<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Board-pattern term-wise primary result — Excel version.
 *
 * Reuses the SAME Blade template as the PDF so the two exports stay in
 * sync (change one place, both formats update). Excel-only formatting
 * (borders, freeze row, tight column widths, wrap headers) is applied in
 * the AfterSheet hook.
 */
class BoardPrimaryResultExport implements FromView, WithTitle, WithEvents
{
    public function __construct(protected array $payload) {}

    public function view(): \Illuminate\Contracts\View\View
    {
        return view('reports.board-primary-xlsx', $this->payload);
    }

    public function title(): string
    {
        $cls = $this->payload['schoolClass']->name ?? 'Primary';
        $sec = $this->payload['section']->name ?? '';
        return trim("{$cls} {$sec}");
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Full-sheet border + centered cells for a clean look. Range
                // is generous so any added rows are still bordered.
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();
                $range = "A1:{$lastCol}{$lastRow}";

                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FF444444'],
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                ]);

                // Fit-to-one-page landscape print setup so the file prints
                // exactly like the PDF version.
                $sheet->getPageSetup()
                    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(1);
                $sheet->getPageMargins()->setTop(0.3)->setRight(0.3)->setLeft(0.3)->setBottom(0.3);
            },
        ];
    }
}
