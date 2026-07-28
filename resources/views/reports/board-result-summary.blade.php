<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Board Result — {{ $exam->title }}</title>
<style>
    @page { size: A3 landscape; margin: 8mm 6mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 8pt; color: #111; }

    .hdr { text-align: center; padding-bottom: 5px; border-bottom: 1.5px solid #111; margin-bottom: 6px; }
    .hdr h1 { font-size: 13pt; font-weight: bold; }
    .hdr h2 { font-size: 9.5pt; font-weight: bold; margin-top: 2px; }
    .hdr .meta { font-size: 8.5pt; color: #555; margin-top: 2px; }

    /* Stats strip */
    .stats { display: table; width: 100%; margin-bottom: 5px; table-layout: fixed; border-collapse: collapse; }
    .stats .cell { display: table-cell; padding: 4px 5px; border: 1px solid #333; text-align: center; }
    .stats .lbl { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.5px; color: #666; font-weight: bold; }
    .stats .val { font-size: 11pt; font-weight: bold; margin-top: 1px; }

    /* Main table */
    table.data { width: 100%; border-collapse: collapse; page-break-inside: auto; }
    table.data th, table.data td { border: 1px solid #333; padding: 1px 3px; font-size: 7pt; text-align: center; }
    table.data thead th { background: #eef2ff; font-weight: bold; font-size: 6.5pt; }
    table.data td.name { text-align: left; font-weight: 500; }
    tr { page-break-inside: avoid; }

    .grade-pill { display: inline-block; padding: 0 3px; border-radius: 2px; font-weight: bold; font-size: 6.5pt; }
    .g-a1, .g-a { background: #d1fae5; color: #065f46; }
    .g-b     { background: #dbeafe; color: #1e3a8a; }
    .g-c, .g-d { background: #fef3c7; color: #78350f; }
    .g-e     { background: #ffedd5; color: #7c2d12; }
    .g-f     { background: #fee2e2; color: #7f1d1d; }

    .result-pass    { color: #065f46; font-weight: bold; }
    .result-supply  { color: #075985; font-weight: bold; }
    .result-fail    { color: #7f1d1d; font-weight: bold; }

    .foot-note { font-size: 7pt; color: #666; text-align: center; margin-top: 5px; font-style: italic; }
</style>
</head>
<body>

@php
    $gradeCls = fn ($g) => 'g-'.strtolower(($g ?? 'x')[0]);
    // Distinct subjects across all results (may vary if some students
    // took extra subjects). One column per unique subject.
    $subjects = collect();
    foreach ($results as $r) {
        foreach ($r->subjects as $sub) {
            if (!$subjects->firstWhere('id', $sub->subject_id)) {
                $subjects->push((object) [
                    'id'   => $sub->subject_id,
                    'name' => $sub->subject?->name,
                ]);
            }
        }
    }
@endphp

<!-- ═══════════ HEADER ═══════════ -->
<div class="hdr">
    <h1>{{ strtoupper($exam->school->name) }}</h1>
    <h2>FBISE {{ $exam->level }} · {{ $exam->title }}</h2>
    <div class="meta">
        Class {{ $exam->schoolClass?->name }} · Academic Session {{ $exam->academicSession?->name }} · {{ $stats['total'] }} students
    </div>
</div>

<!-- ═══════════ STATS STRIP ═══════════ -->
<div class="stats">
    <div class="cell"><div class="lbl">Total</div><div class="val">{{ $stats['total'] }}</div></div>
    <div class="cell"><div class="lbl">Passed</div><div class="val" style="color:#065f46;">{{ $stats['passed'] }}</div></div>
    <div class="cell"><div class="lbl">Supply</div><div class="val" style="color:#075985;">{{ $stats['supply'] }}</div></div>
    <div class="cell"><div class="lbl">Failed</div><div class="val" style="color:#7f1d1d;">{{ $stats['failed'] }}</div></div>
    <div class="cell"><div class="lbl">Pass %</div><div class="val">{{ $stats['pass_percentage'] }}%</div></div>
    <div class="cell"><div class="lbl">Average %</div><div class="val">{{ $stats['avg_percentage'] }}%</div></div>
    <div class="cell"><div class="lbl">Highest %</div><div class="val">{{ $stats['top_percentage'] }}%</div></div>
</div>

<!-- ═══════════ MAIN TABLE ═══════════ -->
<table class="data">
    <thead>
        <tr>
            <th style="width: 3%;">Pos</th>
            <th style="width: 4%;">Roll</th>
            <th style="width: 14%;">Student Name</th>
            <th style="width: 12%;">Father Name</th>
            <th style="width: 5%;">Board #</th>
            @foreach($subjects as $sub)
                <th>{{ $sub->name }}</th>
            @endforeach
            <th style="width: 5%;">Total</th>
            <th style="width: 5%;">%</th>
            <th style="width: 4%;">Grade</th>
            <th style="width: 5%;">Div.</th>
            <th style="width: 5%;">Result</th>
        </tr>
    </thead>
    <tbody>
        @foreach($results as $r)
            <tr>
                <td>{{ $r->position ?: '—' }}</td>
                <td>{{ $r->student->roll_no ?: '—' }}</td>
                <td class="name">{{ $r->student->name }}</td>
                <td class="name">{{ $r->student->father_name }}</td>
                <td>{{ $r->board_roll_no ?: '—' }}</td>
                @foreach($subjects as $sub)
                    @php $sr = $r->subjects->firstWhere('subject_id', $sub->id); @endphp
                    <td>
                        @if($sr)
                            {{ rtrim(rtrim(number_format((float) $sr->total_marks, 1), '0'), '.') }}
                        @else
                            <span style="color:#bbb;">—</span>
                        @endif
                    </td>
                @endforeach
                <td><b>{{ rtrim(rtrim(number_format((float) $r->total_obtained, 1), '0'), '.') }}</b></td>
                <td><b>{{ number_format($r->percentage, 1) }}%</b></td>
                <td><span class="grade-pill {{ $gradeCls($r->grade) }}">{{ $r->grade }}</span></td>
                <td>{{ $r->is_supplementary ? 'Supply' : $r->division }}</td>
                <td class="{{ $r->is_pass ? 'result-pass' : ($r->is_supplementary ? 'result-supply' : 'result-fail') }}">
                    {{ $r->is_pass ? 'Pass' : ($r->is_supplementary ? 'Supply' : 'Fail') }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="foot-note">
    Computer-generated. FBISE result gazette remains the authoritative source.
</div>

</body>
</html>
