<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Board Result — {{ $result->student->name }}</title>
<style>
    @page { size: A4 portrait; margin: 14mm 12mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10pt; color: #111; }

    /* ─── Header ─── */
    .header { text-align: center; padding-bottom: 8px; border-bottom: 2px solid #111; margin-bottom: 12px; }
    .school-name { font-size: 15pt; font-weight: bold; letter-spacing: 0.4px; }
    .school-meta { font-size: 9pt; color: #555; margin-top: 2px; }
    .doc-title { display: inline-block; margin-top: 8px; padding: 3px 12px;
                 border: 1.5px solid #111; font-weight: bold; font-size: 11pt; letter-spacing: 0.5px; }

    /* ─── Student info block ─── */
    .info { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 9.5pt; }
    .info td { padding: 3px 6px; border: 1px solid #333; }
    .info td.k { background: #f2f4f7; font-weight: bold; width: 22%; }
    .info td.v { width: 28%; }

    /* ─── Subjects table ─── */
    table.subj { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.subj th, table.subj td { border: 1px solid #333; padding: 4px 5px; font-size: 9pt; text-align: center; }
    table.subj thead th { background: #eef2ff; font-weight: bold; font-size: 8.5pt; }
    table.subj td.name { text-align: left; font-weight: 600; }
    .grade-pill { display: inline-block; padding: 1px 5px; border-radius: 3px; font-weight: bold; font-size: 8.5pt; }
    .g-a1, .g-a { background: #d1fae5; color: #065f46; }
    .g-b     { background: #dbeafe; color: #1e3a8a; }
    .g-c, .g-d { background: #fef3c7; color: #78350f; }
    .g-e     { background: #ffedd5; color: #7c2d12; }
    .g-f     { background: #fee2e2; color: #7f1d1d; }

    /* ─── Aggregate summary ─── */
    .aggregate { display: table; width: 100%; margin-bottom: 12px; border-collapse: collapse; }
    .aggregate .cell { display: table-cell; width: 20%; padding: 8px 6px; border: 1px solid #333;
                       text-align: center; vertical-align: middle; }
    .aggregate .lbl { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px; color: #666; font-weight: bold; }
    .aggregate .val { font-size: 13pt; font-weight: bold; margin-top: 2px; }

    .result-banner { text-align: center; padding: 8px; border: 2px solid; border-radius: 4px;
                     font-size: 12pt; font-weight: bold; margin-bottom: 12px; }
    .r-pass    { border-color: #065f46; color: #065f46; background: #ecfdf5; }
    .r-supply  { border-color: #075985; color: #075985; background: #f0f9ff; }
    .r-fail    { border-color: #7f1d1d; color: #7f1d1d; background: #fef2f2; }

    /* ─── Signature strip ─── */
    .sig { display: table; width: 100%; margin-top: 24px; }
    .sig .cell { display: table-cell; width: 33.33%; text-align: center; padding: 0 8px; vertical-align: bottom; }
    .sig .line { border-top: 1px solid #111; padding-top: 3px; font-size: 9pt; font-weight: bold; }
    .footer-note { font-size: 8pt; color: #666; text-align: center; margin-top: 16px; font-style: italic; }
</style>
</head>
<body>

@php
    $gradeCls = fn ($g) => 'g-'.strtolower(($g ?? 'x')[0]);
    $s = $result->student;
    // Sort subject rows by name for consistent ordering across cards.
    $subjects = $result->subjects->sortBy(fn ($r) => $r->subject?->name)->values();
@endphp

<!-- ═══════════ HEADER ═══════════ -->
<div class="header">
    <div class="school-name">{{ strtoupper($exam->school->name) }}</div>
    <div class="school-meta">
        @if($exam->school->address_district) District {{ $exam->school->address_district }} · @endif
        Academic Session {{ $exam->academicSession?->name }}
    </div>
    <div class="doc-title">FBISE {{ $exam->level }} · RESULT CARD</div>
</div>

<!-- ═══════════ STUDENT INFO ═══════════ -->
<table class="info">
    <tr>
        <td class="k">Student Name</td><td class="v">{{ $s->name }}</td>
        <td class="k">Board Roll</td><td class="v">{{ $result->board_roll_no ?: '—' }}</td>
    </tr>
    <tr>
        <td class="k">Father's Name</td><td class="v">{{ $s->father_name }}</td>
        <td class="k">Admission #</td><td class="v">{{ $s->admission_no ?: '—' }}</td>
    </tr>
    <tr>
        <td class="k">Class</td><td class="v">{{ $exam->schoolClass?->name }}</td>
        <td class="k">Class Roll</td><td class="v">{{ $s->roll_no ?: '—' }}</td>
    </tr>
    <tr>
        <td class="k">Date of Birth</td>
        <td class="v">{{ $s->date_of_birth ? \Carbon\Carbon::parse($s->date_of_birth)->format('d M Y') : '—' }}</td>
        <td class="k">Exam Title</td><td class="v">{{ $exam->title }}</td>
    </tr>
</table>

<!-- ═══════════ SUBJECTS TABLE ═══════════ -->
<table class="subj">
    <thead>
        <tr>
            <th style="width: 6%;">#</th>
            <th style="width: 30%;">Subject</th>
            <th style="width: 12%;">Theory<br><span style="font-weight:normal; color:#666;">Marks / Max</span></th>
            <th style="width: 12%;">Practical<br><span style="font-weight:normal; color:#666;">Marks / Max</span></th>
            <th style="width: 12%;">Total<br><span style="font-weight:normal; color:#666;">Obt / Max</span></th>
            <th style="width: 12%;">%</th>
            <th style="width: 8%;">Grade</th>
            <th style="width: 8%;">Result</th>
        </tr>
    </thead>
    <tbody>
        @foreach($subjects as $i => $sub)
            @php
                $tot   = (float) $sub->total_marks;
                $max   = (float) $sub->max_marks;
                $pct   = $max > 0 ? round(($tot / $max) * 100, 1) : 0;
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="name">{{ $sub->subject?->name }}</td>
                <td>{{ rtrim(rtrim(number_format($sub->theory_marks, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($sub->theory_max, 2), '0'), '.') }}</td>
                <td>{{ rtrim(rtrim(number_format($sub->practical_marks, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($sub->practical_max, 2), '0'), '.') }}</td>
                <td><b>{{ rtrim(rtrim(number_format($tot, 2), '0'), '.') }}</b> / {{ rtrim(rtrim(number_format($max, 2), '0'), '.') }}</td>
                <td>{{ $pct }}%</td>
                <td><span class="grade-pill {{ $gradeCls($sub->grade) }}">{{ $sub->grade }}</span></td>
                <td>{{ $sub->is_absent ? 'Absent' : ($sub->is_pass ? 'Pass' : 'Fail') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<!-- ═══════════ AGGREGATE ═══════════ -->
<div class="aggregate">
    <div class="cell">
        <div class="lbl">Obtained</div>
        <div class="val">{{ rtrim(rtrim(number_format($result->total_obtained, 2), '0'), '.') }}</div>
    </div>
    <div class="cell">
        <div class="lbl">Out of</div>
        <div class="val">{{ rtrim(rtrim(number_format($result->total_max, 2), '0'), '.') }}</div>
    </div>
    <div class="cell">
        <div class="lbl">Percentage</div>
        <div class="val">{{ number_format($result->percentage, 2) }}%</div>
    </div>
    <div class="cell">
        <div class="lbl">Grade</div>
        <div class="val"><span class="grade-pill {{ $gradeCls($result->grade) }}">{{ $result->grade }}</span></div>
    </div>
    <div class="cell">
        <div class="lbl">Position</div>
        <div class="val">{{ $result->position ? '#'.$result->position : '—' }}</div>
    </div>
</div>

<!-- ═══════════ RESULT BANNER ═══════════ -->
@php
    $resultCls = $result->is_pass ? 'r-pass' : ($result->is_supplementary ? 'r-supply' : 'r-fail');
    $resultLbl = $result->is_pass
        ? 'PASSED · '.strtoupper($result->division).' Division'
        : ($result->is_supplementary ? 'SUPPLEMENTARY' : 'NOT PASSED');
@endphp
<div class="result-banner {{ $resultCls }}">{{ $resultLbl }}</div>

@if($result->remarks)
    <div style="font-size:9pt; padding:6px 10px; border:1px dashed #999; margin-bottom:12px;">
        <b>Remarks:</b> {{ $result->remarks }}
    </div>
@endif

<!-- ═══════════ SIGNATURES ═══════════ -->
<div class="sig">
    <div class="cell">
        <div style="height:36px;"></div>
        <div class="line">Class Teacher</div>
    </div>
    <div class="cell">
        <div style="height:36px;"></div>
        <div class="line">Examination Officer{{ $exam->school->exam_officer_name ? ' — '.$exam->school->exam_officer_name : '' }}</div>
    </div>
    <div class="cell">
        <div style="height:36px;"></div>
        <div class="line">Principal{{ $exam->school->principal_name ? ' — '.$exam->school->principal_name : '' }}</div>
    </div>
</div>

<div class="footer-note">
    Computer-generated. This is an internal record — the original FBISE result gazette remains authoritative.
</div>

</body>
</html>
