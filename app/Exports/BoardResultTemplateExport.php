<?php

namespace App\Exports;

use App\Models\BoardExam;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Downloadable Excel template for bulk board-result entry.
 *
 * Layout:
 *   Row 1: header — Roll No | Name | Board Roll | <Subject1> Theory | <Subject1> Practical | <Subject2> Theory | ...
 *   Rows 2+: one row per student, pre-filled with Roll No + Name.
 *
 * Teacher fills the empty cells offline and re-uploads via the import
 * flow — same class parses this file, so header names must stay put.
 */
class BoardResultTemplateExport implements FromArray, WithTitle, WithEvents
{
    public function __construct(
        protected BoardExam $exam,
        protected Collection $subjects,
        protected Collection $students,
    ) {}

    public function array(): array
    {
        // Header: fixed columns + 2 columns per subject (Theory + Practical).
        $header = ['Roll No', 'Name', 'Board Roll'];
        foreach ($this->subjects as $sub) {
            $header[] = $sub->name.' Theory';
            $header[] = $sub->name.' Practical';
        }

        $rows = [$header];
        foreach ($this->students as $s) {
            $row = [$s->roll_no, $s->name, ''];
            foreach ($this->subjects as $_) {
                $row[] = null; // theory
                $row[] = null; // practical
            }
            $rows[] = $row;
        }
        return $rows;
    }

    public function title(): string
    {
        return substr($this->exam->title ?? 'Template', 0, 30);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();

                // Header row style.
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 10],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                    'fill'      => [
                        'fillType'   => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FFEEF2FF'],
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->freezePane('D2');   // freeze rollno / name / board-roll cols

                // Column widths.
                $sheet->getColumnDimension('A')->setWidth(9);   // Roll
                $sheet->getColumnDimension('B')->setWidth(22);  // Name
                $sheet->getColumnDimension('C')->setWidth(11);  // Board Roll
                // Subject cols: 9 each.
                $lastIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastCol);
                for ($i = 4; $i <= $lastIdx; $i++) {
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
                    $sheet->getColumnDimension($col)->setWidth(9);
                }

                // Thin border on the whole grid.
                $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
                    'borders' => ['allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => 'FFDDDDDD'],
                    ]],
                ]);
            },
        ];
    }
}
