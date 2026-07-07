@php
    $logoPath = !empty($school->logo) && $school?->getLogoAbsolutePath()
        ? $school?->getLogoAbsolutePath() : null;
    $sigPath = $school?->resolveAssetPath('principal_signature');
    $principalName = !empty($school->principal_name)
        ? $school->principal_name
        : ($school->principal?->name ?? null);

    $days = [
        'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday',
        'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday',
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Class Timetable — {{ $section->schoolClass->name }} {{ $section->name }}</title>
<style>
    @page { size: A4 landscape; margin: 10mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; line-height: 1.3; }

    .frame { border: 2px solid #6d28d9; padding: 5px; }
    .frame-inner { border: 1px solid #6d28d9; padding: 8px; }

    .hdr { display: table; width: 100%; padding-bottom: 6px; border-bottom: 2px solid #6d28d9; margin-bottom: 8px; }
    .hdr-logo { display: table-cell; width: 60px; vertical-align: middle; }
    .hdr-logo img { width: 55px; height: 55px; object-fit: contain; }
    .logo-ph { width: 55px; height: 55px; background: #6d28d9; border-radius: 50%; display: inline-block; line-height: 55px; text-align: center; color: #fff; font-size: 20pt; font-weight: bold; }
    .hdr-center { display: table-cell; text-align: center; vertical-align: middle; padding: 0 10px; }
    .sch-tag { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 2px; }
    .sch-name { font-size: 15pt; font-weight: bold; color: #6d28d9; text-transform: uppercase; line-height: 1.05; }
    .sch-addr { font-size: 8pt; color: #475569; margin-top: 2px; }

    .title-bar { background: #6d28d9; color: #fff; text-align: center; padding: 5px 10px; margin-bottom: 8px; font-size: 11pt; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; }

    .meta { display: table; width: 100%; margin-bottom: 8px; font-size: 9pt; }
    .meta-l { display: table-cell; }
    .meta-r { display: table-cell; text-align: right; color: #64748b; font-size: 8pt; }
    .meta-l strong { color: #6d28d9; }

    table.tt { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
    table.tt th, table.tt td { border: 1px solid #cbd5e1; padding: 5px 4px; vertical-align: middle; }
    table.tt th { background: #f3f0ff; color: #4c1d95; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; font-weight: bold; }
    table.tt th.slot-h { width: 110px; text-align: left; padding-left: 8px; }

    td.slot { text-align: left; padding-left: 8px; background: #faf8ff; }
    td.slot .nm { font-weight: bold; color: #4c1d95; font-size: 9pt; }
    td.slot .tm { font-size: 7pt; color: #64748b; font-family: monospace; }

    td.cell { text-align: center; }
    td.cell .sub { font-weight: bold; font-size: 9pt; color: #1e293b; }
    td.cell .tch { font-size: 7.5pt; color: #64748b; margin-top: 1px; }

    tr.brk td { background: #fef3c7 !important; }
    tr.brk td.slot { background: #fde68a !important; }
    td.brk-cell { color: #92400e; font-weight: bold; text-transform: uppercase; font-size: 8pt; letter-spacing: 1px; }

    td.no-class { background: #f8fafc; color: #94a3b8; font-style: italic; font-size: 7pt; }
    td.free { color: #94a3b8; font-size: 7pt; font-style: italic; }

    .sig-row { display: table; width: 100%; margin-top: 14px; padding-top: 10px; }
    .sig-cell { display: table-cell; width: 33.33%; text-align: center; vertical-align: bottom; padding: 0 8px; }
    .sig-img { height: 28px; margin-bottom: 2px; }
    .sig-line { border-top: 1px solid #334155; width: 70%; margin: 0 auto; padding-top: 3px; font-size: 8pt; }
    .sig-role { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-top: 1px; }

    .ftr { margin-top: 6px; font-size: 7pt; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
<div class="frame"><div class="frame-inner">

<div class="hdr">
    <div class="hdr-logo">
        @if($logoPath)
            <img src="{{ $logoPath }}" alt="">
        @else
            <span class="logo-ph">{{ strtoupper(substr($school?->name ?? 'S', 0, 1)) }}</span>
        @endif
    </div>
    <div class="hdr-center">
        <div class="sch-tag">Class Timetable</div>
        <div class="sch-name">{{ $school?->name ?? 'School' }}</div>
        @if(!empty($school?->address))
            <div class="sch-addr">{{ $school->address }}</div>
        @endif
    </div>
</div>

<div class="title-bar">
    {{ $section->schoolClass->name }} &mdash; Section {{ $section->name }}
</div>

<div class="meta">
    <div class="meta-l">
        <strong>Class:</strong> {{ $section->schoolClass->name }} &nbsp;·&nbsp;
        <strong>Section:</strong> {{ $section->name }}
    </div>
    <div class="meta-r">
        Generated {{ now()->format('d M Y, h:i A') }}
    </div>
</div>

<table class="tt">
    <thead>
        <tr>
            <th class="slot-h">Slot</th>
            @foreach($days as $code => $label)
                <th>{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($slots as $slot)
            @php
                $isPeriod = $slot->type === 'period';
                $slotDays = $slot->weekdays ?: array_keys($days);
            @endphp
            <tr class="{{ $isPeriod ? '' : 'brk' }}">
                <td class="slot">
                    <div class="nm">{{ $slot->name }}</div>
                    <div class="tm">{{ substr($slot->starts_at, 0, 5) }}–{{ substr($slot->ends_at, 0, 5) }}</div>
                </td>
                @foreach($days as $code => $label)
                    @php
                        $applies = in_array($code, $slotDays, true);
                        $entry = $applies ? ($entries[$code . '|' . $slot->id] ?? null) : null;
                    @endphp
                    @if(!$applies)
                        <td class="no-class">—</td>
                    @elseif(!$isPeriod)
                        <td class="brk-cell">{{ $slot->type }}</td>
                    @elseif($entry)
                        @php
                            $subjLabel = $entry->subject?->code
                                ?: \Illuminate\Support\Str::limit($entry->subject?->name ?? '—', 14, '…');
                            $tchLabel = $entry->teacher
                                ? \Illuminate\Support\Str::limit($entry->teacher->name, 16, '…')
                                : 'No teacher';
                        @endphp
                        <td class="cell">
                            <div class="sub" title="{{ $entry->subject?->name }}">{{ $subjLabel }}</div>
                            <div class="tch" title="{{ $entry->teacher?->name }}">{{ $tchLabel }}</div>
                        </td>
                    @else
                        <td class="free">free</td>
                    @endif
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>

<div class="sig-row">
    <div class="sig-cell">
        <div class="sig-line">Class Teacher</div>
        <div class="sig-role">Signature</div>
    </div>
    <div class="sig-cell">
        @if($sigPath)
            <img src="{{ $sigPath }}" class="sig-img" alt="">
        @endif
        <div class="sig-line">{{ $principalName ?? 'Principal' }}</div>
        <div class="sig-role">Principal</div>
    </div>
    <div class="sig-cell">
        <div class="sig-line">School Stamp</div>
        <div class="sig-role">Date: {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

<div class="ftr">— Official school timetable. Subject to change. —</div>

</div></div>
</body>
</html>
