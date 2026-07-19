<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromView;
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
                $lastCol = $sheet->getHighestColumn();
                $lastRow = $sheet->getHighestRow();
                $lastColIdx = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($lastCol);

                $subjectCount = count($this->payload['subjects'] ?? []);
                // 5 lead + subjectCount×4 + 5 aggregate = same layout as
                // the template. Header title row spans all of them.
                $totalCols = 5 + ($subjectCount * 4) + 5;

                // ─── FULL-SHEET STYLE: borders + centered wrap ───
                $sheet->getStyle("A1:{$lastCol}{$lastRow}")->applyFromArray([
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
                    'font' => ['size' => 9, 'name' => 'Calibri'],
                ]);

                // ─── COLUMN WIDTHS ───
                // Tighter than before so wide classes (10-14 subjects,
                // 40+ students) still fit one page with a reasonable
                // print scale. If you widen these, print scaling drops.
                $widths = [3, 6, 16, 14, 8];
                for ($i = 0; $i < $subjectCount; $i++) {
                    array_push($widths, 4.5, 4.5, 4.5, 5.5);
                }
                array_push($widths, 5, 7, 5, 5, 11);
                foreach ($widths as $idx => $w) {
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx + 1);
                    if ($idx + 1 > $lastColIdx) break;
                    $sheet->getColumnDimension($col)->setWidth($w);
                }

                // ─── TITLE ROWS ───
                // Plain — no coloured fills, black bold text only.
                $sheet->getRowDimension(1)->setRowHeight(22);
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13],
                ]);

                $sheet->getRowDimension(2)->setRowHeight(16);
                $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 10],
                ]);

                $sheet->getRowDimension(3)->setRowHeight(14);
                $sheet->getStyle("A3:{$lastCol}3")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9],
                ]);

                // ─── HEADER ROWS (rows 4, 5, 6) ───
                for ($r = 4; $r <= 6; $r++) {
                    $sheet->getRowDimension($r)->setRowHeight($r === 4 ? 20 : 12);
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 8],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                    ]);
                }

                // ─── STUDENT ROW STYLE ───
                // Rows 7 onward hold student data. Tight row height so a
                // full 40-student class still fits on one page.
                $studentStart = 7;
                $studentEnd = 6 + count($this->payload['rows'] ?? collect());
                // Stats-footer row markers are derived from $studentEnd —
                // hoisted up here so the yellow-column code below can use
                // $statsEnd. Actual stats-footer styling still happens
                // further down; these are just the coordinates.
                $metricStart  = $studentEnd + 1;
                $teacherStart = $metricStart + 2;
                $statsEnd     = $metricStart + 10;
                if ($studentEnd >= $studentStart) {
                    for ($r = $studentStart; $r <= $studentEnd; $r++) {
                        $sheet->getRowDimension($r)->setRowHeight(12);
                    }

                    // (Yellow column highlighting removed — sheet is now
                    // plain white to match the user's preferred look.)
                    // Name + father columns left-aligned
                    $sheet->getStyle("C{$studentStart}:D{$studentEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    // Remarks column left-aligned (last column)
                    $sheet->getStyle("{$lastCol}{$studentStart}:{$lastCol}{$studentEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    // (Zebra-striping removed — sheet is plain white.)
                }

                // Freeze the header block (rows 1-6) so scrolling students
                // keeps the column labels visible.
                $sheet->freezePane("A7");

                // ─── STATS-FOOTER LAYOUT (11 rows) ───
                // Row 1 (metricStart)  : Total    | val | Average    | subj cells (avg per term) | ... | A++
                // Row 2                : Appeared | val | Percentage | subj cells (%   per term) | ... | A+
                // Row 3 (teacherStart) : Passed   | val | Name of Sub. Teacher (rowspan=9)      | teacher × per-term (rowspan=9) | Co-Curr | A
                // Rows 4-5             : Failed / Pass% | val ... grade band
                // Rows 6-10            : invisible left ... grade bands B..U
                // Row 11               : Total grade
                // ($metricStart / $teacherStart / $statsEnd hoisted above)
                if ($studentEnd >= $studentStart) {
                    // Tight uniform row height. The teacher block spans 9
                    // rows (rowspan=9 in HTML → merged cell), so 9×11px =
                    // ~99px total — plenty of room for the rotated names
                    // without wasting vertical space.
                    for ($r = $metricStart; $r <= $statsEnd; $r++) {
                        $sheet->getRowDimension($r)->setRowHeight(11);
                    }

                    // "Average" and "Percentage" — HORIZONTAL, plain white.
                    $sheet->getStyle("E{$metricStart}:E".($metricStart + 1))->applyFromArray([
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'font' => ['bold' => true, 'size' => 9],
                    ]);

                    // "Name of Sub. Teacher" — column E, teacher row, ROTATED 90°.
                    $sheet->getStyle("E{$teacherStart}")->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'textRotation' => 90,
                            'wrapText' => false,
                        ],
                        'font' => ['bold' => true, 'size' => 9],
                    ]);

                    // Teacher name cells (per-term, one per subject sub-column)
                    // — subject cols start at F, run for subjectCount*4 columns
                    // — all rotated 90° in the teacher row.
                    $subjLastColIdx = 5 + ($subjectCount * 4);   // 1-based: F=6, so last = 5 + subj*4
                    $subjFirstCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(6);
                    $subjLastCol  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($subjLastColIdx);
                    $sheet->getStyle("{$subjFirstCol}{$teacherStart}:{$subjLastCol}{$teacherStart}")->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'textRotation' => 90,
                            'wrapText' => false,
                        ],
                        'font' => ['bold' => true, 'size' => 9],
                    ]);

                    // Metric label column A..C (colspan=3) — left-align.
                    $sheet->getStyle("A{$metricStart}:C".($metricStart + 4))->applyFromArray([
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                        'font' => ['bold' => true, 'size' => 10],
                    ]);
                    // Class-wide value column D — centred, bold, plain black.
                    $sheet->getStyle("D{$metricStart}:D".($metricStart + 4))->applyFromArray([
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                        'font' => ['bold' => true, 'size' => 10],
                    ]);
                }

                // ─── SIGNATURE STRIP ROW HEIGHTS ───
                // Compact so the strip doesn't push the sheet onto a
                // second page for large classes.
                $sigLabelRow = $statsEnd + 2;
                $sigBoxRow   = $sigLabelRow + 1;
                if ($sigBoxRow <= $lastRow) {
                    $sheet->getRowDimension($statsEnd + 1)->setRowHeight(4);   // spacer
                    $sheet->getRowDimension($sigLabelRow)->setRowHeight(15);
                    $sheet->getRowDimension($sigBoxRow)->setRowHeight(32);
                    $sheet->getStyle("A{$sigLabelRow}:{$lastCol}{$sigLabelRow}")->applyFromArray([
                        'font' => ['bold' => true, 'size' => 9],
                    ]);
                }

                // ─── PRINT SETUP ───
                // Tight margins + fit-to-one-page in BOTH directions so
                // Excel scales the whole sheet (title + header + all
                // students + stats + signatures) onto a single A3 sheet
                // regardless of class size.
                $sheet->getPageSetup()
                    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(1);
                $sheet->getPageMargins()->setTop(0.15)->setRight(0.15)->setLeft(0.15)->setBottom(0.15);
                $sheet->getPageSetup()->setHorizontalCentered(true);
                $sheet->getPageSetup()->setVerticalCentered(false);

                // Explicit print area = every populated cell — prevents
                // Excel from padding the print region with empty rows.
                $sheet->getPageSetup()->setPrintArea("A1:{$lastCol}{$lastRow}");

                // Repeat header rows on every printed page (defensive —
                // fit-to-one-page normally means this never triggers).
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 6);
            },
        ];
    }
}
