@php
    // DomPDF requires GD to embed images. Detect once for graceful fallback.
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

    // Deterministic "QR" pattern from admission code
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

    /* CR-80 ID card proportions, slightly larger */
    .card {
        width: 100mm;
        height: 62mm;
        margin: 0 auto;
        position: relative;
        overflow: hidden;
        border-radius: 4mm;
    }
    .card.front { background: #ffffff; border: 1.5px solid #cbd5e1; }
    .card.back  { background: #ffffff; border: 1.5px solid #cbd5e1; }

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

    /* ============== FRONT ==============
       Layout: header (12mm) → body with photo+info (44mm) → footer (6mm) */
    .front-header {
        background: #064e3b;
        color: #ffffff;
        padding: 2.5mm 4mm;
        height: 12mm;
    }
    .front-header table { width: 100%; border-collapse: collapse; }
    .front-header td { vertical-align: middle; padding: 0; }
    .front-logo {
        width: 9mm; height: 9mm;
        background: rgba(255,255,255,0.15);
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 1.5mm;
        text-align: center;
        line-height: 9mm;
        font-size: 10pt;
        font-weight: bold;
        color: #fff;
        overflow: hidden;
    }
    .front-logo img { max-width: 100%; max-height: 100%; }
    .front-school-cell { padding-left: 3mm; }
    .front-school-name {
        font-size: 8.5pt;
        font-weight: bold;
        line-height: 1.1;
        letter-spacing: 0.2px;
    }
    .front-school-tagline {
        font-size: 5.5pt;
        color: #fcd34d;
        font-weight: bold;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        margin-top: 0.5mm;
    }

    .front-body {
        position: relative;
        height: 44mm;
        padding: 3mm;
    }
    .front-photo {
        position: absolute;
        left: 3mm; top: 3mm;
        width: 24mm; height: 30mm;
        background: #f1f5f9;
        border: 1.5px solid #ffffff;
        outline: 1px solid #cbd5e1;
        border-radius: 1.5mm;
        text-align: center;
        line-height: 30mm;
        font-size: 9pt;
        color: #475569;
        font-weight: bold;
        overflow: hidden;
    }
    .front-photo img { width: 100%; height: 100%; object-fit: cover; }

    .front-info {
        position: absolute;
        left: 30mm; top: 3mm; right: 3mm;
    }
    .front-name {
        font-size: 11pt;
        font-weight: 900;
        color: #064e3b;
        line-height: 1.05;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .front-father {
        font-size: 6.5pt;
        color: #64748b;
        margin-top: 0.8mm;
        font-weight: 500;
    }
    .front-divider {
        height: 0.6px;
        background: #cbd5e1;
        margin: 2mm 0 1.5mm;
    }
    .front-row {
        display: table;
        width: 100%;
        font-size: 7pt;
        padding: 0.7mm 0;
    }
    .front-row + .front-row { border-top: 0.4px dotted #e2e8f0; }
    .front-row-label {
        display: table-cell;
        width: 16mm;
        color: #94a3b8;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        font-size: 6pt;
    }
    .front-row-value {
        display: table-cell;
        color: #0f172a;
        font-weight: bold;
    }
    .front-row-value.blood {
        color: #b91c1c;
        font-size: 8pt;
    }

    /* Bottom validity strip — clean, no stamp/sig clutter on the front */
    .front-footer {
        position: absolute;
        left: 0; right: 0; bottom: 0;
        padding: 1.8mm 4mm;
        background: #064e3b;
        color: #fff;
        font-size: 6pt;
        height: 6mm;
    }
    .front-footer table { width: 100%; border-collapse: collapse; }
    .front-footer td { vertical-align: middle; padding: 0; color: #fff; }
    .front-footer td.l { font-weight: bold; letter-spacing: 0.5px; }
    .front-footer td.r {
        text-align: right;
        color: #fcd34d;
        font-family: 'DejaVu Sans Mono', monospace;
        font-weight: bold;
    }

    /* ============== BACK ==============
       Layout: header (8mm) → emergency info (24mm) →
               stamp+signature row (16mm) → footer (6mm)
       Stamp and signature live HERE because that's where authenticity marks
       traditionally go, not crowding the face data. */
    .back-header {
        padding: 2mm 4mm 1.5mm;
        border-bottom: 1px solid #e2e8f0;
        text-align: center;
        height: 8mm;
    }
    .back-title {
        font-size: 6.8pt;
        font-weight: bold;
        color: #064e3b;
        letter-spacing: 2.5px;
        text-transform: uppercase;
    }
    .back-subtitle {
        font-size: 5.5pt;
        color: #64748b;
        margin-top: 0.4mm;
    }

    .back-body {
        padding: 1.5mm 4mm;
        height: 30mm;
    }
    .back-row {
        display: table;
        width: 100%;
        padding: 0.5mm 0;
        font-size: 6.5pt;
    }
    .back-row + .back-row { border-top: 0.4px dotted #e2e8f0; }
    .back-row-label {
        display: table-cell;
        width: 22mm;
        color: #94a3b8;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        font-size: 5.8pt;
    }
    .back-row-value {
        display: table-cell;
        color: #0f172a;
        font-weight: 600;
    }

    /* Authenticity row — stamp on left, QR center, signature on right */
    .back-auth {
        position: absolute;
        left: 0; right: 0; bottom: 6mm;
        height: 18mm;
        padding: 1mm 4mm;
    }
    .back-auth table { width: 100%; height: 100%; border-collapse: collapse; }
    .back-auth td {
        vertical-align: bottom;
        padding: 0;
    }
    .back-auth td.stamp { width: 30%; text-align: center; }
    .back-auth td.qr    { width: 40%; text-align: center; vertical-align: middle; }
    .back-auth td.sig   { width: 30%; text-align: center; }

    .stamp-img { height: 15mm; }
    .stamp-img img { max-height: 15mm; max-width: 28mm; }
    .stamp-cap, .sig-cap {
        margin-top: 0.5mm;
        padding-top: 0.5mm;
        border-top: 0.6px solid #475569;
        font-size: 5.5pt;
        color: #475569;
        font-weight: bold;
        letter-spacing: 0.4px;
        text-transform: uppercase;
    }
    .sig-img { height: 9mm; padding-top: 4mm; }
    .sig-img img { max-height: 9mm; max-width: 28mm; }
    .sig-img .placeholder {
        font-size: 5pt;
        color: #94a3b8;
        font-style: italic;
        line-height: 9mm;
    }

    .back-qr-box {
        display: inline-block;
        width: 16mm; height: 16mm;
        border: 0.8px solid #0f172a;
        padding: 0.8mm;
        background: #fff;
        margin-bottom: 1.5mm;
    }
    .qr-grid { width: 100%; height: 100%; border-collapse: collapse; table-layout: fixed; }
    .qr-grid td { padding: 0; height: 1.2mm; }
    .qr-grid td.on  { background: #0f172a; }
    .qr-grid td.off { background: #fff; }

    .back-footer {
        position: absolute;
        left: 0; right: 0; bottom: 0;
        padding: 1.5mm 4mm;
        background: #064e3b;
        color: #fff;
        font-size: 5.5pt;
        height: 6mm;
    }
    .back-footer table { width: 100%; border-collapse: collapse; }
    .back-footer td { vertical-align: middle; padding: 0; color: #fff; }
    .back-footer td.l { font-weight: bold; letter-spacing: 0.3px; }
    .back-footer td.r { text-align: right; color: #fcd34d; }
</style>
</head>
<body>
<div class="page-title">Student Identification Card · {{ $session }}</div>

<!-- ============ FRONT ============ -->
<div class="card front">
    <div class="front-header">
        <table>
            <tr>
                <td style="width:9mm;">
                    <div class="front-logo">
                        @if($logoPath)
                            <img src="{{ $logoPath }}" alt="">
                        @else
                            {{ strtoupper(substr($school?->name ?? 'S', 0, 1)) }}
                        @endif
                    </div>
                </td>
                <td class="front-school-cell">
                    <div class="front-school-name">{{ $school?->name ?? 'School Name' }}</div>
                    <div class="front-school-tagline">Excellence · Discipline · Honor</div>
                </td>
            </tr>
        </table>
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
            <div class="front-name">{{ $student->name }}</div>
            <div class="front-father">S/o {{ $student->father_name ?? '—' }}</div>
            <div class="front-divider"></div>

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
    </div>

    <div class="front-footer">
        <table>
            <tr>
                <td class="l">VALID FOR SESSION {{ $session }}</td>
                <td class="r">{{ $school?->code ?? '' }}</td>
            </tr>
        </table>
    </div>
</div>

<div class="between">FOLD HERE</div>

<!-- ============ BACK ============ -->
<div class="card back">
    <div class="back-header">
        <div class="back-title">Identification &amp; Emergency Information</div>
        <div class="back-subtitle">If found, please return to the school office</div>
    </div>

    <div class="back-body">
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
            <div class="back-row-value">{{ \Illuminate\Support\Str::limit($student->address ?? '—', 55) }}</div>
        </div>
    </div>

    {{-- Authenticity row — stamp left, QR centre, signature right --}}
    <div class="back-auth">
        <table>
            <tr>
                <td class="stamp">
                    <div class="stamp-img">
                        @if($stampPath)
                            <img src="{{ $stampPath }}" alt="">
                        @else
                            <span style="font-size:5pt;color:#94a3b8;font-style:italic;line-height:15mm;">— stamp —</span>
                        @endif
                    </div>
                    <div class="stamp-cap">School Stamp</div>
                </td>
                <td class="qr">
                    <div class="back-qr-box">
                        <table class="qr-grid">
                            @for ($r = 0; $r < 12; $r++)
                                <tr>
                                    @for ($c = 0; $c < 12; $c++)
                                        @php
                                            $idx = $r * 12 + $c;
                                            $on = isset($bits[$idx]) && $bits[$idx] === '#';
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
                </td>
                <td class="sig">
                    <div class="sig-img">
                        @if($signaturePath)
                            <img src="{{ $signaturePath }}" alt="">
                        @else
                            <div class="placeholder">— signature —</div>
                        @endif
                    </div>
                    <div class="sig-cap">Principal</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="back-footer">
        <table>
            <tr>
                <td class="l">{{ \Illuminate\Support\Str::limit($school?->address ?? '', 50) }}</td>
                <td class="r">{{ $school?->phone ?? '' }}</td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
