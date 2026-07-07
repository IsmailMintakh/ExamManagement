@php
    // Resolve image paths. file_exists checks keep dom-pdf from rendering broken-image
    // placeholders if the file went missing.
    $logoPath = !empty($school->logo) && $school?->getLogoAbsolutePath()
        ? $school?->getLogoAbsolutePath() : null;
    $sigPath = $school?->resolveAssetPath('principal_signature') ?? null;
    $officerSigPath = $school?->resolveAssetPath('exam_officer_signature') ?? null;

    // Title combines exam type and exam name (e.g. "Monthly Test June 2026").
    $titleType = $exam->examType?->name;
    $titleName = $exam->name;
    $title = trim(($titleType ? $titleType . ' ' : '') . $titleName);

    $officerName = $exam->examController?->name ?? ($school->exam_officer_name ?? null);
    // Principal name: prefer the free-text field on the school (set on
    // Schools → Edit). Falls back to the school-admin user (relation) for
    // backward compat with schools that already have one assigned.
    $principalName = !empty($school->principal_name)
        ? $school->principal_name
        : ($school->principal?->name ?? null);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Date Sheet — {{ $title }}</title>
<style>
    /* Standard A4 page — no special margin reservation. Signature block sits
       in normal flow at the end of content (dompdf's `position: fixed` was
       unreliable: bottom: -50mm pushed it off-page, signatures vanished). */
    @page {
        size: A4 portrait;
        margin: 12mm;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1e293b; line-height: 1.35; }

    /* Outer decorative border. No min-height — let dompdf paginate naturally. */
    .frame { border: 2px solid #064e3b; padding: 6px; }
    .frame-inner { border: 1px solid #064e3b; padding: 10px; }

    /* ─── Header ─── */
    .hdr { display: table; width: 100%; padding-bottom: 8px; border-bottom: 2px solid #064e3b; margin-bottom: 10px; }
    .hdr-logo { display: table-cell; width: 80px; vertical-align: middle; }
    .hdr-logo img { width: 70px; height: 70px; object-fit: contain; }
    .logo-ph {
        width: 70px; height: 70px;
        background: #064e3b; border-radius: 50%;
        display: inline-block; line-height: 70px; text-align: center;
        color: #fff; font-size: 26pt; font-weight: bold;
    }
    .hdr-center { display: table-cell; text-align: center; vertical-align: middle; padding: 0 10px; }
    .hdr-right { display: table-cell; width: 95px; text-align: right; font-size: 8pt; vertical-align: middle; }
    .sch-tag { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 1px; }
    .sch-name {
        font-size: 16pt; font-weight: bold; color: #064e3b;
        text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.05;
    }
    .sch-addr { font-size: 8pt; color: #475569; margin-top: 3px; }

    .title-bar {
        background: #064e3b; color: #fff; text-align: center;
        padding: 7px 10px; margin-bottom: 10px;
        font-size: 13pt; font-weight: bold; letter-spacing: 2.5px; text-transform: uppercase;
    }

    .meta-row { display: table; width: 100%; margin-bottom: 12px; font-size: 9pt; border-collapse: collapse; }
    .meta-cell { display: table-cell; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 5px 8px; }
    .meta-lbl { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .meta-val { font-weight: bold; color: #1e293b; }

    /* Class header — single band with class + sections side-by-side, both
       clearly labelled and equally prominent. Class is bold green band; the
       sections block on the right is rendered with a contrasting pill so it
       reads as a paired piece of metadata, not a faded afterthought. */
    .class-title {
        background: #064e3b; color: #fff;
        padding: 6px 10px;
        font-weight: bold; font-size: 10.5pt;
        text-transform: uppercase; letter-spacing: 1px;
        margin-top: 12px;
        display: table;
        width: 100%;
        table-layout: fixed;
    }
    .class-title .cls { display: table-cell; vertical-align: middle; }
    .class-title .sec {
        display: table-cell; vertical-align: middle; text-align: right;
        font-size: 9pt; letter-spacing: 0.5px;
    }
    .class-title .sec-pill {
        background: #d1fae5; color: #064e3b;
        padding: 2px 8px; border-radius: 3px;
        font-weight: 600;
    }

    table.ds { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 9pt; }
    table.ds th, table.ds td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
    table.ds th { background: #064e3b; color: #fff; text-transform: uppercase; font-size: 8pt; letter-spacing: 0.5px; }
    table.ds td.center { text-align: center; }
    table.ds tr:nth-child(even) td { background: #f8fafc; }
    .subj-code { font-size: 7.5pt; color: #64748b; font-weight: normal; }

    /* ─── General Instructions block (above signatures, in normal flow) ─── */
    .instructions {
        margin-top: 14px;
        border: 1px solid #064e3b;
        background: #f0fdf4;
        padding: 8px 12px;
    }
    .instructions h4 {
        font-size: 9.5pt;
        color: #064e3b;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 5px;
        border-bottom: 1px solid #bbf7d0;
        padding-bottom: 3px;
    }
    .instructions ol { padding-left: 18px; font-size: 8.5pt; color: #1e293b; }
    .instructions li { margin-bottom: 2px; }

    /* ─── Signature block (in normal flow at end of content) ───
       Earlier attempt used position: fixed with bottom: -50mm which placed
       the block OFF the page → signatures vanished. Reverting to natural
       flow. For typical 1-2 class datesheets, the block lands near the bottom
       of the page after the General Instructions block above. */
    .sig {
        display: table; width: 100%;
        margin-top: 18px;
        padding-top: 10px;
        border-top: 1px solid #cbd5e1;
        page-break-inside: avoid;
    }
    .sig-cell { display: table-cell; width: 50%; text-align: center; padding: 0 16px; vertical-align: bottom; }
    .sig-img-wrap { height: 18mm; position: relative; margin-bottom: 2px; }
    .sig-img {
        position: absolute;
        left: 50%; bottom: 0;
        transform: translateX(-50%);
    }
    .sig-img img { max-height: 16mm; max-width: 50mm; }
    .sig-img .placeholder { font-size: 7pt; color: #cbd5e1; font-style: italic; line-height: 14mm; display: inline-block; }
    .sig-line { border-top: 1px solid #334155; padding-top: 3px; font-size: 9pt; font-weight: bold; }
    .sig-role { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .sig-name { font-size: 8pt; color: #0f172a; font-weight: 600; margin-top: 1px; }

    .ftr {
        margin-top: 6px; padding-top: 4px;
        border-top: 1px solid #e2e8f0;
        font-size: 7.5pt; color: #94a3b8;
        display: table; width: 100%;
    }
    .ftr-l { display: table-cell; }
    .ftr-r { display: table-cell; text-align: right; }
</style>
</head>
<body>
<div class="frame"><div class="frame-inner">

<div class="hdr">
    <div class="hdr-logo">
        @if($logoPath)
            <img src="{{ $logoPath }}" alt="">
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

<div class="title-bar">{{ $title }}</div>

<div class="meta-row">
    <div class="meta-cell" style="border-right:none;">
        <div class="meta-lbl">Session</div>
        <div class="meta-val">{{ $academicSession?->name ?? '—' }}</div>
    </div>
    <div class="meta-cell" style="border-right:none;">
        <div class="meta-lbl">Exam Window</div>
        <div class="meta-val">{{ optional($exam->start_date)->format('d M Y') ?? '—' }} – {{ optional($exam->end_date)->format('d M Y') ?? '—' }}</div>
    </div>
    <div class="meta-cell">
        <div class="meta-lbl">Total Papers</div>
        <div class="meta-val">{{ $byClass->flatten()->count() }}</div>
    </div>
</div>

@forelse($byClass as $className => $classSchedules)
@php
    $sectionList = !empty($sectionsByClass[$className]) && $sectionsByClass[$className] !== '—'
        ? $sectionsByClass[$className] : 'All';
@endphp
<div class="class-title">
    <span class="cls">Class: {{ $className }}</span>
    <span class="sec"><span class="sec-pill">Sections: {{ $sectionList }}</span></span>
</div>
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
                <td>{{ substr($sch->start_time ?? '', 0, 5) }} – {{ substr($sch->end_time ?? '', 0, 5) }}</td>
                {{-- abs() guards legacy rows where Carbon 3 stored signed-negative durations --}}
                <td class="center">
                    @if($sch->duration_minutes)
                        {{ abs((int) $sch->duration_minutes) }} min
                    @else
                        —
                    @endif
                </td>
                <td style="font-size:8pt;">{{ $sch->instructions ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@empty
<p style="padding:20px; text-align:center; color:#94a3b8;">No schedule rows created yet for this school.</p>
@endforelse

{{-- General Instructions — appears in normal flow above the signature block.
     Standard exam-hall guidance; tweak wording as policy evolves. --}}
<div class="instructions">
    <h4>General Instructions</h4>
    <ol>
        <li>Students must reach the examination centre at least 30 minutes before the scheduled start time.</li>
        <li>Carry a valid Admit Card and School ID; entry will be denied without them.</li>
        <li>Mobile phones, smart watches and any electronic devices are strictly prohibited inside the hall.</li>
        <li>Use only blue or black ink ball-point pens for writing the paper.</li>
        <li>Late arrival beyond 15 minutes after the start time will not be allowed under any circumstances.</li>
        <li>Any form of unfair means will lead to immediate cancellation of the paper.</li>
    </ol>
</div>

{{-- ─── Signatures (natural flow, after instructions) ─── --}}
<div class="sig">
    <div class="sig-cell">
        <div class="sig-img-wrap">
            <div class="sig-img">
                @if($officerSigPath)
                    <img src="{{ $officerSigPath }}" alt="">
                @else
                    <span class="placeholder">— signature —</span>
                @endif
            </div>
        </div>
        <div class="sig-line">Exam Controller</div>
        @if($officerName)
            <div class="sig-name">{{ $officerName }}</div>
        @else
            <div class="sig-role">Signature &amp; Date</div>
        @endif
    </div>

    <div class="sig-cell">
        <div class="sig-img-wrap">
            <div class="sig-img">
                @if($sigPath)
                    <img src="{{ $sigPath }}" alt="">
                @else
                    <span class="placeholder">— signature —</span>
                @endif
            </div>
        </div>
        <div class="sig-line">Principal</div>
        @if($principalName)
            <div class="sig-name">{{ $principalName }}</div>
        @else
            <div class="sig-role">Signature &amp; Date</div>
        @endif
    </div>
</div>

<div class="ftr">
    <div class="ftr-l">Generated: <strong>{{ now()->format('d M Y, h:i A') }}</strong></div>
    <div class="ftr-r">Computer-generated · signed by Principal &amp; Exam Controller</div>
</div>

</div></div>

</body>
</html>
