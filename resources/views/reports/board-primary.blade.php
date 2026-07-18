<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Term-wise Result — {{ $schoolClass->name }} {{ $section->name }}</title>
<style>
    @page { size: A3 landscape; margin: 8mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 8pt; color: #111; }
    .hdr { text-align: center; margin-bottom: 6px; }
    .hdr h1 { font-size: 15pt; font-weight: bold; letter-spacing: 0.5px; }
    .hdr h2 { font-size: 10pt; font-weight: bold; text-decoration: underline; margin-top: 2px; }
    .meta-row { display: table; width: 100%; margin: 4px 0 6px; font-size: 9pt; }
    .meta-l { display: table-cell; text-align: left; width: 60%; }
    .meta-r { display: table-cell; text-align: right; width: 40%; }
    .meta-r .cls-box { display: inline-block; border: 1px solid #333; padding: 1px 8px; font-weight: bold; margin-left: 4px; }

    table.data { width: 100%; border-collapse: collapse; }
    table.data th, table.data td { border: 1px solid #333; text-align: center; padding: 1px 2px; font-size: 7.5pt; line-height: 1.05; }
    table.data thead th { background: #f4f4f4; font-weight: bold; font-size: 7pt; }
    .subj-hdr { background: #f4f4f4; font-weight: bold; }
    .col-name { text-align: left !important; padding-left: 4px !important; font-weight: 500; white-space: nowrap; max-width: 90px; overflow: hidden; text-overflow: ellipsis; }
    .col-num { font-variant-numeric: tabular-nums; }
    .sub-total { background: #fff9c4; font-weight: bold; }
    .grand-total { background: #fff59d; font-weight: bold; }
    .grade { font-weight: bold; }
    .row-height td { height: 12px; }
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

    /* Signature strip */
    .sig-strip { display: table; width: 100%; margin-top: 10px; }
    .sig-cell { display: table-cell; width: 25%; text-align: center; padding: 0 4px; vertical-align: top; }
    .sig-line { border-top: 1px solid #333; padding-top: 2px; font-size: 8pt; font-weight: bold; }
    .sig-box { height: 24px; }
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
</table>

<div class="footer-block">
    {{-- Summary stats --}}
    <div class="stats-left">
        <table class="mini">
            <tr><td class="lbl">Average</td><td>{{ number_format($summary['average_percentage'], 2) }}</td></tr>
            <tr><td class="lbl">Total Percentage</td><td>{{ number_format($summary['average_percentage'], 2) }}</td></tr>
            <tr><td class="lbl">Appeared</td><td>{{ $summary['appeared'] }}</td></tr>
            <tr><td class="lbl">Passed</td><td>{{ $summary['passed'] }}</td></tr>
            <tr><td class="lbl">Failed</td><td>{{ $summary['failed'] }}</td></tr>
            <tr><td class="lbl">Pass %</td><td>{{ number_format($summary['pass_percentage'], 2) }}%</td></tr>
        </table>
    </div>

    {{-- Subject-teacher name row —
         one column per subject so the HM/DDO can see which teacher owns
         which paper. Names come from SubjectTeacher rows scoped to this
         section (with a class-wide fallback when no section-specific
         assignment exists). --}}
    <div class="stats-teachers">
        <table class="mini">
            <thead>
                <tr>
                    <th style="text-align:center; width:100px">Name of Sub. Teacher</th>
                    @foreach($subjects as $s)
                        <th style="text-align:center">{{ $s['name'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="lbl">Teacher</td>
                    @foreach($subjects as $s)
                        <td style="font-size:7pt">
                            {{ $s['teacher'] ?: '—' }}
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>

    {{-- Grade distribution --}}
    <div class="stats-grades">
        <table class="mini">
            <thead>
                <tr><th style="width:24px">Grade</th><th style="width:26px">#</th><th>Range</th></tr>
            </thead>
            <tbody>
                @foreach($gradeBands as $b)
                    <tr>
                        <td class="lbl" style="text-align:center">{{ $b['grade'] }}</td>
                        <td class="col-num">{{ $gradeCounts[$b['grade']] ?? 0 }}</td>
                        <td>{{ $b['label'] }}</td>
                    </tr>
                @endforeach
                <tr style="background:#f4f4f4">
                    <td class="lbl" style="text-align:center">Total</td>
                    <td class="col-num">{{ array_sum($gradeCounts) }}</td>
                    <td>&nbsp;</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="sig-strip">
    <div class="sig-cell">
        <div class="sig-box"></div>
        <div class="sig-line">Prepared By</div>
    </div>
    <div class="sig-cell">
        <div class="sig-box"></div>
        <div class="sig-line">Checked By (Class Tr){{ $classTeacher ? ' — '.$classTeacher : '' }}</div>
    </div>
    <div class="sig-cell">
        <div class="sig-box"></div>
        <div class="sig-line">Signature of HM/DDO</div>
    </div>
    <div class="sig-cell">
        <div class="sig-box"></div>
        <div class="sig-line">Counter Signed by</div>
    </div>
</div>

</body>
</html>
