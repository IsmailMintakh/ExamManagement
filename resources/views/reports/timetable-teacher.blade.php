@php
    $logoPath = !empty($school?->logo) && file_exists(public_path('storage/' . $school->logo))
        ? public_path('storage/' . $school->logo) : null;
    $sigPath = !empty($school?->principal_signature ?? null) && file_exists(public_path('storage/' . $school->principal_signature))
        ? public_path('storage/' . $school->principal_signature) : null;
    $principalName = !empty($school?->principal_name)
        ? $school->principal_name
        : ($school?->principal?->name ?? null);

    $days = [
        'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday',
        'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday',
    ];

    // Quick load summary: count of period entries by day.
    $loadByDay = [];
    foreach ($days as $code => $_) { $loadByDay[$code] = 0; }
    foreach ($entries as $e) {
        if (isset($loadByDay[$e->weekday])) $loadByDay[$e->weekday]++;
    }
    $totalLoad = array_sum($loadByDay);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Teacher Timetable — {{ $teacher->name }}</title>
<style>
    @page { size: A4 landscape; margin: 10mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; line-height: 1.3; }

    .frame { border: 2px solid #0369a1; padding: 5px; }
    .frame-inner { border: 1px solid #0369a1; padding: 8px; }

    .hdr { display: table; width: 100%; padding-bottom: 6px; border-bottom: 2px solid #0369a1; margin-bottom: 8px; }
    .hdr-logo { display: table-cell; width: 60px; vertical-align: middle; }
    .hdr-logo img { width: 55px; height: 55px; object-fit: contain; }
    .logo-ph { width: 55px; height: 55px; background: #0369a1; border-radius: 50%; display: inline-block; line-height: 55px; text-align: center; color: #fff; font-size: 20pt; font-weight: bold; }
    .hdr-center { display: table-cell; text-align: center; vertical-align: middle; padding: 0 10px; }
    .sch-tag { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 2px; }
    .sch-name { font-size: 15pt; font-weight: bold; color: #0369a1; text-transform: uppercase; line-height: 1.05; }
    .sch-addr { font-size: 8pt; color: #475569; margin-top: 2px; }

    .title-bar { background: #0369a1; color: #fff; text-align: center; padding: 5px 10px; margin-bottom: 8px; font-size: 11pt; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; }

    .meta { display: table; width: 100%; margin-bottom: 8px; font-size: 9pt; }
    .meta-l { display: table-cell; }
    .meta-r { display: table-cell; text-align: right; color: #64748b; font-size: 8pt; }
    .meta-l strong { color: #0369a1; }

    .load-row { display: table; width: 100%; margin-bottom: 8px; font-size: 8pt; }
    .load-cell { display: table-cell; text-align: center; padding: 4px 6px; background: #e0f2fe; border: 1px solid #bae6fd; }
    .load-cell .lbl { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
    .load-cell .val { font-weight: bold; color: #0369a1; font-size: 11pt; }

    table.tt { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
    table.tt th, table.tt td { border: 1px solid #cbd5e1; padding: 5px 4px; vertical-align: middle; }
    table.tt th { background: #e0f2fe; color: #075985; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; font-weight: bold; }
    table.tt th.slot-h { width: 110px; text-align: left; padding-left: 8px; }

    td.slot { text-align: left; padding-left: 8px; background: #f0f9ff; }
    td.slot .nm { font-weight: bold; color: #075985; font-size: 9pt; }
    td.slot .tm { font-size: 7pt; color: #64748b; font-family: monospace; }

    td.cell { text-align: center; }
    td.cell .sub { font-weight: bold; font-size: 9pt; color: #1e293b; }
    td.cell .cls { font-size: 7.5pt; color: #64748b; margin-top: 1px; }

    tr.brk td { background: #fef3c7 !important; }
    tr.brk td.slot { background: #fde68a !important; }
    td.brk-cell { color: #92400e; font-weight: bold; text-transform: uppercase; font-size: 8pt; letter-spacing: 1px; }

    td.no-class { background: #f8fafc; color: #94a3b8; font-style: italic; font-size: 7pt; }
    td.free { color: #15803d; font-weight: bold; font-size: 8pt; text-transform: uppercase; letter-spacing: 1px; background: #dcfce7; }

    .sig-row { display: table; width: 100%; margin-top: 14px; padding-top: 10px; }
    .sig-cell { display: table-cell; width: 50%; text-align: center; vertical-align: bottom; padding: 0 8px; }
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
        <div class="sch-tag">Teacher Timetable</div>
        <div class="sch-name">{{ $school?->name ?? 'School' }}</div>
        @if(!empty($school?->address))
            <div class="sch-addr">{{ $school->address }}</div>
        @endif
    </div>
</div>

<div class="title-bar">{{ $teacher->name }}</div>

<div class="meta">
    <div class="meta-l">
        <strong>Teacher:</strong> {{ $teacher->name }}
        @if($teacher->email) &nbsp;·&nbsp; {{ $teacher->email }} @endif
    </div>
    <div class="meta-r">
        Generated {{ now()->format('d M Y, h:i A') }}
    </div>
</div>

<div class="load-row">
    @foreach($days as $code => $label)
        <div class="load-cell">
            <div class="lbl">{{ substr($label, 0, 3) }}</div>
            <div class="val">{{ $loadByDay[$code] }}</div>
        </div>
    @endforeach
    <div class="load-cell" style="background: #0369a1; color: #fff; border-color: #0369a1;">
        <div class="lbl" style="color: #bae6fd;">Total/wk</div>
        <div class="val" style="color: #fff;">{{ $totalLoad }}</div>
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
                        @endphp
                        <td class="cell">
                            <div class="sub" title="{{ $entry->subject?->name }}">{{ $subjLabel }}</div>
                            <div class="cls">{{ $entry->schoolClass?->name }} · {{ $entry->section?->name }}</div>
                        </td>
                    @else
                        <td class="free">FREE</td>
                    @endif
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>

<div class="sig-row">
    <div class="sig-cell">
        <div class="sig-line">{{ $teacher->name }}</div>
        <div class="sig-role">Teacher Signature</div>
    </div>
    <div class="sig-cell">
        @if($sigPath)
            <img src="{{ $sigPath }}" class="sig-img" alt="">
        @endif
        <div class="sig-line">{{ $principalName ?? 'Principal' }}</div>
        <div class="sig-role">Principal</div>
    </div>
</div>

<div class="ftr">— Periods marked FREE may be subject to substitution duty. —</div>

</div></div>
</body>
</html>
