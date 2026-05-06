@php
    // ─── Setup ────────────────────────────────────────────────────────
    // The legacy template had 4 disparate layouts; this single elegant design
    // replaces all of them. The orientation + colour fields stay editable per
    // template so admins can tune brand colours without touching markup.
    $orientation = $template->orientation ?? 'landscape';
    $primary = $template->primary_color ?? '#0f3d2e';   // deep teal-green
    $accent = $template->accent_color  ?? '#b8860b';    // antique gold

    $logoPath = (!empty($school->logo ?? null) && file_exists(public_path('storage/' . $school->logo)))
        ? public_path('storage/' . $school->logo) : null;
    $signaturePath = (!empty($school->principal_signature ?? null) && file_exists(public_path('storage/' . $school->principal_signature)))
        ? public_path('storage/' . $school->principal_signature) : null;
    $stampPath = (!empty($school->school_stamp ?? null) && file_exists(public_path('storage/' . $school->school_stamp)))
        ? public_path('storage/' . $school->school_stamp) : null;
    $officerSigPath = (!empty($school->exam_officer_signature ?? null) && file_exists(public_path('storage/' . $school->exam_officer_signature)))
        ? public_path('storage/' . $school->exam_officer_signature) : null;

    $body = $template->body_text ?? 'in recognition of {grade} performance in <b>{exam_name}</b>.';
    $replacements = [
        '{student_name}' => $data['student_name'] ?? '',
        '{rank}' => $data['rank'] ?? '',
        '{percentage}' => $data['percentage'] ?? '',
        '{grade}' => $data['grade'] ?? '',
        '{exam_name}' => $data['exam_name'] ?? '',
        '{academic_session}' => $data['academic_session'] ?? '',
        '{class_name}' => $data['class_name'] ?? '',
        '{section_name}' => $data['section_name'] ?? '',
        '{subject_name}' => $data['subject_name'] ?? '',
        '{school_name}' => $data['school_name'] ?? ($school->name ?? ''),
    ];
    foreach ($replacements as $k => $v) {
        $body = str_replace($k, '<b>' . e($v) . '</b>', $body);
    }

    $typeSubtitles = [
        'merit'               => 'OF MERIT',
        'subject_topper'      => 'OF EXCELLENCE',
        'pass'                => 'OF ACHIEVEMENT',
        'special_achievement' => 'OF DISTINCTION',
        'participation'       => 'OF PARTICIPATION',
        'custom'              => 'OF RECOGNITION',
    ];
    $subtitle = $typeSubtitles[$template->type ?? 'custom'] ?? 'OF RECOGNITION';

    $W = $orientation === 'landscape' ? 297 : 210;
    $H = $orientation === 'landscape' ? 210 : 297;

    $issueDate = $data['date'] ?? now()->format('d F Y');
    $certNo = $data['certificate_number'] ?? '';
    // $exam may not be defined when template is being previewed without context.
    // Use null-safe access + fall back to the school's default exam officer name.
    $exam = $exam ?? null;
    $officerName = ($exam?->examController?->name) ?? ($school->exam_officer_name ?? null);
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { size: A4 {{ $orientation }}; margin: 0; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { width: 100%; height: 100%; }
    body { font-family: 'DejaVu Sans', sans-serif; color: #1f2937; }

    .sheet {
        position: relative;
        width: {{ $W }}mm;
        height: {{ $H }}mm;
        overflow: hidden;
        background: #fdfcf7;          /* warm off-white, like real certificate paper */
    }

    /* ─── Triple-line border frame ───
       Outer thick line + small gap + thin inner accent line. The classic
       "official document" frame, but we keep it understated so the content
       breathes. */
    .border-outer {
        position: absolute;
        top: 6mm; left: 6mm; right: 6mm; bottom: 6mm;
        border: 2.5px solid {{ $primary }};
        z-index: 1;
    }
    .border-inner {
        position: absolute;
        top: 9mm; left: 9mm; right: 9mm; bottom: 9mm;
        border: 0.6px solid {{ $accent }};
        z-index: 2;
    }
    /* Decorative gold corner flourishes — small angle brackets */
    .corner {
        position: absolute;
        width: 16mm; height: 16mm;
        border: 1.5px solid {{ $accent }};
        z-index: 3;
    }
    .c-tl { top: 12mm; left: 12mm;  border-right: 0; border-bottom: 0; }
    .c-tr { top: 12mm; right: 12mm; border-left: 0;  border-bottom: 0; }
    .c-bl { bottom: 12mm; left: 12mm;  border-right: 0; border-top: 0; }
    .c-br { bottom: 12mm; right: 12mm; border-left: 0;  border-top: 0; }

    /* Watermark — school logo at very low opacity, centred behind everything.
       Adds weight to the page without competing for attention. */
    .watermark {
        position: absolute;
        top: 50%; left: 50%;
        width: 110mm; height: 110mm;
        margin-top: -55mm; margin-left: -55mm;
        opacity: 0.04;
        z-index: 1;
        text-align: center;
    }
    .watermark img { width: 100%; height: 100%; object-fit: contain; }

    /* ─── Header: logo + school name ─── */
    .header {
        position: absolute;
        top: 18mm; left: 0; right: 0;
        text-align: center;
        z-index: 5;
    }
    .header-logo {
        width: 18mm; height: 18mm;
        margin: 0 auto 3mm;
        text-align: center;
    }
    .header-logo img { max-width: 100%; max-height: 100%; }
    .header-logo .ph {
        width: 18mm; height: 18mm; line-height: 18mm;
        background: {{ $primary }}; color: #fff;
        border-radius: 50%;
        font-size: 16pt; font-weight: bold;
        display: inline-block;
    }
    .school-name {
        font-size: 11pt;
        font-weight: bold;
        color: {{ $primary }};
        letter-spacing: 4px;
        text-transform: uppercase;
    }
    .school-tag {
        font-size: 7.5pt;
        color: #6b7280;
        letter-spacing: 2px;
        text-transform: uppercase;
        margin-top: 0.5mm;
    }

    /* Decorative divider line under header */
    .divider {
        margin: 3mm auto 0;
        width: 60mm;
        position: relative;
    }
    .divider .line {
        height: 0.6px;
        background: {{ $accent }};
    }
    .divider .ornament {
        position: absolute;
        top: -5px;
        left: 50%;
        margin-left: -10px;
        width: 20px;
        text-align: center;
        background: #fdfcf7;
        font-size: 9pt;
        color: {{ $accent }};
        line-height: 1;
    }

    /* ─── Main content (title, name, citation) ─── */
    .body {
        position: absolute;
        top: {{ $orientation === 'landscape' ? '70mm' : '95mm' }};
        left: 25mm; right: 25mm;
        text-align: center;
        z-index: 5;
    }
    .title {
        font-family: 'DejaVu Serif', serif;
        font-size: {{ $orientation === 'landscape' ? '46pt' : '38pt' }};
        font-weight: bold;
        color: {{ $primary }};
        letter-spacing: 10px;
        line-height: 1;
    }
    .subtitle {
        font-size: 12pt;
        color: {{ $accent }};
        letter-spacing: 8px;
        font-weight: bold;
        margin-top: 4mm;
    }
    .presented {
        font-size: 10pt;
        color: #6b7280;
        margin-top: 8mm;
        font-style: italic;
    }
    .name {
        font-family: 'DejaVu Serif', serif;
        font-style: italic;
        font-size: {{ $orientation === 'landscape' ? '36pt' : '30pt' }};
        color: {{ $primary }};
        margin: 3mm 0 1mm;
        line-height: 1.05;
    }
    .name-underline {
        margin: 0 auto 5mm;
        width: 110mm;
        height: 0.6px;
        background: {{ $accent }};
    }
    .citation {
        font-size: 11pt;
        line-height: 1.6;
        color: #374151;
        max-width: 200mm;
        margin: 0 auto;
    }
    .citation b { color: {{ $primary }}; font-weight: bold; }

    /* ─── Bottom row: 3 columns
           Date (left) · Principal+stamp (center) · Exam Controller (right)  ─── */
    .footer {
        position: absolute;
        bottom: 22mm;
        left: 25mm; right: 25mm;
        z-index: 6;
    }
    .footer table {
        width: 100%;
        border-collapse: collapse;
    }
    .footer td {
        vertical-align: bottom;
        text-align: center;
        padding: 0;
        width: 33.33%;
    }
    /* Wrap that holds signature image + stamp, shared by all three cells */
    .sig-wrap {
        position: relative;
        height: 18mm;
    }
    .sig-img {
        position: absolute;
        left: 50%; bottom: 0;
        transform: translateX(-50%);
        z-index: 2;
    }
    .sig-img img { max-height: 16mm; max-width: 50mm; }
    .sig-img .placeholder {
        font-size: 7pt; color: #cbd5e1; font-style: italic;
        line-height: 16mm; display: inline-block;
    }
    /* Stamp overlays the principal signature with semi-transparent fill —
       like a real document. Sits above z-index of sig but doesn't obscure it. */
    .stamp-overlay {
        position: absolute;
        left: 50%; bottom: -3mm;
        transform: translateX(-50%);
        opacity: 0.55;
        z-index: 1;
        width: 28mm; height: 28mm;
    }
    .stamp-overlay img { width: 100%; height: 100%; object-fit: contain; }

    .sig-line {
        border-top: 1px solid #374151;
        padding-top: 1.5mm;
        font-size: 9.5pt;
        font-weight: bold;
        color: #1f2937;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    .sig-name {
        font-size: 10pt;
        font-weight: bold;
        color: {{ $primary }};
        margin-top: 0.5mm;
        font-style: italic;
    }
    .sig-role {
        font-size: 7.5pt;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        margin-top: 0.5mm;
    }

    /* ─── Bottom meta strip: certificate number + verification ─── */
    .meta {
        position: absolute;
        bottom: 8mm; left: 18mm; right: 18mm;
        display: table;
        width: calc(100% - 36mm);
        font-size: 7pt;
        color: #9ca3af;
        z-index: 10;
    }
    .meta-l { display: table-cell; text-align: left; font-weight: bold; }
    .meta-l b { color: {{ $primary }}; font-family: 'DejaVu Sans Mono', monospace; }
    .meta-r { display: table-cell; text-align: right; }

    /* Subtle tag in top corner declaring the certificate type */
    .type-tag {
        position: absolute;
        top: 12mm; right: 14mm;
        background: {{ $accent }};
        color: #fff;
        font-size: 7pt;
        font-weight: bold;
        letter-spacing: 2px;
        padding: 1.5mm 4mm;
        border-radius: 1mm;
        text-transform: uppercase;
        z-index: 6;
    }
</style>
</head>
<body>
<div class="sheet">

    {{-- Watermark behind everything --}}
    @if($logoPath)
        <div class="watermark"><img src="{{ $logoPath }}" alt=""></div>
    @endif

    {{-- Triple-line frame --}}
    <div class="border-outer"></div>
    <div class="border-inner"></div>
    <div class="corner c-tl"></div>
    <div class="corner c-tr"></div>
    <div class="corner c-bl"></div>
    <div class="corner c-br"></div>

    {{-- Type tag (Merit, Excellence, etc.) --}}
    <div class="type-tag">{{ $subtitle }}</div>

    {{-- Header --}}
    <div class="header">
        <div class="header-logo">
            @if($logoPath)
                <img src="{{ $logoPath }}" alt="">
            @else
                <span class="ph">{{ strtoupper(substr($school->name ?? 'S', 0, 1)) }}</span>
            @endif
        </div>
        <div class="school-name">{{ $school->name ?? 'School Name' }}</div>
        @if(!empty($school->address))
            <div class="school-tag">{{ $school->address }}</div>
        @endif
        <div class="divider">
            <div class="line"></div>
            <div class="ornament">&#10070;</div>
        </div>
    </div>

    {{-- Main content --}}
    <div class="body">
        <div class="title">{{ $template->title_text ?? 'CERTIFICATE' }}</div>
        <div class="subtitle">{{ $subtitle }}</div>

        <div class="presented">This certificate is proudly presented to</div>
        <div class="name">{{ $data['student_name'] ?? 'Student Name' }}</div>
        <div class="name-underline"></div>

        <div class="citation">{!! $body !!}</div>
    </div>

    {{-- Bottom signature row --}}
    <div class="footer">
        <table>
            <tr>
                {{-- LEFT: Date of Issue --}}
                <td>
                    <div class="sig-wrap">
                        <div class="sig-img" style="font-family:'DejaVu Serif',serif;font-size:13pt;color:{{ $primary }};font-weight:bold;line-height:18mm;">
                            {{ $issueDate }}
                        </div>
                    </div>
                    <div class="sig-line">Date of Issue</div>
                    <div class="sig-role">Issued at {{ $school->name ? \Illuminate\Support\Str::words($school->name, 3, '...') : 'School' }}</div>
                </td>

                {{-- CENTER: Principal signature with stamp overlaid --}}
                <td>
                    <div class="sig-wrap">
                        @if($stampPath)
                            <div class="stamp-overlay"><img src="{{ $stampPath }}" alt=""></div>
                        @endif
                        <div class="sig-img">
                            @if($signaturePath)
                                <img src="{{ $signaturePath }}" alt="">
                            @else
                                <span class="placeholder">— signature —</span>
                            @endif
                        </div>
                    </div>
                    <div class="sig-line">Principal</div>
                    @if(!empty($school->principal_name))
                        <div class="sig-name">{{ $school->principal_name }}</div>
                    @endif
                </td>

                {{-- RIGHT: Exam Controller / Officer (only if assigned or school has officer) --}}
                <td>
                    <div class="sig-wrap">
                        <div class="sig-img">
                            @if($officerSigPath)
                                <img src="{{ $officerSigPath }}" alt="">
                            @else
                                <span class="placeholder">— signature —</span>
                            @endif
                        </div>
                    </div>
                    <div class="sig-line">{{ $exam?->examController ? 'Exam Controller' : 'Examination Officer' }}</div>
                    @if($officerName)
                        <div class="sig-name">{{ $officerName }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- Bottom meta: certificate number + verification --}}
    <div class="meta">
        <div class="meta-l">
            @if($certNo)Certificate No: <b>{{ $certNo }}</b>@endif
        </div>
        <div class="meta-r">
            @if(!empty($data['verification_code']))
                Verify at {{ rtrim(config('app.url'), '/') }}/verify/certificate/{{ $data['verification_code'] }}
            @endif
        </div>
    </div>
</div>
</body>
</html>
