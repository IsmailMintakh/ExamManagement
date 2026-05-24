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

    // Each stage's sections + its own bell schedule are rendered as a block.
    // Within a stage, sections may still need to be chunked across pages if
    // there are more than ~16. Stages are emitted in the declared order so
    // ECD comes before Primary, etc.
    $stageOrder = ['pre_primary', 'primary', 'middle', 'secondary', 'higher_secondary', ''];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Master Timetable — {{ $school->name }}</title>
<style>
    @page { size: A3 landscape; margin: 8mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { font-family: 'DejaVu Sans', sans-serif; font-size: 8pt; color: #1e293b; line-height: 1.25; }

    .frame { border: 2px solid #6d28d9; padding: 4px; }
    .frame-inner { border: 1px solid #6d28d9; padding: 6px; }

    .page { page-break-after: always; }
    .page:last-child { page-break-after: auto; }

    .hdr { display: table; width: 100%; padding-bottom: 5px; border-bottom: 2px solid #6d28d9; margin-bottom: 6px; }
    .hdr-logo { display: table-cell; width: 55px; vertical-align: middle; }
    .hdr-logo img { width: 50px; height: 50px; object-fit: contain; }
    .logo-ph { width: 50px; height: 50px; background: #6d28d9; border-radius: 50%; display: inline-block; line-height: 50px; text-align: center; color: #fff; font-size: 18pt; font-weight: bold; }
    .hdr-center { display: table-cell; text-align: center; vertical-align: middle; padding: 0 10px; }
    .sch-tag { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 2px; }
    .sch-name { font-size: 14pt; font-weight: bold; color: #6d28d9; text-transform: uppercase; line-height: 1.05; }
    .sch-addr { font-size: 7pt; color: #475569; margin-top: 2px; }
    .hdr-right { display: table-cell; width: 55px; vertical-align: middle; }

    .day-bar { background: #6d28d9; color: #fff; text-align: center; padding: 4px 10px; margin-bottom: 6px; font-size: 12pt; font-weight: bold; letter-spacing: 4px; text-transform: uppercase; }

    .chunk-info { font-size: 7pt; color: #64748b; text-align: center; margin-bottom: 4px; font-style: italic; }

    table.master { width: 100%; border-collapse: collapse; font-size: 7pt; table-layout: fixed; }
    table.master th, table.master td { border: 1px solid #cbd5e1; padding: 3px 2px; vertical-align: middle; word-wrap: break-word; }

    table.master th.slot-h { width: 28mm; background: #f3f0ff; color: #4c1d95; text-align: left; padding-left: 5px; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.3px; font-weight: bold; }
    table.master th.sec-h { background: #f3f0ff; color: #4c1d95; font-size: 6.5pt; text-align: center; font-weight: bold; }
    table.master th.sec-h .cls { font-size: 7pt; color: #6d28d9; text-transform: uppercase; }
    table.master th.sec-h .sec { font-size: 6pt; color: #64748b; }

    td.slot { background: #faf8ff; text-align: left; padding-left: 5px; }
    td.slot .nm { font-weight: bold; color: #4c1d95; font-size: 7.5pt; }
    td.slot .tm { font-size: 6pt; color: #64748b; font-family: monospace; }
    td.slot .ty { font-size: 6pt; color: #92400e; text-transform: uppercase; font-weight: bold; }

    td.cell { text-align: center; }
    td.cell .sub { font-weight: bold; font-size: 7pt; color: #1e293b; line-height: 1.1; }
    td.cell .tch { font-size: 5.5pt; color: #64748b; line-height: 1.1; margin-top: 0.5px; }
    td.cell.empty { color: #cbd5e1; font-style: italic; font-size: 6pt; }

    tr.brk td { background: #fef3c7 !important; }
    tr.brk td.slot { background: #fde68a !important; }
    td.brk-cell { color: #92400e; font-weight: bold; text-transform: uppercase; font-size: 6.5pt; letter-spacing: 0.5px; text-align: center; }

    td.no-class { background: #f8fafc; color: #94a3b8; font-style: italic; font-size: 6pt; text-align: center; }

    .legend { margin-top: 6px; text-align: center; font-size: 6pt; color: #64748b; }
    .legend span { display: inline-block; padding: 2px 6px; margin: 0 4px; border-radius: 3px; font-weight: bold; }
    .legend .l-period { background: #faf8ff; color: #4c1d95; }
    .legend .l-break { background: #fef3c7; color: #92400e; }
    .legend .l-empty { background: #f8fafc; color: #94a3b8; }
</style>
</head>
<body>

{{-- ─── ONE BLOCK PER (STAGE × DAY × SECTION-CHUNK) ─── --}}
@php $pageCounter = 0; @endphp
@foreach($stageOrder as $stageKey)
    @php
        $stageSecs = $sectionsByStage->get($stageKey) ?? collect();
        if ($stageSecs->isEmpty()) continue;
        $stageSlots = $slotsByStage[$stageKey] ?? collect();
        if ($stageSlots->isEmpty()) continue;
        $stageLabel = $stageKey ? ($stageLabels[$stageKey] ?? $stageKey) : 'Other classes';
        $sectionChunks = $stageSecs->chunk(16);
    @endphp
    @foreach($days as $code => $label)
        @foreach($sectionChunks as $chunkIndex => $chunk)
            @php $pageCounter++; @endphp
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
                            <div class="sch-tag">Master Timetable · {{ $stageLabel }}</div>
                            <div class="sch-name">{{ $school->name }}</div>
                            @if(!empty($school->address))
                                <div class="sch-addr">{{ $school->address }}</div>
                            @endif
                        </div>
                        <div class="hdr-right" style="text-align: right; font-size: 7pt; color: #64748b;">
                            Page {{ $pageCounter }}
                        </div>
                    </div>

                    <div class="day-bar">{{ $label }} · {{ $stageLabel }}</div>

                    @if($sectionChunks->count() > 1)
                        <div class="chunk-info">
                            Sections {{ $chunkIndex * 16 + 1 }}–{{ min($chunkIndex * 16 + $chunk->count(), $stageSecs->count()) }} of {{ $stageSecs->count() }}
                        </div>
                    @endif

                    <table class="master">
                        <colgroup>
                            <col style="width: 28mm;">
                            @foreach($chunk as $sec)
                                <col>
                            @endforeach
                        </colgroup>
                        <thead>
                            <tr>
                                <th class="slot-h">Slot</th>
                                @foreach($chunk as $sec)
                                    <th class="sec-h">
                                        <div class="cls">{{ $sec->schoolClass->name }}</div>
                                        <div class="sec">Sec {{ $sec->name }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stageSlots as $slot)
                                @php
                                    $isPeriod = $slot->type === 'period';
                                    $slotDays = $slot->weekdays ?: array_keys($days);
                                @endphp
                                <tr class="{{ $isPeriod ? '' : 'brk' }}">
                                    <td class="slot">
                                        <div class="nm">{{ $slot->name }}</div>
                                        <div class="tm">{{ substr($slot->starts_at, 0, 5) }}–{{ substr($slot->ends_at, 0, 5) }}</div>
                                        @if(!$isPeriod)<div class="ty">{{ $slot->type }}</div>@endif
                                    </td>
                                    @foreach($chunk as $sec)
                                        @php
                                            $applies = in_array($code, $slotDays, true);
                                            $entry = $applies ? ($entries[$code . '|' . $slot->id . '|' . $sec->id] ?? null) : null;
                                            $subjLabel = $entry ? ($entry->subject?->code ?: \Illuminate\Support\Str::limit($entry->subject?->name ?? '—', 10, '…')) : null;
                                            $tchLabel = $entry && $entry->teacher ? \Illuminate\Support\Str::limit($entry->teacher->name, 12, '…') : null;
                                        @endphp
                                        @if(!$applies)
                                            <td class="no-class">—</td>
                                        @elseif(!$isPeriod)
                                            <td class="brk-cell">{{ $slot->type }}</td>
                                        @elseif($entry)
                                            <td class="cell">
                                                <div class="sub" title="{{ $entry->subject?->name }}">{{ $subjLabel }}</div>
                                                <div class="tch" title="{{ $entry->teacher?->name }}">{{ $tchLabel ?? 'No teacher' }}</div>
                                            </td>
                                        @else
                                            <td class="cell empty">—</td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="legend">
                        <span class="l-period">Period</span>
                        <span class="l-break">Break / Lunch</span>
                        <span class="l-empty">Empty</span>
                    </div>

                </div></div>
            </div>
        @endforeach
    @endforeach
@endforeach

</body>
</html>
