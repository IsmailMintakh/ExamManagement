<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Term-wise Result — {{ $schoolClass->name }} {{ $section->name }}</title>
<style>
    /* Single-page A3 landscape. Every dimension below is tuned so a
       roster of up to ~40 students + full stats footer + signature strip
       fits within the printable area without spilling to page 2. */
    @page { size: A3 landscape; margin: 5mm 6mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 8pt; color: #111; }
    .hdr { text-align: center; margin-bottom: 3px; }
    .hdr h1 { font-size: 13pt; font-weight: bold; letter-spacing: 0.5px; }
    .hdr h2 { font-size: 9pt; font-weight: bold; text-decoration: underline; margin-top: 1px; }
    .meta-row { display: table; width: 100%; margin: 2px 0 3px; font-size: 8.5pt; }
    .meta-l { display: table-cell; text-align: left; width: 60%; }
    .meta-r { display: table-cell; text-align: right; width: 40%; }
    .meta-r .cls-box { display: inline-block; border: 1px solid #333; padding: 1px 8px; font-weight: bold; margin-left: 4px; }

    table.data { width: 100%; border-collapse: collapse; page-break-inside: avoid; }
    table.data th, table.data td { border: 1px solid #333; text-align: center; padding: 0 2px; font-size: 7pt; line-height: 1; }
    table.data thead th { background: #f4f4f4; font-weight: bold; font-size: 6.5pt; padding: 1px 2px; }
    .subj-hdr { background: #f4f4f4; font-weight: bold; }
    .col-name { text-align: left !important; padding-left: 4px !important; font-weight: 500; white-space: nowrap; max-width: 90px; overflow: hidden; text-overflow: ellipsis; }
    .col-num { font-variant-numeric: tabular-nums; }
    .sub-total { background: #fff9c4; font-weight: bold; }
    .grand-total { background: #fff59d; font-weight: bold; }
    .grade { font-weight: bold; }
    .row-height td { height: 10px; }
    .remark-U { color: #b00; }
    .remark-P { color: #0a5c0a; }

    /* Bottom stats block */
    .footer-block { display: table; width: 100%; margin-top: 6px; table-layout: fixed; }
    .stats-left { display: table-cell; width: 22%; vertical-align: top; padding-right: 6px; }
    .stats-teachers { display: table-cell; width: 55%; vertical-align: top; padding: 0 6px; }
    .stats-grades { display: table-cell; width: 23%; vertical-align: top; padding-left: 6px; }
    table.mini { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
    table.mini th, table.mini td { border: 1px solid #333; padding: 2px 4px; text-align: center; }
    table.mini th { background: #eee; font-weight: bold; text-align: left; padding-left: 4px; }
    table.mini td.lbl { text-align: left; font-weight: bold; padding-left: 4px; }
    /* Stats-footer — sized to fit on one page. Row height increased a bit
       so rotated labels don't overflow into the signature strip below. */
    .stats-footer td { border: 1px solid #333; padding: 1px 3px; font-size: 7.5pt; text-align: center; height: 15px; }
    .stats-footer .sf-lbl { background: #fff; font-weight: bold; text-align: left; padding-left: 8px; }
    .stats-footer .sf-cls-val { background: #fff; font-weight: bold; text-align: center; color: #0a5c0a; }
    .stats-footer .sf-val { background: #fff; font-weight: bold; font-size: 7pt; }
    /* Teacher names + section labels are rendered as ROTATED text
       (transform: rotate(-90deg)). Text stays on one line and is rotated
       so it reads bottom-to-top. Font size tuned so the rotated text
       fits inside the cell height without spilling into signatures. */
    .stats-footer .sf-teacher {
        background: #fff;
        vertical-align: middle;
        text-align: center;
        padding: 2px 1px;
    }
    .stats-footer .sf-teacher span {
        display: inline-block;
        transform: rotate(-90deg);
        white-space: nowrap;
        font-weight: bold;
        font-size: 6.5pt;
    }
    .stats-footer .sf-empty { background: #fff; }
    /* Invisible cell — used for the LEFT block rows below "Pass%" so the
       class-summary section (Total / Appeared / Passed / Failed / Pass%)
       reads as a self-contained 5-row block, not extended down with
       empty gridded rows. */
    .stats-footer .sf-invis { background: transparent; border: 0; }
    /* "Average Percentage" — STRAIGHT horizontal 2-line label at the top
       of the section (matches reference sheet). Two words on two lines. */
    .stats-footer .sf-avgpct-label {
        background: #fff;
        vertical-align: top;
        text-align: center;
        padding: 3px 1px;
        font-weight: bold;
        font-size: 6.5pt;
        line-height: 1.15;
    }
    /* "Name of Sub. Teacher" — ROTATED vertical label (kept as-is). */
    .stats-footer .sf-vert {
        background: #fff;
        vertical-align: middle;
        text-align: center;
        padding: 2px 1px;
    }
    .stats-footer .sf-vert span {
        display: inline-block;
        transform: rotate(-90deg);
        white-space: nowrap;
        font-weight: bold;
        font-size: 6pt;
    }
    .stats-footer .sf-g-letter { background: #fff; font-weight: bold; }
    .stats-footer .sf-g-count  { background: #fff; font-weight: bold; }
    .stats-footer .sf-g-range  { background: #fff; text-align: left; padding-left: 4px; font-size: 7pt; }

    /* Signature strip — compact so it fits on the same page. */
    .sig-strip { display: table; width: 100%; margin-top: 5px; table-layout: fixed; page-break-inside: avoid; }
    .sig-cell { display: table-cell; width: 25%; text-align: center; padding: 0 6px; vertical-align: top; }
    .sig-label { font-size: 8.5pt; font-weight: bold; text-align: center; padding: 1px 0 2px; }
    .sig-box { border: 1px solid #333; height: 26px; padding: 1px; text-align: center; vertical-align: middle; }
    .sig-box img { max-height: 22px; max-width: 100%; object-fit: contain; }
</style>
</head>
<body>

<div class="hdr">
    <h1>SCHOOL EDUCATION DEPARTMENT DISTRICT {{ $school->address_district ?? '____________' }}</h1>
    <h2>Term-wise Result for the Academic Year {{ $academicSession?->name ?? now()->year }}</h2>
</div>

<div class="meta-row">
    <div class="meta-l">
        <b>INSTITUTION:</b>
        <span style="display:inline-block; border-bottom: 1px solid #333; min-width: 300px; padding: 0 6px;">
            {{ $school->name }}
        </span>
    </div>
    <div class="meta-r">
        <b>Class:</b>
        <span class="cls-box">{{ $schoolClass->name }}{{ $section->name ? ' - '.$section->name : '' }}</span>
    </div>
</div>

@php
    $subjectCount = count($subjects);
    // 5 fixed cols per subject (T-I, T-II, T-III, Total) — reference sheet
    // uses 4 sub-cols per subject (three terms + total).
    $subjCols = 4;
    // Trailing columns after all subjects: Co-Curr, Total O.M, %, Grade, Remarks
    $trailCols = 5;
    // Leading columns before subjects: S, Ad, Student Name, F. Name, DOB
    $leadCols = 5;
@endphp

<table class="data">
    <colgroup>
        <col style="width:22px">    {{-- S --}}
        <col style="width:36px">    {{-- Ad --}}
        <col style="width:120px">   {{-- Student Name --}}
        <col style="width:105px">   {{-- F. Name --}}
        <col style="width:60px">    {{-- DOB --}}
        @foreach($subjects as $s)
            <col><col><col><col>
        @endforeach
        <col style="width:32px">    {{-- Co-Curr --}}
        <col style="width:44px">    {{-- Total O.M --}}
        <col style="width:34px">    {{-- % --}}
        <col style="width:34px">    {{-- Grade --}}
        <col style="width:70px">    {{-- Remarks --}}
    </colgroup>
    <thead>
        <tr>
            <th rowspan="3">S</th>
            <th rowspan="3">Ad</th>
            <th rowspan="3">Student Name</th>
            <th rowspan="3">F. Name</th>
            <th rowspan="3">DOB</th>
            @foreach($subjects as $s)
                <th colspan="{{ $subjCols }}" class="subj-hdr">{{ $s['name'] }}</th>
            @endforeach
            <th rowspan="3">Co<br>Curr</th>
            <th rowspan="3">Total<br>O.M</th>
            <th rowspan="3">%</th>
            <th rowspan="3">Grade</th>
            <th rowspan="3">Remarks</th>
        </tr>
        <tr>
            @foreach($subjects as $s)
                <th>T-I</th><th>T-II</th><th>T-III</th><th>Total</th>
            @endforeach
        </tr>
        <tr>
            @foreach($subjects as $s)
                <th>{{ $termColTotal }}</th>
                <th>{{ $termColTotal }}</th>
                <th>{{ $termColTotal }}</th>
                <th>{{ $termColTotal * $termCount }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @if($rows->isEmpty())
            <tr>
                <td colspan="{{ 5 + (count($subjects) * 4) + 5 }}" style="padding:14px; text-align:center; background:#fff8e1; color:#8a5a00;">
                    <b>No students found for this section.</b><br>
                    <span style="font-size:7.5pt">
                        Add students to <b>{{ $schoolClass->name }} · {{ $section->name }}</b> in the Students module,
                        or make sure this section actually holds the primary students you expect.
                    </span>
                </td>
            </tr>
        @endif
        @if($rows->isNotEmpty() && count($subjects) === 0)
            <tr>
                <td colspan="{{ 5 + (count($subjects) * 4) + 5 }}" style="padding:14px; text-align:center; background:#ffebee; color:#a00;">
                    <b>No subjects mapped to this class on the exam.</b><br>
                    <span style="font-size:7.5pt">
                        Open <b>Exams → Edit → Subjects</b> and add the papers for {{ $schoolClass->name }} first,
                        then reload this report.
                    </span>
                </td>
            </tr>
        @endif
        @foreach($rows as $idx => $row)
            <tr class="row-height">
                <td class="col-num">{{ $idx + 1 }}</td>
                <td class="col-num">{{ $row['admission_no'] }}</td>
                <td class="col-name">{{ $row['name'] }}</td>
                <td class="col-name">{{ $row['father_name'] }}</td>
                <td class="col-num">{{ $row['dob'] }}</td>
                @foreach($row['subjects'] as $sr)
                    <td class="col-num">{{ $sr['t1'] !== null ? rtrim(rtrim(number_format($sr['t1'], 1), '0'), '.') : '' }}</td>
                    <td class="col-num">{{ $sr['t2'] !== null ? rtrim(rtrim(number_format($sr['t2'], 1), '0'), '.') : '' }}</td>
                    <td class="col-num">{{ $sr['t3'] !== null ? rtrim(rtrim(number_format($sr['t3'], 1), '0'), '.') : '' }}</td>
                    <td class="col-num sub-total">{{ rtrim(rtrim(number_format($sr['total'], 1), '0'), '.') }}</td>
                @endforeach
                <td class="col-num">{{ $row['co_curr'] !== null ? rtrim(rtrim(number_format($row['co_curr'], 1), '0'), '.') : '' }}</td>
                <td class="col-num grand-total">{{ rtrim(rtrim(number_format($row['grand_obtained'], 1), '0'), '.') }}</td>
                <td class="col-num">{{ number_format($row['percentage'], 2) }}</td>
                <td class="grade">{{ $row['grade'] }}</td>
                <td class="{{ $row['appeared'] && $row['percentage'] >= 40 ? 'remark-P' : 'remark-U' }}">{{ $row['remarks'] }}</td>
            </tr>
        @endforeach
    </tbody>
    {{-- ═══════════ TEACHER NAME ROW (in-table for perfect column alignment) ═══════════
         Sits in the same colgroup as every student row, so each teacher
         name lines up exactly under their subject columns above. The
         label spans the first 5 lead columns (S / Ad / Student / Father
         / DOB) and each teacher cell spans the same 4 columns as one
         subject header. The tail spans the aggregate columns. --}}
    {{-- ═══════════ IN-TABLE FOOTER — matches reference sheet ═══════════
           • Left  (colspan=4): metric label — Total / Appeared / Passed
             / Failed / Pass%. These describe the per-subject value shown
             on their row.
           • DOB col: vertical "Average Percentage" (rowspan=5) — section
             banner separating labels from per-subject values.
           • Per-subject (colspan=4 = one cell per subject spanning
             T-I/T-II/T-III/Total sub-columns): the metric value for
             THAT subject on THAT row.
           • Co-Curr: empty.
           • Right 4 cols: grade-distribution grid (11 rows). --}}
    <tfoot class="stats-footer">
        @php
            // Per-slot max for percentage math.
            $__termMax = max(1, $termColTotal);
            $__totalMax = max(1, $termColTotal * max(1, $termCount));
            $slots = ['t1', 't2', 't3', 'total'];

            // ── LEFT block: 5 whole-class label rows ────────────────
            // Kept as a self-contained class-summary section (label +
            // class-wide value). Same as before.
            $classSummary = [
                ['Total',    $summary['total_students'] ?? 0],
                ['Appeared', $summary['appeared']       ?? 0],
                ['Passed',   $summary['passed']         ?? 0],
                ['Failed',   $summary['failed']         ?? 0],
                ['Pass%',    number_format($summary['pass_percentage'] ?? 0, 1).'%'],
            ];

            // ── MIDDLE block: 2 rows only — Average + Percentage ────
            // Each renders per-subject × per-term values (4 cells per
            // subject).
            //   • Average    → subject's average marks (mean score across
            //                  students who appeared in this subject).
            //   • Percentage → passing percentage — share of students who
            //                  scored ≥40% of the subject's max in that
            //                  term. Pre-computed as `pass_percent` on
            //                  the controller.
            $avgFn = fn($stats) => $stats['average']      ?? 0;
            $pctFn = fn($stats) => ($stats['pass_percent'] ?? 0).'%';
            $classCombinedPct = number_format($summary['average_percentage'] ?? 0, 1);
            $subjPerTermCols = count($subjects) * $subjCols;   // total subject columns
        @endphp

        {{-- ── Row 1: Total (class) | Average (label) | per-subject × per-term avg | Co-Curr | A++ ── --}}
        @php $gb = $gradeBands[0] ?? null; @endphp
        <tr>
            <td colspan="3" class="sf-lbl">{{ $classSummary[0][0] }}</td>
            <td class="sf-cls-val">{{ $classSummary[0][1] }}</td>
            <td class="sf-avgpct-label">Average</td>
            @foreach($subjects as $s)
                @foreach($slots as $slot)
                    <td class="sf-val">{{ $avgFn($s['stats'][$slot] ?? []) }}</td>
                @endforeach
            @endforeach
            <td class="sf-empty">&nbsp;</td>
            <td class="sf-g-letter">{{ $gb['grade'] ?? '' }}</td>
            <td class="sf-g-count">{{ $gradeCounts[$gb['grade'] ?? ''] ?? 0 }}</td>
            <td colspan="2" class="sf-g-range">{{ $gb['label'] ?? '' }}</td>
        </tr>

        {{-- ── Row 2: Appeared (class) | Percentage (label) | per-subject × per-term % | Co-Curr | A+ ── --}}
        @php $gb = $gradeBands[1] ?? null; @endphp
        <tr>
            <td colspan="3" class="sf-lbl">{{ $classSummary[1][0] }}</td>
            <td class="sf-cls-val">{{ $classSummary[1][1] }}</td>
            <td class="sf-avgpct-label">Percentage</td>
            @foreach($subjects as $s)
                @foreach($slots as $slot)
                    <td class="sf-val">{{ $pctFn($s['stats'][$slot] ?? []) }}</td>
                @endforeach
            @endforeach
            <td class="sf-empty">&nbsp;</td>
            <td class="sf-g-letter">{{ $gb['grade'] ?? '' }}</td>
            <td class="sf-g-count">{{ $gradeCounts[$gb['grade'] ?? ''] ?? 0 }}</td>
            <td colspan="2" class="sf-g-range">{{ $gb['label'] ?? '' }}</td>
        </tr>

        {{-- ── Row 3: Passed (class) | Name-of-Sub.-Teacher rowspan=9 | teacher × per-term rowspan=9 | Co-Curr | A ── --}}
        @php $gb = $gradeBands[2] ?? null; @endphp
        <tr>
            <td colspan="3" class="sf-lbl">{{ $classSummary[2][0] }}</td>
            <td class="sf-cls-val">{{ $classSummary[2][1] }}</td>
            <td rowspan="9" class="sf-vert"><span>Name of Sub. Teacher</span></td>
            @foreach($subjects as $s)
                @php $perTerm = $s['teachers'] ?? null; @endphp
                @foreach($slots as $slot)
                    @php
                        $tname = $perTerm[$slot] ?? null;
                        if ($tname === null && $slot === 't1') $tname = $s['teacher'] ?? '';
                    @endphp
                    <td rowspan="9" class="sf-teacher">
                        @if($tname)<span>{{ $tname }}</span>@endif
                    </td>
                @endforeach
            @endforeach
            <td class="sf-empty">&nbsp;</td>
            <td class="sf-g-letter">{{ $gb['grade'] ?? '' }}</td>
            <td class="sf-g-count">{{ $gradeCounts[$gb['grade'] ?? ''] ?? 0 }}</td>
            <td colspan="2" class="sf-g-range">{{ $gb['label'] ?? '' }}</td>
        </tr>

        {{-- ── Rows 4-5: Failed, Pass% (class values on left) | teacher continues via rowspan | grade band ── --}}
        @foreach([3, 4] as $mi)
            @php $gb = $gradeBands[$mi] ?? null; @endphp
            <tr>
                <td colspan="3" class="sf-lbl">{{ $classSummary[$mi][0] }}</td>
                <td class="sf-cls-val">{{ $classSummary[$mi][1] }}</td>
                <td class="sf-empty">&nbsp;</td>
                <td class="sf-g-letter">{{ $gb['grade'] ?? '' }}</td>
                <td class="sf-g-count">{{ $gradeCounts[$gb['grade'] ?? ''] ?? 0 }}</td>
                <td colspan="2" class="sf-g-range">{{ $gb['label'] ?? '' }}</td>
            </tr>
        @endforeach
        {{-- Rows 6-10: LEFT block ended at Pass% (row 5) — everything
             below is invisible on the left. Teacher section (row 3 rowspan
             = 9) covers the middle. Just grade bands remain on the right. --}}
        @foreach([5, 6, 7, 8, 9] as $bandIdx)
            @php $gb = $gradeBands[$bandIdx] ?? null; @endphp
            <tr>
                <td colspan="4" class="sf-invis">&nbsp;</td>
                <td class="sf-invis">&nbsp;</td>
                <td class="sf-g-letter">{{ $gb['grade'] ?? '' }}</td>
                <td class="sf-g-count">{{ $gradeCounts[$gb['grade'] ?? ''] ?? 0 }}</td>
                <td colspan="2" class="sf-g-range">{{ $gb['label'] ?? '' }}</td>
            </tr>
        @endforeach

        {{-- Row 11: Total row of the grade table --}}
        <tr>
            <td colspan="4" class="sf-invis">&nbsp;</td>
            <td class="sf-invis">&nbsp;</td>
            <td class="sf-g-letter">Total</td>
            <td class="sf-g-count">{{ array_sum($gradeCounts) }}</td>
            <td colspan="2" class="sf-invis">&nbsp;</td>
        </tr>
    </tfoot>
</table>

{{-- All summary/grade/teacher content lives inside the main table's
     <tfoot> — see above. Only the signature strip follows. --}}

<div class="sig-strip">
    @php
        $prepared    = $signatures['prepared_by']     ?? ['name' => null, 'path' => null];
        $classTeach  = $signatures['class_teacher']   ?? ['name' => $classTeacher, 'path' => null];
        $hmDdo       = $signatures['hm_ddo']          ?? ['name' => null, 'path' => null];
        $counter     = $signatures['counter_signed']  ?? ['name' => null, 'path' => null];
    @endphp
    <div class="sig-cell">
        {{-- "Prepared By" — no name printed; whoever prepared it signs the box. --}}
        <div class="sig-label">Prepared By</div>
        <div class="sig-box">
            @if(!empty($prepared['path']) && file_exists($prepared['path']))
                <img src="{{ $prepared['path'] }}" alt="">
            @endif
        </div>
    </div>
    <div class="sig-cell">
        <div class="sig-label">Checked By ( ClassTr){{ $classTeach['name'] ? ' — '.$classTeach['name'] : '' }}</div>
        <div class="sig-box">
            @if(!empty($classTeach['path']) && file_exists($classTeach['path']))
                <img src="{{ $classTeach['path'] }}" alt="">
            @endif
        </div>
    </div>
    <div class="sig-cell">
        <div class="sig-label">Signature of HM/DDO{{ $hmDdo['name'] ? ' — '.$hmDdo['name'] : '' }}</div>
        <div class="sig-box">
            @if(!empty($hmDdo['path']) && file_exists($hmDdo['path']))
                <img src="{{ $hmDdo['path'] }}" alt="">
            @endif
        </div>
    </div>
    <div class="sig-cell">
        {{-- "Counter Signed by" — no name printed; whoever counter-signs
             writes their own name/designation in the box. --}}
        <div class="sig-label">Counter Signed by</div>
        <div class="sig-box">
            @if(!empty($counter['path']) && file_exists($counter['path']))
                <img src="{{ $counter['path'] }}" alt="">
            @endif
        </div>
    </div>
</div>

</body>
</html>
