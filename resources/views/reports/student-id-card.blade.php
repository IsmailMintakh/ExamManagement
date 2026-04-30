@php
    // DomPDF requires the GD extension to embed JPEG/PNG images. Detect once
    // so we can gracefully fall back to text placeholders when GD isn't available.
    $canEmbedImages = extension_loaded('gd');

    $school = $student->school;
    $logoPath = ($canEmbedImages && !empty($school?->logo) && file_exists(public_path('storage/' . $school->logo)))
        ? public_path('storage/' . $school->logo) : null;
    $stampPath = ($canEmbedImages && !empty($school?->school_stamp) && file_exists(public_path('storage/' . $school->school_stamp)))
        ? public_path('storage/' . $school->school_stamp) : null;
    $signaturePath = ($canEmbedImages && !empty($school?->principal_signature) && file_exists(public_path('storage/' . $school->principal_signature)))
        ? public_path('storage/' . $school->principal_signature) : null;
    $photoPath = ($canEmbedImages && !empty($student->photo) && file_exists(public_path('storage/' . $student->photo)))
        ? public_path('storage/' . $student->photo) : null;

    $session = $student->academicSession?->name ?? now()->year . '-' . substr(now()->year + 1, 2);
    $code = $student->admission_no;
    $verifyUrl = rtrim(config('app.url'), '/') . '/verify/student/' . $code;

    // Build a deterministic "QR" pattern from code — looks like a real QR badge
    $hash = md5($code);
    $bits = [];
    for ($i = 0; $i < 144; $i++) {
        $bits[] = (hexdec($hash[$i % 32]) + $i) % 3 !== 0 ? '#' : ' ';
    }
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Student ID Card · {{ $student->name }}</title>
<style>
    @page { size: A4 portrait; margin: 18mm 14mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #0f172a; }

    .page-title {
        text-align: center;
        font-size: 9pt;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 3px;
        font-weight: bold;
        margin-bottom: 12mm;
    }

    /* ===== Card sizing — slightly larger than CR80 for readability ===== */
    .card {
        width: 100mm;
        height: 62mm;
        margin: 0 auto;
        position: relative;
        overflow: hidden;
        border-radius: 4mm;
    }
    .card.front {
        background: #ffffff;
        border: 1.5px solid #e2e8f0;
        /* shadow removed for DomPDF compatibility */
    }
    .card.back {
        background: #f8fafc;
        border: 1.5px solid #e2e8f0;
        /* shadow removed for DomPDF compatibility */
    }

    /* Crop marks */
    .crop-marks {
        position: relative;
        margin: 0 auto;
        width: 100mm;
    }
    .crop-marks::before, .crop-marks::after {
        content: '';
        position: absolute;
        background: #cbd5e1;
    }
    .between {
        text-align: center;
        font-size: 8pt;
        color: #94a3b8;
        margin: 8mm 0;
        letter-spacing: 2px;
    }
    .between::before, .between::after {
        content: '— — —';
        margin: 0 5mm;
        color: #cbd5e1;
    }

    /* ============== FRONT ============== */
    .front {
        display: table;
    }
    .front-header {
        background: #064e3b;
        color: #ffffff;
        padding: 4mm 5mm 3mm;
        position: relative;
    }
    /* decorative blur removed for DomPDF compatibility */
    .front-header-row {
        display: table;
        width: 100%;
        position: relative;
        z-index: 2;
    }
    .front-logo-cell {
        display: table-cell;
        width: 12mm;
        vertical-align: middle;
    }
    .front-logo {
        width: 11mm;
        height: 11mm;
        background: rgba(255,255,255,0.15);
        border: 1.5px solid rgba(255,255,255,0.3);
        border-radius: 2mm;
        text-align: center;
        line-height: 11mm;
        font-size: 12pt;
        font-weight: bold;
        color: #fff;
        overflow: hidden;
    }
    .front-logo img { max-width: 100%; max-height: 100%; }
    .front-school-cell {
        display: table-cell;
        vertical-align: middle;
        padding-left: 3mm;
    }
    .front-school-name {
        font-size: 9pt;
        font-weight: bold;
        line-height: 1.15;
        letter-spacing: 0.5px;
    }
    .front-school-tagline {
        font-size: 6pt;
        color: rgba(252, 211, 77, 0.95);
        font-weight: bold;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        margin-top: 0.5mm;
    }

    .front-id-tag {
        position: absolute;
        right: 4mm; top: 3mm;
        background: #f59e0b;
        color: #422006;
        font-size: 6.5pt;
        font-weight: bold;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        padding: 1mm 2.5mm;
        border-radius: 1.2mm;
        z-index: 3;
    }

    .front-body {
        position: relative;
        height: 47mm;
        padding: 4mm 5mm 4mm 4mm;
    }

    .front-photo {
        position: absolute;
        left: 4mm; top: 4mm;
        width: 25mm; height: 30mm;
        background: #e2e8f0;
        border: 2px solid #ffffff;
        /* shadow removed */
        border-radius: 1.5mm;
        text-align: center;
        line-height: 30mm;
        font-size: 9pt;
        color: #475569;
        font-weight: bold;
        overflow: hidden;
    }
    .front-photo img {
        width: 100%; height: 100%;
        object-fit: cover;
    }

    .front-info {
        position: absolute;
        left: 33mm; top: 4mm; right: 5mm;
    }

    .front-name-block {
        margin-bottom: 2.5mm;
    }
    .front-name {
        font-size: 11pt;
        font-weight: 900;
        color: #0f172a;
        line-height: 1.1;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .front-father {
        font-size: 7pt;
        color: #64748b;
        margin-top: 0.5mm;
        font-weight: 500;
    }

    .front-row {
        display: table;
        width: 100%;
        font-size: 7pt;
        padding: 1mm 0;
        border-bottom: 0.5px dotted #cbd5e1;
    }
    .front-row:last-child { border-bottom: none; }
    .front-row-label {
        display: table-cell;
        width: 16mm;
        color: #94a3b8;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 6.5pt;
    }
    .front-row-value {
        display: table-cell;
        color: #0f172a;
        font-weight: bold;
    }
    .front-row-value.blood {
        color: #b91c1c;
    }

    .front-footer {
        position: absolute;
        left: 0; right: 0; bottom: 0;
        padding: 2mm 5mm;
        background: #f1f5f9;
        font-size: 6pt;
        color: #64748b;
        display: table;
        width: 100%;
    }
    .front-footer-l {
        display: table-cell;
        font-weight: bold;
        letter-spacing: 0.3px;
    }
    .front-footer-r {
        display: table-cell;
        text-align: right;
        color: #047857;
        font-weight: bold;
    }

    /* ============== BACK ============== */
    .back {
        position: relative;
    }
    .back-header {
        padding: 3mm 5mm 2mm;
        border-bottom: 1.5px solid #e2e8f0;
        text-align: center;
    }
    .back-title {
        font-size: 7pt;
        font-weight: bold;
        color: #047857;
        letter-spacing: 2.5px;
        text-transform: uppercase;
    }
    .back-subtitle {
        font-size: 6pt;
        color: #64748b;
        margin-top: 0.5mm;
    }

    .back-body {
        position: absolute;
        top: 9mm; left: 0; right: 0; bottom: 12mm;
        padding: 2mm 5mm;
        font-size: 6.5pt;
    }

    .back-row {
        display: table;
        width: 100%;
        padding: 1mm 0;
        border-bottom: 0.5px dotted #cbd5e1;
    }
    .back-row-label {
        display: table-cell;
        width: 26mm;
        color: #94a3b8;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 6pt;
    }
    .back-row-value {
        display: table-cell;
        color: #0f172a;
        font-weight: 600;
    }

    .back-rules {
        position: absolute;
        left: 5mm;
        right: 30mm;
        bottom: 14mm;
        font-size: 5.5pt;
        color: #64748b;
        line-height: 1.4;
    }
    .back-rules b { color: #b91c1c; }

    .back-qr {
        position: absolute;
        right: 5mm; bottom: 14mm;
        width: 20mm; height: 20mm;
        border: 1px solid #0f172a;
        padding: 1mm;
        background: #fff;
    }
    .qr-grid {
        width: 100%; height: 100%;
        border-collapse: collapse;
        table-layout: fixed;
    }
    .qr-grid td {
        padding: 0;
        height: 1.4mm;
    }
    .qr-grid td.on { background: #0f172a; }
    .qr-grid td.off { background: #fff; }

    .back-footer {
        position: absolute;
        left: 0; right: 0; bottom: 0;
        padding: 2mm 5mm;
        background: #047857;
        color: #fff;
        font-size: 5.5pt;
        display: table;
        width: 100%;
    }
    .back-footer-l { display: table-cell; font-weight: bold; letter-spacing: 0.3px; }
    .back-footer-r { display: table-cell; text-align: right; color: #fcd34d; }

    .back-stamp {
        position: absolute;
        left: 50%;
        margin-left: -8mm;
        bottom: 18mm;
        width: 16mm; height: 16mm;
        border-radius: 50%;
        opacity: 0.45;
        text-align: center;
        line-height: 16mm;
    }
    .back-stamp img { max-width: 100%; max-height: 100%; }
    .back-signature {
        position: absolute;
        left: 50%;
        margin-left: -16mm;
        bottom: 14mm;
        width: 32mm;
        text-align: center;
    }
    .back-signature img { max-height: 8mm; max-width: 30mm; }
    .back-signature .sig-line {
        border-top: 0.8px solid #0f172a;
        margin-top: 1mm;
        padding-top: 0.5mm;
        font-size: 5.5pt;
        color: #64748b;
        font-weight: bold;
        letter-spacing: 0.5px;
    }
</style>
</head>
<body>
<div class="page-title">Student Identification Card · {{ $session }}</div>

<!-- ============ FRONT ============ -->
<div class="card front">
    <div class="front-header">
        <div class="front-id-tag">Student ID</div>
        <div class="front-header-row">
            <div class="front-logo-cell">
                <div class="front-logo">
                    @if($logoPath)
                        <img src="{{ $logoPath }}" alt="">
                    @else
                        {{ strtoupper(substr($school?->name ?? 'S', 0, 1)) }}
                    @endif
                </div>
            </div>
            <div class="front-school-cell">
                <div class="front-school-name">{{ $school?->name ?? 'School Name' }}</div>
                <div class="front-school-tagline">Excellence · Discipline · Honor</div>
            </div>
        </div>
    </div>

    <div class="front-body">
        <div class="front-photo">
            @if($photoPath)
                <img src="{{ $photoPath }}" alt="">
            @else
                {{ strtoupper(substr($student->name, 0, 1)) }}
            @endif
        </div>

        <div class="front-info">
            <div class="front-name-block">
                <div class="front-name">{{ $student->name }}</div>
                <div class="front-father">S/o {{ $student->father_name ?? '—' }}</div>
            </div>

            <div class="front-row">
                <div class="front-row-label">Class</div>
                <div class="front-row-value">{{ $student->schoolClass?->name }} · Sec {{ $student->section?->name }}</div>
            </div>
            <div class="front-row">
                <div class="front-row-label">Roll No</div>
                <div class="front-row-value">{{ $student->roll_no ?: '—' }}</div>
            </div>
            <div class="front-row">
                <div class="front-row-label">Adm. No</div>
                <div class="front-row-value">{{ $student->admission_no }}</div>
            </div>
            <div class="front-row">
                <div class="front-row-label">Blood</div>
                <div class="front-row-value blood">{{ $student->blood_group ?: '—' }}</div>
            </div>
        </div>

        <div class="front-footer">
            <div class="front-footer-l">VALID FOR SESSION {{ $session }}</div>
            <div class="front-footer-r">★ {{ $school?->code ?? '' }}</div>
        </div>
    </div>
</div>

<div class="between">FOLD HERE</div>

<!-- ============ BACK ============ -->
<div class="card back">
    <div class="back-header">
        <div class="back-title">Identification & Emergency Information</div>
        <div class="back-subtitle">If found, please return to the school office</div>
    </div>

    <div class="back-body">
        <div class="back-row">
            <div class="back-row-label">Student Name</div>
            <div class="back-row-value">{{ $student->name }}</div>
        </div>
        <div class="back-row">
            <div class="back-row-label">Father's Name</div>
            <div class="back-row-value">{{ $student->father_name ?: '—' }}</div>
        </div>
        <div class="back-row">
            <div class="back-row-label">Date of Birth</div>
            <div class="back-row-value">{{ $student->date_of_birth ? \Carbon\Carbon::parse($student->date_of_birth)->format('d M Y') : '—' }}</div>
        </div>
        <div class="back-row">
            <div class="back-row-label">Guardian Phone</div>
            <div class="back-row-value">{{ $student->guardian_phone ?: '—' }}</div>
        </div>
        <div class="back-row">
            <div class="back-row-label">Address</div>
            <div class="back-row-value">{{ \Illuminate\Support\Str::limit($student->address ?? '—', 60) }}</div>
        </div>
    </div>

    <div class="back-rules">
        <b>I. M. P:</b> This card is property of the school and must be returned upon graduation or transfer. Not transferable. Misuse leads to disciplinary action.
    </div>

    <div class="back-qr">
        <table class="qr-grid">
            @for ($r = 0; $r < 12; $r++)
                <tr>
                    @for ($c = 0; $c < 12; $c++)
                        @php
                            $idx = $r * 12 + $c;
                            $on = isset($bits[$idx]) && $bits[$idx] === '#';
                            // Draw the three corner anchors as solid squares
                            $isAnchor = (($r < 3 && $c < 3) || ($r < 3 && $c > 8) || ($r > 8 && $c < 3));
                            $solid = $isAnchor && (
                                ($r === 0 || $r === 2 || ($c === 0 && $r < 3) || ($c === 2 && $r < 3))
                                || ($r === 0 || $r === 2 || ($c === 9 && $r < 3) || ($c === 11 && $r < 3))
                                || ($r === 9 || $r === 11 || ($c === 0 && $r > 8) || ($c === 2 && $r > 8))
                            );
                            $on = $on || $solid;
                        @endphp
                        <td class="{{ $on ? 'on' : 'off' }}"></td>
                    @endfor
                </tr>
            @endfor
        </table>
    </div>

    <div class="back-stamp">
        @if($stampPath)<img src="{{ $stampPath }}" alt="">@endif
    </div>

    <div class="back-footer">
        <div class="back-footer-l">{{ $school?->address ?? '' }}</div>
        <div class="back-footer-r">{{ $school?->phone ?? '' }}</div>
    </div>
</div>
</body>
</html>
