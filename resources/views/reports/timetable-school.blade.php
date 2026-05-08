@php
    $logoPath = !empty($school->logo) && file_exists(public_path('storage/' . $school->logo))
        ? public_path('storage/' . $school->logo) : null;
    $sigPath = !empty($school->principal_signature ?? null) && file_exists(public_path('storage/' . $school->principal_signature))
        ? public_path('storage/' . $school->principal_signature) : null;
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
<title>School Timetable — {{ $school->name }}</title>
<style>
    @page { size: A4 landscape; margin: 10mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; line-height: 1.3; }

    .frame { border: 2px solid #6d28d9; padding: 5px; }
    .frame-inner { border: 1px solid #6d28d9; padding: 8px; }

    .cover {
        text-align: center;
        padding: 80px 30px 60px;
        page-break-after: always;
    }
    .cover-logo { width: 130px; height: 130px; margin-bottom: 24px; }
    .cover-logo img { width: 130px; height: 130px; object-fit: contain; }
    .cover-tag { font-size: 11pt; color: #64748b; text-transform: uppercase; letter-spacing: 4px; margin-bottom: 10px; }
    .cover-name { font-size: 30pt; font-weight: bold; color: #6d28d9; text-transform: uppercase; letter-spacing: 1px; line-height: 1.1; margin-bottom: 16px; }
    .cover-addr { font-size: 11pt; color: #475569; margin-bottom: 50px; }
    .cover-title { background: #6d28d9; color: #fff; padding: 12px 30px; display: inline-block; font-size: 16pt; font-weight: bold; letter-spacing: 4px; margin-bottom: 30px; }
    .cover-meta { font-size: 10pt; color: #475569; line-height: 1.8; }
    .cover-meta strong { color: #6d28d9; }

    .summary-box {
        margin: 30px auto 0; max-width: 480px;
        border: 1px solid #ddd6fe; border-radius: 6px;
        padding: 16px 24px;
        text-align: left;
        background: #faf8ff;
    }
    .summary-box h3 { color: #6d28d9; font-size: 10pt; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; text-align: center; }
    .summary-row { display: table; width: 100%; padding: 4px 0; font-size: 10pt; }
    .summary-l { display: table-cell; color: #64748b; }
    .summary-r { display: table-cell; text-align: right; font-weight: bold; color: #1e293b; }

    .page { page-break-before: always; }
    .page:first-child { page-break-before: auto; }

    .hdr { display: table; width: 100%; padding-bottom: 6px; border-bottom: 2px solid #6d28d9; margin-bottom: 8px; }
    .hdr-logo { display: table-cell; width: 60px; vertical-align: middle; }
    .hdr-logo img { width: 55px; height: 55px; object-fit: contain; }
    .logo-ph { width: 55px; height: 55px; background: #6d28d9; border-radius: 50%; display: inline-block; line-height: 55px; text-align: center; color: #fff; font-size: 20pt; font-weight: bold; }
    .hdr-center { display: table-cell; text-align: center; vertical-align: middle; padding: 0 10px; }
    .sch-tag { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 2px; }
    .sch-name { font-size: 14pt; font-weight: bold; color: #6d28d9; text-transform: uppercase; line-height: 1.05; }

    .title-bar { background: #6d28d9; color: #fff; text-align: center; padding: 5px 10px; margin-bottom: 8px; font-size: 11pt; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; }

    table.tt { width: 100%; border-collapse: collapse; font-size: 8pt; }
    table.tt th, table.tt td { border: 1px solid #cbd5e1; padding: 4px 3px; vertical-align: middle; }
    table.tt th { background: #f3f0ff; color: #4c1d95; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.3px; text-align: center; font-weight: bold; }
    table.tt th.slot-h { width: 100px; text-align: left; padding-left: 6px; }

    td.slot { text-align: left; padding-left: 6px; background: #faf8ff; }
    td.slot .nm { font-weight: bold; color: #4c1d95; font-size: 8pt; }
    td.slot .tm { font-size: 6.5pt; color: #64748b; font-family: monospace; }

    td.cell { text-align: center; }
    td.cell .sub { font-weight: bold; font-size: 8pt; color: #1e293b; }
    td.cell .tch { font-size: 7pt; color: #64748b; margin-top: 1px; }

    tr.brk td { background: #fef3c7 !important; }
    tr.brk td.slot { background: #fde68a !important; }
    td.brk-cell { color: #92400e; font-weight: bold; text-transform: uppercase; font-size: 7pt; letter-spacing: 1px; }

    td.no-class { background: #f8fafc; color: #94a3b8; font-style: italic; font-size: 7pt; }
    td.free { color: #94a3b8; font-size: 7pt; font-style: italic; }

    .sig-row { display: table; width: 100%; margin-top: 10px; padding-top: 8px; }
    .sig-cell { display: table-cell; width: 50%; text-align: center; vertical-align: bottom; padding: 0 8px; }
    .sig-img { height: 24px; margin-bottom: 2px; }
    .sig-line { border-top: 1px solid #334155; width: 70%; margin: 0 auto; padding-top: 3px; font-size: 8pt; }
    .sig-role { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-top: 1px; }
</style>
</head>
<body>

{{-- ─── COVER PAGE ─── --}}
<div class="cover">
    @if($logoPath)
        <div class="cover-logo"><img src="{{ $logoPath }}" alt=""></div>
    @endif
    <div class="cover-tag">School Master Timetable</div>
    <div class="cover-name">{{ $school->name }}</div>
    @if(!empty($school->address))
        <div class="cover-addr">{{ $school->address }}</div>
    @endif
    <div class="cover-title">Academic Timetable Booklet</div>
    <div class="cover-meta">
        <strong>Generated:</strong> {{ now()->format('l, d F Y · h:i A') }}<br>
        <strong>Total Sections:</strong> {{ $sections->count() }}<br>
        <strong>Total Periods:</strong> {{ $slots->where('type', 'period')->count() }} per day
    </div>

    <div class="summary-box">
        <h3>Sections in this booklet</h3>
        @foreach($sections as $sec)
            <div class="summary-row">
                <span class="summary-l">{{ $sec->schoolClass->name }} &middot; {{ $sec->name }}</span>
                <span class="summary-r">Page {{ $loop->index + 2 }}</span>
            </div>
        @endforeach
    </div>
</div>

{{-- ─── ONE PAGE PER SECTION ─── --}}
@foreach($sections as $sec)
    @php $entries = $entriesBySection->get($sec->id, collect()); @endphp
    <div class="page">
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
                    <div class="sch-tag">Class Timetable</div>
                    <div class="sch-name">{{ $school->name }}</div>
                </div>
            </div>

            <div class="title-bar">
                {{ $sec->schoolClass->name }} &mdash; Section {{ $sec->name }}
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
                                    $entry = $applies ? $entries->get($code . '|' . $slot->id) : null;
                                @endphp
                                @if(!$applies)
                                    <td class="no-class">—</td>
                                @elseif(!$isPeriod)
                                    <td class="brk-cell">{{ $slot->type }}</td>
                                @elseif($entry)
                                    <td class="cell">
                                        <div class="sub">{{ $entry->subject?->name ?? '—' }}</div>
                                        <div class="tch">{{ $entry->teacher?->name ?? 'No teacher' }}</div>
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
            </div>

        </div></div>
    </div>
@endforeach

</body>
</html>
