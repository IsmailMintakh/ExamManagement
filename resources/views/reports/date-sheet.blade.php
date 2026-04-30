<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Date Sheet — {{ $exam->name }}</title>
<style>
    @page { size: A4 portrait; margin: 12mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1e293b; line-height: 1.35; }

    .frame { border: 2px solid #1e3a8a; padding: 6px; }
    .frame-inner { border: 1px solid #1e3a8a; padding: 10px; }

    .hdr { display: table; width: 100%; padding-bottom: 8px; border-bottom: 2px solid #1e3a8a; margin-bottom: 10px; }
    .hdr-logo { display: table-cell; width: 64px; vertical-align: middle; }
    .hdr-logo img { width: 58px; height: 58px; object-fit: contain; }
    .logo-ph { width: 58px; height: 58px; background: #1e3a8a; border-radius: 50%; display: inline-block; line-height: 58px; text-align: center; color: #fff; font-size: 22pt; font-weight: bold; }
    .hdr-center { display: table-cell; text-align: center; vertical-align: middle; padding: 0 10px; }
    .hdr-right { display: table-cell; width: 90px; text-align: right; font-size: 8pt; vertical-align: middle; }
    .sch-tag { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 1px; }
    .sch-name { font-size: 17pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1; }
    .sch-addr { font-size: 8pt; color: #475569; margin-top: 3px; }

    .title-bar { background: #1e3a8a; color: #fff; text-align: center; padding: 6px 10px; margin-bottom: 10px; font-size: 12pt; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; }

    .meta-row { display: table; width: 100%; margin-bottom: 10px; font-size: 9pt; }
    .meta-cell { display: table-cell; background: #eff6ff; border: 1px solid #bfdbfe; padding: 5px 8px; }
    .meta-lbl { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .meta-val { font-weight: bold; color: #1e293b; }

    .class-title { background: #e0e7ff; color: #1e3a8a; padding: 5px 10px; font-weight: bold; font-size: 10pt; text-transform: uppercase; letter-spacing: 1px; margin-top: 10px; margin-bottom: 0; border-left: 4px solid #1e3a8a; }

    table.ds { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 9pt; }
    table.ds th, table.ds td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
    table.ds th { background: #1e3a8a; color: #fff; text-transform: uppercase; font-size: 8pt; letter-spacing: 0.5px; }
    table.ds td.center { text-align: center; }
    table.ds tr:nth-child(even) td { background: #f8fafc; }
    .subj-code { font-size: 7.5pt; color: #64748b; font-weight: normal; }

    .ftr { margin-top: 12px; padding-top: 6px; border-top: 1px solid #e2e8f0; font-size: 7.5pt; color: #94a3b8; display: table; width: 100%; }
    .ftr-l { display: table-cell; }
    .ftr-r { display: table-cell; text-align: right; }

    .sig { display: table; width: 100%; margin-top: 18px; }
    .sig-cell { display: table-cell; width: 33.33%; text-align: center; padding: 0 10px; vertical-align: bottom; }
    .sig-line { border-top: 1px solid #334155; padding-top: 3px; font-size: 9pt; font-weight: bold; }
    .sig-role { font-size: 7pt; color: #64748b; text-transform: uppercase; }
</style>
</head>
<body>
<div class="frame"><div class="frame-inner">

<div class="hdr">
    <div class="hdr-logo">
        @if(!empty($school->logo) && file_exists(public_path('storage/' . $school->logo)))
            <img src="{{ public_path('storage/' . $school->logo) }}" alt="">
        @else
            <span class="logo-ph">{{ strtoupper(substr($school->name ?? 'S', 0, 1)) }}</span>
        @endif
    </div>
    <div class="hdr-center">
        <div class="sch-tag">Date Sheet</div>
        <div class="sch-name">{{ $school->name ?? 'School' }}</div>
        <div class="sch-addr">
            {{ $school->address ?? '' }}@if(!empty($school->phone)) · Ph: {{ $school->phone }}@endif
        </div>
    </div>
    <div class="hdr-right">
        <div class="meta-lbl">Issue Date</div>
        <div class="meta-val">{{ now()->format('d M Y') }}</div>
    </div>
</div>

<div class="title-bar">{{ $exam->name }}</div>

<div class="meta-row">
    <div class="meta-cell" style="border-right:none;">
        <div class="meta-lbl">Exam Type</div>
        <div class="meta-val">{{ $exam->examType?->name ?? '—' }}</div>
    </div>
    <div class="meta-cell" style="border-right:none;">
        <div class="meta-lbl">Session</div>
        <div class="meta-val">{{ $academicSession?->name ?? '—' }}</div>
    </div>
    <div class="meta-cell" style="border-right:none;">
        <div class="meta-lbl">Exam Window</div>
        <div class="meta-val">{{ optional($exam->start_date)->format('d M Y') ?? '—' }} – {{ optional($exam->end_date)->format('d M Y') ?? '—' }}</div>
    </div>
    <div class="meta-cell">
        <div class="meta-lbl">Total Sessions</div>
        <div class="meta-val">{{ $byClass->flatten()->count() }}</div>
    </div>
</div>

@forelse($byClass as $className => $classSchedules)
<div class="class-title">Class: {{ $className }}</div>
<table class="ds">
    <thead>
        <tr>
            <th style="width:5%">#</th>
            <th style="width:16%">Date</th>
            <th style="width:13%">Day</th>
            <th style="width:26%">Subject</th>
            <th style="width:22%">Time</th>
            <th style="width:10%">Duration</th>
            <th>Instructions</th>
        </tr>
    </thead>
    <tbody>
        @foreach($classSchedules as $i => $sch)
            <tr>
                <td class="center">{{ $i + 1 }}</td>
                <td>{{ optional($sch->exam_date)->format('d M Y') }}</td>
                <td>{{ optional($sch->exam_date)->format('l') }}</td>
                <td>
                    <strong>{{ $sch->subject?->name }}</strong>
                    @if(!empty($sch->subject?->code))
                        <div class="subj-code">{{ $sch->subject->code }}</div>
                    @endif
                </td>
                <td>{{ substr($sch->start_time, 0, 5) }} – {{ substr($sch->end_time, 0, 5) }}</td>
                <td class="center">{{ $sch->duration_minutes ? $sch->duration_minutes.' min' : '—' }}</td>
                <td style="font-size:8pt;">{{ $sch->instructions ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@empty
<p style="padding:20px; text-align:center; color:#94a3b8;">No schedule rows created yet.</p>
@endforelse

<div class="sig">
    <div class="sig-cell">
        <div class="sig-line">Examination Officer</div>
        <div class="sig-role">Signature &amp; Date</div>
    </div>
    <div class="sig-cell">
        <div class="sig-line">Principal</div>
        <div class="sig-role">School Head</div>
    </div>
    <div class="sig-cell">
        <div class="sig-line">Controller of Examinations</div>
        <div class="sig-role">DDO Office</div>
    </div>
</div>

<div class="ftr">
    <div class="ftr-l">Generated: <strong>{{ now()->format('d M Y, h:i A') }}</strong></div>
    <div class="ftr-r">Computer-generated — no manual signature required</div>
</div>

</div></div>
</body>
</html>
