<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Student ID Cards · Bulk</title>
<style>
    @page { size: A4 portrait; margin: 8mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #0f172a; }

    .sheet-title {
        text-align: center;
        font-size: 8pt;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 2px;
        margin-bottom: 4mm;
    }

    /* 4 columns x 2 rows × ... = 8 cards per A4 (each 95mm x 60mm) */
    .grid {
        display: table;
        width: 100%;
        table-layout: fixed;
        border-collapse: separate;
        border-spacing: 2mm;
    }
    .row { display: table-row; }
    .cell {
        display: table-cell;
        width: 50%;
        padding: 0;
    }

    .card {
        width: 95mm;
        height: 60mm;
        position: relative;
        overflow: hidden;
        border-radius: 3mm;
        page-break-inside: avoid;
    }
    .card.front {
        background: #ffffff;
        border: 1px dashed #cbd5e1;
        outline: 1px solid #fff;
    }
    .card.back {
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
    }

    /* ============== FRONT (compact) ============== */
    .front-header {
        background: #064e3b;
        color: #fff;
        padding: 2.5mm 3mm 2mm;
        position: relative;
    }
    .front-id-tag {
        position: absolute;
        right: 2.5mm; top: 2mm;
        background: #f59e0b;
        color: #422006;
        font-size: 5.5pt;
        font-weight: bold;
        letter-spacing: 1px;
        padding: 0.5mm 1.5mm;
        border-radius: 0.8mm;
    }
    .front-header-flex {
        display: table;
        width: 100%;
    }
    .front-logo {
        display: table-cell;
        width: 9mm;
        vertical-align: middle;
    }
    .front-logo div {
        width: 8mm; height: 8mm;
        background: rgba(255,255,255,0.2);
        border-radius: 1mm;
        text-align: center;
        line-height: 8mm;
        font-size: 9pt;
        font-weight: bold;
        overflow: hidden;
    }
    .front-logo div img { max-width: 100%; max-height: 100%; }
    .front-school {
        display: table-cell;
        vertical-align: middle;
        padding-left: 2mm;
    }
    .front-school-name {
        font-size: 7pt;
        font-weight: bold;
        line-height: 1.1;
    }
    .front-school-tagline {
        font-size: 5pt;
        color: #fcd34d;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 0.3mm;
    }

    .front-body {
        position: relative;
        padding: 3mm;
        height: 47mm;
    }
    .photo-box {
        position: absolute;
        left: 3mm; top: 3mm;
        width: 22mm; height: 27mm;
        background: #e2e8f0;
        border: 1.5px solid #fff;
        /* shadow removed */
        border-radius: 1mm;
        text-align: center;
        line-height: 27mm;
        font-size: 8pt;
        color: #475569;
        font-weight: bold;
        overflow: hidden;
    }
    .photo-box img { width: 100%; height: 100%; object-fit: cover; }

    .info {
        position: absolute;
        left: 28mm; top: 3mm; right: 3mm;
        font-size: 6pt;
    }
    .info .name {
        font-size: 9pt;
        font-weight: 900;
        line-height: 1;
        text-transform: uppercase;
        margin-bottom: 0.5mm;
    }
    .info .father {
        font-size: 5.5pt;
        color: #64748b;
        margin-bottom: 1.5mm;
    }
    .info-row {
        display: table;
        width: 100%;
        padding: 0.5mm 0;
        border-bottom: 0.4px dotted #cbd5e1;
        font-size: 6pt;
    }
    .info-row:last-child { border-bottom: none; }
    .info-row .l {
        display: table-cell;
        width: 12mm;
        color: #94a3b8;
        font-weight: bold;
        text-transform: uppercase;
        font-size: 5pt;
        letter-spacing: 0.3px;
    }
    .info-row .v { display: table-cell; font-weight: bold; }
    .info-row .v.blood { color: #b91c1c; }

    .front-footer {
        position: absolute;
        left: 0; right: 0; bottom: 0;
        padding: 1.5mm 3mm;
        background: #f1f5f9;
        font-size: 5.5pt;
        color: #64748b;
        display: table;
        width: 100%;
    }
    .front-footer .l { display: table-cell; font-weight: bold; }
    .front-footer .r { display: table-cell; text-align: right; color: #047857; font-weight: bold; }

    /* ============== BACK (compact) ============== */
    .back {
        position: relative;
    }
    .back-header {
        padding: 2mm 3mm;
        border-bottom: 1px solid #e2e8f0;
        text-align: center;
    }
    .back-header .t {
        font-size: 6pt;
        font-weight: bold;
        color: #047857;
        letter-spacing: 1.8px;
        text-transform: uppercase;
    }
    .back-body {
        position: absolute;
        top: 7mm; left: 0; right: 0; bottom: 9mm;
        padding: 2mm 3mm;
    }
    .back-row {
        display: table;
        width: 100%;
        padding: 0.5mm 0;
        border-bottom: 0.4px dotted #cbd5e1;
        font-size: 5.5pt;
    }
    .back-row .l {
        display: table-cell;
        width: 22mm;
        color: #94a3b8;
        font-weight: bold;
        text-transform: uppercase;
        font-size: 5pt;
    }
    .back-row .v { display: table-cell; font-weight: 600; }

    .back-footer {
        position: absolute;
        left: 0; right: 0; bottom: 0;
        padding: 1.5mm 3mm;
        background: #047857;
        color: #fff;
        font-size: 5pt;
    }
    .back-qr {
        position: absolute;
        right: 3mm; bottom: 11mm;
        width: 13mm; height: 13mm;
        border: 0.6px solid #0f172a;
        padding: 0.5mm;
        background: #fff;
    }
    .back-qr table { width: 100%; height: 100%; border-collapse: collapse; table-layout: fixed; }
    .back-qr td { padding: 0; height: 1mm; }
    .back-qr td.on { background: #0f172a; }
    .back-qr td.off { background: #fff; }

    .page-break { page-break-after: always; }
</style>
</head>
<body>
<div class="sheet-title">Student ID Cards · Front Side</div>

@php
    // DomPDF needs GD to embed images; degrade gracefully if missing.
    $canEmbedImages = extension_loaded('gd');
    $rows = $students->chunk(2);
    $totalChunks = $rows->count();
@endphp

{{-- ═══ FRONT SIDE PAGE(S) ═══ --}}
@foreach ($rows->chunk(4) as $pageIdx => $pageChunks)
<div class="grid">
    @foreach ($pageChunks as $rowIdx => $row)
    <div class="row">
        @foreach ($row as $student)
            @php
                $school = $student->school;
                $logoPath = ($canEmbedImages && !empty($school?->logo) && file_exists(public_path('storage/' . $school->logo)))
                    ? public_path('storage/' . $school->logo) : null;
                $photoPath = ($canEmbedImages && !empty($student->photo) && file_exists(public_path('storage/' . $student->photo)))
                    ? public_path('storage/' . $student->photo) : null;
                $session = $student->academicSession?->name ?? now()->year . '-' . substr(now()->year + 1, 2);
            @endphp
            <div class="cell">
                <div class="card front">
                    <div class="front-header">
                        <div class="front-id-tag">ID</div>
                        <div class="front-header-flex">
                            <div class="front-logo">
                                <div>
                                    @if($logoPath)<img src="{{ $logoPath }}" alt="">@else{{ strtoupper(substr($school?->name ?? 'S', 0, 1)) }}@endif
                                </div>
                            </div>
                            <div class="front-school">
                                <div class="front-school-name">{{ \Illuminate\Support\Str::limit($school?->name ?? '', 38) }}</div>
                                <div class="front-school-tagline">Student Identity</div>
                            </div>
                        </div>
                    </div>
                    <div class="front-body">
                        <div class="photo-box">
                            @if($photoPath)<img src="{{ $photoPath }}" alt="">@else{{ strtoupper(substr($student->name, 0, 1)) }}@endif
                        </div>
                        <div class="info">
                            <div class="name">{{ \Illuminate\Support\Str::limit($student->name, 24) }}</div>
                            <div class="father">S/o {{ \Illuminate\Support\Str::limit($student->father_name ?? '—', 26) }}</div>
                            <div class="info-row"><div class="l">Class</div><div class="v">{{ $student->schoolClass?->name }} · {{ $student->section?->name }}</div></div>
                            <div class="info-row"><div class="l">Roll</div><div class="v">{{ $student->roll_no ?: '—' }}</div></div>
                            <div class="info-row"><div class="l">Adm.</div><div class="v">{{ $student->admission_no }}</div></div>
                            <div class="info-row"><div class="l">Blood</div><div class="v blood">{{ $student->blood_group ?: '—' }}</div></div>
                        </div>
                        <div class="front-footer">
                            <div class="l">SESSION {{ $session }}</div>
                            <div class="r">★ {{ $school?->code ?? '' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
        @if (count($row) === 1)<div class="cell"></div>@endif
    </div>
    @endforeach
</div>

@if (!$loop->last)
<div class="page-break"></div>
<div class="sheet-title">Student ID Cards · Front Side · Page {{ $pageIdx + 2 }}</div>
@endif
@endforeach

{{-- ═══ BACK SIDE PAGE(S) ═══ Print double-sided to align --}}
<div class="page-break"></div>
<div class="sheet-title">Student ID Cards · Back Side · Print on reverse</div>

@foreach ($rows->chunk(4) as $pageIdx => $pageChunks)
<div class="grid">
    @foreach ($pageChunks as $rowIdx => $row)
    <div class="row">
        @foreach ($row->reverse() as $student)
            @php
                $school = $student->school;
                $hash = md5($student->admission_no);
                $bits = [];
                for ($i = 0; $i < 144; $i++) {
                    $bits[] = (hexdec($hash[$i % 32]) + $i) % 3 !== 0;
                }
            @endphp
            <div class="cell">
                <div class="card back">
                    <div class="back-header"><div class="t">Identification &amp; Emergency</div></div>
                    <div class="back-body">
                        <div class="back-row"><div class="l">Father</div><div class="v">{{ \Illuminate\Support\Str::limit($student->father_name ?? '—', 30) }}</div></div>
                        <div class="back-row"><div class="l">DOB</div><div class="v">{{ $student->date_of_birth ? \Carbon\Carbon::parse($student->date_of_birth)->format('d M Y') : '—' }}</div></div>
                        <div class="back-row"><div class="l">Phone</div><div class="v">{{ $student->guardian_phone ?: '—' }}</div></div>
                        <div class="back-row"><div class="l">Address</div><div class="v">{{ \Illuminate\Support\Str::limit($student->address ?? '—', 38) }}</div></div>
                    </div>
                    <div class="back-qr">
                        <table>
                            @for ($r = 0; $r < 12; $r++)<tr>
                                @for ($c = 0; $c < 12; $c++)
                                    @php
                                        $idx = $r * 12 + $c;
                                        $isAnchor = (($r < 3 && $c < 3) || ($r < 3 && $c > 8) || ($r > 8 && $c < 3));
                                        $on = $bits[$idx] || $isAnchor;
                                    @endphp
                                    <td class="{{ $on ? 'on' : 'off' }}"></td>
                                @endfor
                            </tr>@endfor
                        </table>
                    </div>
                    <div class="back-footer">{{ \Illuminate\Support\Str::limit($school?->address ?? '', 50) }}</div>
                </div>
            </div>
        @endforeach
        @if (count($row) === 1)<div class="cell"></div>@endif
    </div>
    @endforeach
</div>

@if (!$loop->last)
<div class="page-break"></div>
<div class="sheet-title">Back Side · Page {{ $pageIdx + 2 }}</div>
@endif
@endforeach

</body>
</html>
