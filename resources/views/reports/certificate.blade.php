@php
    $orientation = $template->orientation ?? 'landscape';
    $layout = $template->design_layout ?? 'modern';
    $primary = $template->primary_color ?? '#1e3a8a';
    $accent = $template->accent_color ?? '#f59e0b';

    $logoPath = (!empty($school->logo ?? null) && file_exists(public_path('storage/' . $school->logo)))
        ? public_path('storage/' . $school->logo) : null;
    $signaturePath = (!empty($school->principal_signature ?? null) && file_exists(public_path('storage/' . $school->principal_signature)))
        ? public_path('storage/' . $school->principal_signature) : null;
    $stampPath = (!empty($school->school_stamp ?? null) && file_exists(public_path('storage/' . $school->school_stamp)))
        ? public_path('storage/' . $school->school_stamp) : null;

    $body = $template->body_text ?? '';
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
        '{school_name}' => $data['school_name'] ?? '',
    ];
    foreach ($replacements as $k => $v) {
        $body = str_replace($k, '<b>' . e($v) . '</b>', $body);
    }

    $typeSubtitles = [
        'merit' => 'OF MERIT',
        'subject_topper' => 'OF EXCELLENCE',
        'pass' => 'OF ACHIEVEMENT',
        'special_achievement' => 'OF DISTINCTION',
        'participation' => 'OF PARTICIPATION',
        'custom' => 'OF RECOGNITION',
    ];
    $subtitle = $typeSubtitles[$template->type ?? 'custom'] ?? 'OF RECOGNITION';

    // Canvas dimensions — A4 exact
    $W = $orientation === 'landscape' ? 297 : 210;
    $H = $orientation === 'landscape' ? 210 : 297;
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
        background: #ffffff;
    }

    @if ($layout === 'modern')
        {{-- ================= MODERN CORPORATE (blue/gold geometric ribbons) ================= --}}
        .deco { position: absolute; z-index: 1; }
        .deco-tl-1 { top: 0; left: 0; width: 120mm; height: 18mm; background: {{ $primary }}; transform: skewX(-30deg) translateX(-40mm); }
        .deco-tr-1 { top: 0; right: 0; width: 90mm; height: 55mm; background: {{ $primary }}; transform: skewX(-30deg) translateX(30mm); }
        .deco-tr-2 { top: 0; right: 0; width: 50mm; height: 45mm; background: {{ $accent }}; transform: skewX(-30deg) translateX(40mm); }
        .deco-bl-1 { bottom: 0; left: 0; width: 90mm; height: 55mm; background: {{ $primary }}; transform: skewX(-30deg) translateX(-30mm); }
        .deco-bl-2 { bottom: 0; left: 0; width: 50mm; height: 45mm; background: {{ $accent }}; transform: skewX(-30deg) translateX(-40mm); }
        .deco-br-1 { bottom: 0; right: 0; width: 120mm; height: 18mm; background: {{ $primary }}; transform: skewX(-30deg) translateX(40mm); }

        .frame {
            position: absolute;
            top: 10mm; left: 15mm; right: 15mm; bottom: 10mm;
            background: #fff;
            border: 2px solid #d1d5db;
            z-index: 2;
        }
        .frame-inner {
            position: absolute;
            top: 3mm; left: 3mm; right: 3mm; bottom: 3mm;
            border: 1px solid {{ $primary }};
        }

        .content { position: absolute; top: 10mm; left: 15mm; right: 15mm; bottom: 60mm; z-index: 3; padding: 12mm 12mm 0; text-align: center; }

        .title { font-family: 'DejaVu Serif', serif; font-size: 42pt; font-weight: bold; letter-spacing: 8px; color: #1f2937; margin-top: 6mm; }
        .subtitle { font-size: 11pt; letter-spacing: 8px; color: {{ $primary }}; margin-top: 2mm; font-weight: bold; }
        .diamonds { margin: 4mm auto 3mm; letter-spacing: 2px; color: {{ $accent }}; font-size: 12pt; }
        .presented { font-size: 10.5pt; color: #6b7280; margin-bottom: 2mm; font-style: italic; }
        .name { font-family: 'DejaVu Serif', serif; font-style: italic; font-size: 32pt; color: {{ $primary }}; margin: 2mm 0 3mm; line-height: 1; }
        .citation { font-size: 10pt; line-height: 1.5; color: #4b5563; max-width: 200mm; margin: 0 auto; }

        .sig-left { position: absolute; bottom: 18mm; left: 36mm; width: 55mm; text-align: center; z-index: 3; }
        .sig-right { position: absolute; bottom: 18mm; right: 36mm; width: 55mm; text-align: center; z-index: 3; }
        .sig-img { height: 12mm; line-height: 12mm; overflow: hidden; }
        .sig-img img { max-height: 11mm; max-width: 45mm; vertical-align: bottom; }
        .sig-bar { border-top: 1.2px solid #374151; padding-top: 1.5mm; }
        .sig-label { font-size: 8pt; letter-spacing: 1.5px; color: #6b7280; text-transform: uppercase; }
        .sig-name { font-size: 9.5pt; font-weight: bold; color: #1f2937; margin-top: 0.5mm; }
        .stamp-anchor {
            position: absolute;
            bottom: 22mm;
            left: 50%;
            margin-left: -11mm;
            width: 22mm;
            height: 22mm;
            text-align: center;
            overflow: hidden;
            z-index: 5;
        }
        .stamp-anchor img { max-height: 22mm; max-width: 22mm; opacity: 0.9; }
        .stamp-label-center {
            position: absolute;
            bottom: 18mm;
            left: 50%;
            margin-left: -15mm;
            width: 30mm;
            text-align: center;
            z-index: 3;
        }
        .stamp-label-center .sig-bar { border-top: 1.2px solid #374151; padding-top: 1.5mm; }
        .stamp-label-center .sig-label { font-size: 8pt; color: #6b7280; text-transform: uppercase; letter-spacing: 1.5px; }

    @elseif ($layout === 'gold')
        {{-- ================= GOLD RIBBON (orange + dark corner triangles + medal) ================= --}}
        .corner-tl {
            position: absolute; top: 0; left: 0; width: 0; height: 0;
            border-top: 55mm solid #1e293b; border-right: 45mm solid transparent;
            z-index: 1;
        }
        .corner-tl-accent {
            position: absolute; top: 0; left: 0; width: 0; height: 0;
            border-top: 40mm solid {{ $accent }}; border-right: 28mm solid transparent;
            z-index: 2;
        }
        .corner-br {
            position: absolute; bottom: 0; right: 0; width: 0; height: 0;
            border-bottom: 55mm solid #1e293b; border-left: 45mm solid transparent;
            z-index: 1;
        }
        .corner-br-accent {
            position: absolute; bottom: 0; right: 0; width: 0; height: 0;
            border-bottom: 40mm solid {{ $accent }}; border-left: 28mm solid transparent;
            z-index: 2;
        }

        .medal {
            position: absolute; top: 16mm; right: 22mm;
            width: 34mm; height: 34mm; border-radius: 50%;
            background: radial-gradient(circle, {{ $accent }} 0%, #b45309 100%);
            border: 3px solid {{ $accent }};
            text-align: center;
            color: #7c2d12;
            font-weight: bold;
            padding-top: 10mm;
            font-size: 7.5pt;
            line-height: 1.15;
            z-index: 5;
        }
        .medal b { display: block; font-size: 10pt; margin: 0.5mm 0; }

        .content { position: absolute; top: 22mm; left: 22mm; right: 22mm; bottom: 55mm; z-index: 3; }

        .title { font-size: 50pt; font-weight: bold; color: {{ $accent }}; letter-spacing: 2px; line-height: 1; font-family: 'DejaVu Sans', sans-serif; }
        .subtitle { font-size: 14pt; font-weight: bold; color: #1e293b; margin-top: 2mm; letter-spacing: 5px; }
        .awarded { font-size: 12pt; color: #1f2937; margin-top: 8mm; }
        .name { font-family: 'DejaVu Serif', serif; font-style: italic; font-size: 40pt; color: #1e293b; margin: 3mm 0; line-height: 1; }
        .citation { font-size: 10.5pt; line-height: 1.5; color: #374151; max-width: 200mm; margin-top: 4mm; }

        .sig-left { position: absolute; bottom: 20mm; left: 32mm; width: 60mm; z-index: 4; }
        .sig-right { position: absolute; bottom: 20mm; right: 32mm; width: 60mm; z-index: 4; }
        .sig-img { height: 13mm; line-height: 13mm; text-align: center; overflow: hidden; }
        .sig-img img { max-height: 13mm; max-width: 45mm; vertical-align: bottom; }
        .sig-bar { border-top: 1.5px solid #1f2937; padding-top: 1.5mm; text-align: center; }
        .sig-name { font-size: 10.5pt; font-weight: bold; color: #1e293b; }
        .sig-label { font-size: 8.5pt; color: #4b5563; margin-top: 0.5mm; }
        .stamp-badge {
            position: absolute;
            bottom: 22mm;
            left: 50%;
            margin-left: -11mm;
            width: 22mm;
            height: 22mm;
            text-align: center;
            overflow: hidden;
            z-index: 5;
        }
        .stamp-badge img { max-height: 22mm; max-width: 22mm; opacity: 0.9; }

    @elseif ($layout === 'graduation')
        {{-- ================= GRADUATION (navy corners + cap icon + gold medal ribbon) ================= --}}
        .corner-tl {
            position: absolute; top: 0; left: 0;
            width: 55mm; height: 45mm;
            background: {{ $primary }};
            transform: skewY(10deg) translateY(-18mm);
            z-index: 1;
        }
        .corner-br {
            position: absolute; bottom: 0; right: 0;
            width: 55mm; height: 45mm;
            background: {{ $primary }};
            transform: skewY(10deg) translateY(18mm);
            z-index: 1;
        }

        .frame {
            position: absolute;
            top: 10mm; left: 10mm; right: 10mm; bottom: 10mm;
            border: 2px solid {{ $primary }};
            z-index: 2;
        }

        .content { position: absolute; top: 14mm; left: 25mm; right: 25mm; bottom: 52mm; z-index: 3; text-align: center; }

        .cap {
            font-size: 24pt;
            color: {{ $primary }};
            margin-bottom: 2mm;
        }
        .school-name {
            font-size: 10pt;
            font-weight: bold;
            color: #1f2937;
            letter-spacing: 3px;
            margin-bottom: 3mm;
        }
        .title { font-family: 'DejaVu Sans', sans-serif; font-size: 28pt; font-weight: bold; color: {{ $accent }}; letter-spacing: 2px; line-height: 1; }
        .subtitle { font-size: 10pt; color: #6b7280; margin-top: 2mm; }
        .name {
            font-family: 'DejaVu Serif', serif; font-size: 42pt; color: {{ $primary }};
            margin: 6mm 0 2mm; line-height: 1; font-style: italic;
        }
        .name-line {
            margin: 0 auto 4mm; width: 130mm; height: 1px; background: #1f2937;
        }
        .citation { font-size: 10.5pt; line-height: 1.5; color: #374151; max-width: 180mm; margin: 0 auto; }

        .medal-ribbon {
            position: absolute; bottom: 16mm; right: 22mm;
            width: 22mm; height: 30mm;
            z-index: 5;
            text-align: center;
        }
        .medal-ribbon .ribbon-top {
            width: 100%; height: 8mm;
            background: {{ $primary }};
        }
        .medal-ribbon .medal-circle {
            width: 18mm; height: 18mm; border-radius: 50%;
            background: radial-gradient(circle, {{ $accent }} 0%, #b45309 100%);
            margin: -2mm auto 0;
            border: 2px solid {{ $accent }};
            font-size: 14pt; color: #7c2d12; font-weight: bold;
            padding-top: 3mm;
        }

        .sig-center { position: absolute; bottom: 20mm; left: 50%; margin-left: -35mm; width: 70mm; text-align: center; z-index: 4; }
        .sig-img { height: 13mm; line-height: 13mm; overflow: hidden; }
        .sig-img img { max-height: 13mm; max-width: 50mm; vertical-align: bottom; }
        .sig-bar { border-top: 1.5px solid #1f2937; padding-top: 1.5mm; }
        .sig-name { font-size: 10.5pt; font-weight: bold; color: #1f2937; }
        .sig-label { font-size: 8.5pt; color: #6b7280; margin-top: 0.5mm; }
        .stamp-side {
            position: absolute; bottom: 20mm; left: 26mm;
            width: 22mm; height: 22mm;
            z-index: 5;
            text-align: center;
            overflow: hidden;
        }
        .stamp-side img { max-height: 22mm; max-width: 22mm; opacity: 0.9; }
        .stamp-side-label {
            position: absolute; bottom: 15mm; left: 22mm; width: 30mm;
            text-align: center; z-index: 4;
            font-size: 8pt; color: #6b7280; letter-spacing: 1.5px; text-transform: uppercase;
            border-top: 1px solid #374151; padding-top: 1.5mm;
        }

    @else
        {{-- ================= CLASSIC (traditional double border) ================= --}}
        .frame-outer {
            position: absolute;
            top: 8mm; left: 8mm; right: 8mm; bottom: 8mm;
            border: 5px double {{ $primary }};
            z-index: 1;
        }
        .frame-inner {
            position: absolute;
            top: 11mm; left: 11mm; right: 11mm; bottom: 11mm;
            border: 1px solid {{ $accent }};
            z-index: 1;
        }
        .corner { position: absolute; width: 14mm; height: 14mm; border: 2px solid {{ $accent }}; z-index: 2; }
        .c-tl { top: 10mm; left: 10mm; border-right: none; border-bottom: none; }
        .c-tr { top: 10mm; right: 10mm; border-left: none; border-bottom: none; }
        .c-bl { bottom: 10mm; left: 10mm; border-right: none; border-top: none; }
        .c-br { bottom: 10mm; right: 10mm; border-left: none; border-top: none; }

        .content { position: absolute; top: 16mm; left: 22mm; right: 22mm; bottom: 50mm; z-index: 3; text-align: center; }

        .school-name { font-size: 12pt; font-weight: bold; color: {{ $primary }}; letter-spacing: 3px; text-transform: uppercase; }
        .school-bar { margin: 2mm auto; width: 50mm; height: 1.5px; background: {{ $accent }}; }
        .title { font-family: 'DejaVu Serif', serif; font-size: 32pt; font-weight: bold; color: {{ $primary }}; letter-spacing: 4px; margin-top: 4mm; line-height: 1; }
        .subtitle { font-size: 10pt; color: {{ $accent }}; letter-spacing: 6px; font-weight: bold; margin-top: 2mm; }
        .presented { font-size: 11pt; color: #6b7280; font-style: italic; margin-top: 6mm; }
        .name { font-family: 'DejaVu Serif', serif; font-size: 26pt; font-weight: bold; color: {{ $primary }}; margin: 2mm 0 2mm; line-height: 1.1; }
        .name-bar { margin: 0 auto 3mm; width: 80mm; height: 1px; background: {{ $accent }}; }
        .citation { font-size: 11pt; line-height: 1.55; color: #374151; max-width: 190mm; margin: 0 auto; }

        .sig-left { position: absolute; bottom: 20mm; left: 32mm; width: 55mm; text-align: center; z-index: 4; }
        .sig-right { position: absolute; bottom: 20mm; right: 32mm; width: 55mm; text-align: center; z-index: 4; }
        .sig-img { height: 12mm; line-height: 12mm; overflow: hidden; }
        .sig-img img { max-height: 12mm; max-width: 45mm; vertical-align: bottom; }
        .sig-bar { border-top: 1px solid #374151; padding-top: 1.5mm; }
        .sig-name { font-size: 10pt; font-weight: bold; color: #1f2937; }
        .sig-label { font-size: 8pt; color: #6b7280; margin-top: 0.5mm; text-transform: uppercase; letter-spacing: 1px; }
        .stamp-center {
            position: absolute;
            bottom: 22mm;
            left: 50%;
            margin-left: -11mm;
            width: 22mm;
            height: 22mm;
            text-align: center;
            overflow: hidden;
            z-index: 5;
        }
        .stamp-center img { max-height: 22mm; max-width: 22mm; opacity: 0.9; }
    @endif

    /* Common logo (used by some layouts) */
    .logo {
        position: absolute;
        top: 16mm; left: 50%; margin-left: -9mm;
        width: 18mm; height: 18mm;
        text-align: center;
        z-index: 4;
    }
    .logo img { max-width: 100%; max-height: 100%; }
    .logo-ph {
        width: 100%; height: 100%; background: {{ $primary }};
        color: #fff; border-radius: 50%;
        font-size: 18pt; font-weight: bold; line-height: 18mm;
    }

    /* Footer meta bar (all layouts) */
    .meta {
        position: absolute;
        bottom: 6mm; left: 14mm; right: 14mm;
        display: table;
        width: calc(100% - 28mm);
        font-size: 7pt;
        color: #9ca3af;
        z-index: 10;
    }
    .meta-l { display: table-cell; text-align: left; }
    .meta-r { display: table-cell; text-align: right; }
</style>
</head>
<body>
<div class="sheet">

{{-- ============ MODERN ============ --}}
@if($layout === 'modern')
    <div class="deco deco-tl-1"></div>
    <div class="deco deco-tr-1"></div>
    <div class="deco deco-tr-2"></div>
    <div class="deco deco-bl-1"></div>
    <div class="deco deco-bl-2"></div>
    <div class="deco deco-br-1"></div>
    <div class="frame">
        <div class="frame-inner"></div>
    </div>
    <div class="content">
        <div class="title">{{ $template->title_text ?? 'CERTIFICATE' }}</div>
        <div class="subtitle">{{ $subtitle }}</div>
        <div class="diamonds">◆ ◆ ◆</div>
        <div class="presented">This certificate is proudly presented to</div>
        <div class="name">{{ $data['student_name'] ?? 'Student Name' }}</div>
        <div class="citation">{!! $body !!}</div>
    </div>
    <div class="sig-left">
        <div class="sig-img">@if($signaturePath)<img src="{{ $signaturePath }}" alt="">@endif</div>
        <div class="sig-bar">
            <div class="sig-label">Principal's Signature</div>
            <div class="sig-name">{{ $school->principal_name ?? '&nbsp;' }}</div>
        </div>
    </div>
    @if($stampPath)<div class="stamp-anchor"><img src="{{ $stampPath }}" alt=""></div>@endif
    <div class="stamp-label-center">
        <div class="sig-bar">
            <div class="sig-label">Official Seal</div>
        </div>
    </div>
    <div class="sig-right">
        <div class="sig-img">&nbsp;</div>
        <div class="sig-bar">
            <div class="sig-label">Date of Issue</div>
            <div class="sig-name">{{ $data['date'] ?? now()->format('d M Y') }}</div>
        </div>
    </div>

{{-- ============ GOLD ============ --}}
@elseif($layout === 'gold')
    <div class="corner-tl"></div>
    <div class="corner-tl-accent"></div>
    <div class="corner-br"></div>
    <div class="corner-br-accent"></div>
    <div class="medal">WITH<b>HONORS</b>{{ date('Y') }}</div>
    <div class="content">
        <div class="title">{{ $template->title_text ?? 'CERTIFICATE' }}</div>
        <div class="subtitle">{{ $subtitle }}</div>
        <div class="awarded">This Certificate is proudly awarded to:</div>
        <div class="name">{{ $data['student_name'] ?? 'Student Name' }}</div>
        <div class="citation">{!! $body !!}</div>
    </div>
    <div class="sig-left">
        <div class="sig-img">@if($signaturePath)<img src="{{ $signaturePath }}" alt="">@endif</div>
        <div class="sig-bar">
            <div class="sig-name">{{ $school->principal_name ?? '&nbsp;' }}</div>
            <div class="sig-label">School Principal</div>
        </div>
    </div>
    @if($stampPath)<div class="stamp-badge"><img src="{{ $stampPath }}" alt=""></div>@endif
    <div class="sig-right">
        <div class="sig-img">&nbsp;</div>
        <div class="sig-bar">
            <div class="sig-name">{{ $data['date'] ?? now()->format('d F Y') }}</div>
            <div class="sig-label">Date of Issue</div>
        </div>
    </div>

{{-- ============ GRADUATION ============ --}}
@elseif($layout === 'graduation')
    <div class="corner-tl"></div>
    <div class="corner-br"></div>
    <div class="frame"></div>
    <div class="content">
        <div class="cap">&#127891;</div>
        <div class="school-name">{{ $data['school_name'] ?? ($school->name ?? 'SCHOOL NAME') }}</div>
        <div class="title">{{ $template->title_text ?? 'CERTIFICATE OF COMPLETION' }}</div>
        <div class="subtitle">This is to acknowledge that</div>
        <div class="name">{{ $data['student_name'] ?? 'Student Name' }}</div>
        <div class="name-line"></div>
        <div class="citation">{!! $body !!}</div>
    </div>
    <div class="medal-ribbon">
        <div class="ribbon-top"></div>
        <div class="medal-circle">&#9733;</div>
    </div>
    @if($stampPath)<div class="stamp-side"><img src="{{ $stampPath }}" alt=""></div>@endif
    <div class="stamp-side-label">Official Seal</div>
    <div class="sig-center">
        <div class="sig-img">@if($signaturePath)<img src="{{ $signaturePath }}" alt="">@endif</div>
        <div class="sig-bar">
            <div class="sig-name">{{ $school->principal_name ?? '&nbsp;' }}</div>
            <div class="sig-label">Principal's Signature · {{ $data['date'] ?? now()->format('d M Y') }}</div>
        </div>
    </div>

{{-- ============ CLASSIC ============ --}}
@else
    <div class="frame-outer"></div>
    <div class="frame-inner"></div>
    <div class="corner c-tl"></div>
    <div class="corner c-tr"></div>
    <div class="corner c-bl"></div>
    <div class="corner c-br"></div>

    <div class="content">
        <div class="school-name">{{ $data['school_name'] ?? ($school->name ?? 'School Name') }}</div>
        <div class="school-bar"></div>
        <div class="title">{{ $template->title_text ?? 'Certificate of Achievement' }}</div>
        <div class="subtitle">{{ $subtitle }}</div>
        <div class="presented">This certificate is proudly presented to</div>
        <div class="name">{{ $data['student_name'] ?? 'Student Name' }}</div>
        <div class="name-bar"></div>
        <div class="citation">{!! $body !!}</div>
    </div>
    <div class="sig-left">
        <div class="sig-img">@if($signaturePath)<img src="{{ $signaturePath }}" alt="">@endif</div>
        <div class="sig-bar">
            <div class="sig-label">Principal</div>
            <div class="sig-name">{{ $school->principal_name ?? '' }}</div>
        </div>
    </div>
    <div class="sig-right">
        <div class="sig-img">&nbsp;</div>
        <div class="sig-bar">
            <div class="sig-label">Date</div>
            <div class="sig-name">{{ $data['date'] ?? now()->format('d F Y') }}</div>
        </div>
    </div>
    @if($stampPath)<div class="stamp-center"><img src="{{ $stampPath }}" alt=""></div>@endif
@endif

    <div class="meta">
        <div class="meta-l">No: <b>{{ $data['certificate_number'] ?? '' }}</b></div>
        <div class="meta-r">
            @if(!empty($data['verification_code']))
                Verify: {{ rtrim(config('app.url'), '/') }}/verify/certificate/{{ $data['verification_code'] }}
            @endif
        </div>
    </div>
</div>
</body>
</html>
