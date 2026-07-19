{{--
    Excel version of the board-primary term-wise result.

    IMPORTANT: PhpSpreadsheet (via maatwebsite FromView) parses each
    top-level <table> as its OWN column-width island — mixing them breaks
    alignment. So everything (title, header, student rows, summary,
    teacher row, signature row) lives inside ONE table with a consistent
    total-column count and colspans elsewhere.

    Column layout (total cols = 5 + subjects*4 + 5 = "cols"):
      S | Ad | Student Name | F. Name | DOB | [T-I T-II T-III Total]×N | Co Curr | Total O.M | % | Grade | Remarks
--}}
@php
    $subjectCount = count($subjects);
    $totalCols    = 5 + ($subjectCount * 4) + 5;
    $co    = fn ($n) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($n);
@endphp

<table>
    {{-- ═══════════ TITLE BLOCK ═══════════ --}}
    <tr>
        <th colspan="{{ $totalCols }}" style="text-align:center; font-size:14pt; font-weight:bold;">
            SCHOOL EDUCATION DEPARTMENT DISTRICT ____________
        </th>
    </tr>
    <tr>
        <th colspan="{{ $totalCols }}" style="text-align:center; font-size:11pt; font-weight:bold;">
            Term-wise Result for the Academic Year {{ $academicSession?->name ?? now()->year }}
        </th>
    </tr>
    <tr>
        <td colspan="{{ (int) ceil($totalCols / 2) }}" style="font-weight:bold; text-align:left;">
            INSTITUTION: {{ $school->name }}
        </td>
        <td colspan="{{ $totalCols - (int) ceil($totalCols / 2) }}" style="font-weight:bold; text-align:right;">
            Class: {{ $schoolClass->name }}{{ $section?->name ? ' - '.$section->name : '' }}
        </td>
    </tr>

    {{-- ═══════════ HEADER ROWS (3-level, mirrors the PDF) ═══════════ --}}
    <tr style="font-weight:bold; text-align:center;">
        <th rowspan="3">S</th>
        <th rowspan="3">Ad</th>
        <th rowspan="3">Student Name</th>
        <th rowspan="3">F. Name</th>
        <th rowspan="3">DOB</th>
        @foreach($subjects as $s)
            <th colspan="4">{{ $s['name'] }}</th>
        @endforeach
        <th rowspan="3">Co-<br/>Curr</th>
        <th rowspan="3">Total<br/>O.M</th>
        <th rowspan="3">%</th>
        <th rowspan="3">Grade</th>
        <th rowspan="3">Remarks</th>
    </tr>
    <tr style="font-weight:bold; text-align:center;">
        @foreach($subjects as $s)
            <th>T-I</th><th>T-II</th><th>T-III</th><th>Total</th>
        @endforeach
    </tr>
    <tr style="font-weight:bold; text-align:center;">
        @foreach($subjects as $s)
            <th>{{ $termColTotal }}</th>
            <th>{{ $termColTotal }}</th>
            <th>{{ $termColTotal }}</th>
            <th>{{ $termColTotal * $termCount }}</th>
        @endforeach
    </tr>

    {{-- ═══════════ STUDENT ROWS ═══════════ --}}
    @if($rows->isEmpty())
        <tr>
            <td colspan="{{ $totalCols }}" style="text-align:center;">
                No students found for this section.
            </td>
        </tr>
    @endif
    @foreach($rows as $idx => $row)
        <tr>
            <td style="text-align:center;">{{ $idx + 1 }}</td>
            <td style="text-align:center;">{{ $row['admission_no'] }}</td>
            <td style="text-align:left;">{{ $row['name'] }}</td>
            <td style="text-align:left;">{{ $row['father_name'] }}</td>
            <td style="text-align:center;">{{ $row['dob'] }}</td>
            @foreach($row['subjects'] as $sr)
                <td style="text-align:center;">{{ $sr['t1'] !== null ? $sr['t1'] : '' }}</td>
                <td style="text-align:center;">{{ $sr['t2'] !== null ? $sr['t2'] : '' }}</td>
                <td style="text-align:center;">{{ $sr['t3'] !== null ? $sr['t3'] : '' }}</td>
                <td style="text-align:center; font-weight:bold;">{{ $sr['total'] !== null ? $sr['total'] : '' }}</td>
            @endforeach
            <td style="text-align:center;">{{ $row['co_curr'] !== null ? $row['co_curr'] : '' }}</td>
            <td style="text-align:center; font-weight:bold;">{{ $row['grand_obtained'] }}</td>
            <td style="text-align:center;">{{ number_format($row['percentage'], 2) }}</td>
            <td style="text-align:center; font-weight:bold;">{{ $row['grade'] }}</td>
            <td style="text-align:left;">{{ $row['remarks'] }}</td>
        </tr>
    @endforeach

    {{-- ═══════════ STATS FOOTER — mirrors the PDF layout ═══════════
         Structure (11 rows total, aligned with grade table on the right):
           Row 1: [Total | val] [Average lbl] [per-subj × per-term avg]   [—] [A++]
           Row 2: [Appeared | val] [Percentage lbl] [per-subj × per-term %] [—] [A+]
           Row 3-11: teacher block (NoST + teachers) on the left/middle
                     with grade bands A / B++ / B+ / B / C / D / E / U / Total
                     on the right. Left is invisible below row 5. --}}
    @php
        $slots = ['t1', 't2', 't3', 'total'];
        $classSummary = [
            ['Total',    $summary['total_students'] ?? 0],
            ['Appeared', $summary['appeared']       ?? 0],
            ['Passed',   $summary['passed']         ?? 0],
            ['Failed',   $summary['failed']         ?? 0],
            ['Pass %',   number_format($summary['pass_percentage'] ?? 0, 1).'%'],
        ];
    @endphp

    {{-- Row 1: Total + Average --}}
    @php $gb = $gradeBands[0] ?? null; @endphp
    <tr>
        <td colspan="3" style="text-align:left; font-weight:bold; padding-left:6px;">{{ $classSummary[0][0] }}</td>
        <td style="text-align:center; font-weight:bold;">{{ $classSummary[0][1] }}</td>
        <td style="text-align:center; font-weight:bold;">Average</td>
        @foreach($subjects as $s)
            @foreach($slots as $slot)
                <td style="text-align:center;">{{ $s['stats'][$slot]['average'] ?? 0 }}</td>
            @endforeach
        @endforeach
        <td>&nbsp;</td>
        <td style="text-align:center; font-weight:bold;">{{ $gb['grade'] ?? '' }}</td>
        <td style="text-align:center; font-weight:bold;">{{ $gradeCounts[$gb['grade'] ?? ''] ?? 0 }}</td>
        <td colspan="2" style="text-align:left; padding-left:4px;">{{ $gb['label'] ?? '' }}</td>
    </tr>

    {{-- Row 2: Appeared + Percentage --}}
    @php $gb = $gradeBands[1] ?? null; @endphp
    <tr>
        <td colspan="3" style="text-align:left; font-weight:bold; padding-left:6px;">{{ $classSummary[1][0] }}</td>
        <td style="text-align:center; font-weight:bold;">{{ $classSummary[1][1] }}</td>
        <td style="text-align:center; font-weight:bold;">Percentage</td>
        @foreach($subjects as $s)
            @foreach($slots as $slot)
                <td style="text-align:center;">{{ ($s['stats'][$slot]['pass_percent'] ?? 0) }}%</td>
            @endforeach
        @endforeach
        <td>&nbsp;</td>
        <td style="text-align:center; font-weight:bold;">{{ $gb['grade'] ?? '' }}</td>
        <td style="text-align:center; font-weight:bold;">{{ $gradeCounts[$gb['grade'] ?? ''] ?? 0 }}</td>
        <td colspan="2" style="text-align:left; padding-left:4px;">{{ $gb['label'] ?? '' }}</td>
    </tr>

    {{-- Row 3: Passed + Name of Sub. Teacher (rowspan=9) + teacher × per-term (rowspan=9) --}}
    @php $gb = $gradeBands[2] ?? null; @endphp
    <tr>
        <td colspan="3" style="text-align:left; font-weight:bold; padding-left:6px;">{{ $classSummary[2][0] }}</td>
        <td style="text-align:center; font-weight:bold;">{{ $classSummary[2][1] }}</td>
        <td rowspan="9" style="text-align:center; font-weight:bold;">Name of Sub. Teacher</td>
        @foreach($subjects as $s)
            @php $perTerm = $s['teachers'] ?? null; @endphp
            @foreach($slots as $slot)
                @php
                    $tname = $perTerm[$slot] ?? null;
                    if ($tname === null && $slot === 't1') $tname = $s['teacher'] ?? '';
                @endphp
                <td rowspan="9" style="text-align:center; font-weight:bold;">{{ $tname }}</td>
            @endforeach
        @endforeach
        <td>&nbsp;</td>
        <td style="text-align:center; font-weight:bold;">{{ $gb['grade'] ?? '' }}</td>
        <td style="text-align:center; font-weight:bold;">{{ $gradeCounts[$gb['grade'] ?? ''] ?? 0 }}</td>
        <td colspan="2" style="text-align:left; padding-left:4px;">{{ $gb['label'] ?? '' }}</td>
    </tr>

    {{-- Rows 4-5: Failed, Pass% (left) + teacher continues via rowspan + grade band --}}
    @foreach([3, 4] as $mi)
        @php $gb = $gradeBands[$mi] ?? null; @endphp
        <tr>
            <td colspan="3" style="text-align:left; font-weight:bold; padding-left:6px;">{{ $classSummary[$mi][0] }}</td>
            <td style="text-align:center; font-weight:bold;">{{ $classSummary[$mi][1] }}</td>
            <td>&nbsp;</td>
            <td style="text-align:center; font-weight:bold;">{{ $gb['grade'] ?? '' }}</td>
            <td style="text-align:center; font-weight:bold;">{{ $gradeCounts[$gb['grade'] ?? ''] ?? 0 }}</td>
            <td colspan="2" style="text-align:left; padding-left:4px;">{{ $gb['label'] ?? '' }}</td>
        </tr>
    @endforeach

    {{-- Rows 6-10: left invisible + Co-Curr + grade bands B / C / D / E / U --}}
    @foreach([5, 6, 7, 8, 9] as $bandIdx)
        @php $gb = $gradeBands[$bandIdx] ?? null; @endphp
        <tr>
            <td colspan="3">&nbsp;</td>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td style="text-align:center; font-weight:bold;">{{ $gb['grade'] ?? '' }}</td>
            <td style="text-align:center; font-weight:bold;">{{ $gradeCounts[$gb['grade'] ?? ''] ?? 0 }}</td>
            <td colspan="2" style="text-align:left; padding-left:4px;">{{ $gb['label'] ?? '' }}</td>
        </tr>
    @endforeach

    {{-- Row 11: Total grade row --}}
    <tr>
        <td colspan="3">&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td style="text-align:center; font-weight:bold;">Total</td>
        <td style="text-align:center; font-weight:bold;">{{ array_sum($gradeCounts) }}</td>
        <td colspan="2">&nbsp;</td>
    </tr>

    {{-- Spacer row --}}
    <tr><td colspan="{{ $totalCols }}">&nbsp;</td></tr>

    {{-- ═══════════ SIGNATURE STRIP ═══════════ --}}
    @php
        $sigCols = 4;
        $sigCellSpan = (int) floor($totalCols / $sigCols);
        $sigLastSpan = $totalCols - ($sigCellSpan * ($sigCols - 1));
    @endphp
    @php
        $preparedName = $signatures['prepared_by']['name']     ?? '';
        $ctName       = $signatures['class_teacher']['name']   ?? $classTeacher ?? '';
        $hmName       = $signatures['hm_ddo']['name']          ?? '';
        $ccName       = $signatures['counter_signed']['name']  ?? '';
    @endphp
    <tr style="font-weight:bold; text-align:center;">
        {{-- Prepared By / Counter Signed by intentionally omit the name —
             those roles sign the box themselves. Class Teacher and HM/DDO
             names are kept as the accountable persons for the report. --}}
        <th colspan="{{ $sigCellSpan }}">Prepared By</th>
        <th colspan="{{ $sigCellSpan }}">Checked By (Class Tr){{ $ctName ? ' — '.$ctName : '' }}</th>
        <th colspan="{{ $sigCellSpan }}">Signature of HM/DDO{{ $hmName ? ' — '.$hmName : '' }}</th>
        <th colspan="{{ $sigLastSpan }}">Counter Signed by</th>
    </tr>
    <tr>
        {{-- Excel doesn't render image data URIs cleanly from HTML;
             the signature name in the header row above is the practical
             substitute. Empty box below is where a printed sheet gets
             signed by hand. --}}
        <td colspan="{{ $sigCellSpan }}" style="height:40px;">&nbsp;</td>
        <td colspan="{{ $sigCellSpan }}">&nbsp;</td>
        <td colspan="{{ $sigCellSpan }}">&nbsp;</td>
        <td colspan="{{ $sigLastSpan }}">&nbsp;</td>
    </tr>
</table>
