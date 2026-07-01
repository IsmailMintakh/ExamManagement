@php
    $logoPath = !empty($school->logo) && $school?->getLogoAbsolutePath()
        ? $school?->getLogoAbsolutePath() : null;
    $sigPath = !empty($school->principal_signature ?? null) && file_exists(public_path('storage/' . $school->principal_signature))
        ? public_path('storage/' . $school->principal_signature) : null;
    $principalName = !empty($school->principal_name)
        ? $school->principal_name
        : ($school->principal?->name ?? null);

    $dayLabels = [
        'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed',
        'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat',
    ];
    $allWorkingDays = ['mon','tue','wed','thu','fri','sat'];

    $headerTitle = $session?->name
        ? 'Timetable for Session ' . $session->name
        : 'Timetable';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $headerTitle }} — {{ $school->name }}</title>
<style>
    @page { size: A4 landscape; margin: 8mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 9pt;
        color: #0f172a;
        line-height: 1.25;
    }

    /* ─── Navy + cream palette ─── */
    .frame { border: 2.5px solid #1e3a8a; padding: 4px; }
    .frame-inner { border: 1px solid #1e3a8a; padding: 7px; }

    .page { page-break-after: always; }
    .page:last-child { page-break-after: auto; }

    /* Header */
    .hdr { display: table; width: 100%; padding-bottom: 6px; border-bottom: 2.5px solid #1e3a8a; margin-bottom: 8px; }
    .hdr-logo { display: table-cell; width: 56px; vertical-align: middle; }
    .hdr-logo img { width: 52px; height: 52px; object-fit: contain; }
    .logo-ph {
        width: 52px; height: 52px;
        background: #1e3a8a; border-radius: 50%;
        display: inline-block; line-height: 52px;
        text-align: center; color: #fef3c7;
        font-size: 18pt; font-weight: bold;
    }
    .hdr-center { display: table-cell; text-align: center; vertical-align: middle; padding: 0 10px; }
    .hdr-right { display: table-cell; width: 80px; text-align: right; vertical-align: middle; font-size: 8pt; color: #1e3a8a; font-weight: bold; }

    .sch-tag { font-size: 8pt; color: #1e3a8a; text-transform: uppercase; letter-spacing: 2.5px; font-weight: bold; }
    .sch-name {
        font-size: 16pt; font-weight: bold;
        color: #0f172a;
        text-transform: uppercase; line-height: 1; letter-spacing: 0.5px;
        margin-top: 2px;
    }
    .sch-session {
        margin-top: 4px;
        font-size: 9.5pt;
        color: #1e3a8a;
        font-weight: bold;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    /* ─── Main grid ─── */
    table.routine {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        font-size: 9pt;
    }
    table.routine th, table.routine td {
        border: 1px solid #475569;
        padding: 5px 4px;
        vertical-align: middle;
    }
    col.sec-col { width: 30mm; }

    /* Period header band */
    th.hdr-cell {
        background: #1e3a8a;
        color: #ffffff;
        font-size: 9.5pt;
        font-weight: bold;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        padding: 6px 3px;
    }
    th.hdr-cell .nm { font-size: 11pt; line-height: 1; }
    th.hdr-cell .tm {
        font-size: 7.5pt;
        color: #fef3c7;
        font-family: monospace;
        line-height: 1.2;
        margin-top: 2px;
        font-weight: normal;
    }
    th.hdr-cell .skip {
        font-size: 6.5pt;
        color: #fbbf24;
        line-height: 1;
        margin-top: 2px;
        font-weight: bold;
    }

    th.sec-h {
        background: #1e3a8a;
        color: #ffffff;
        font-size: 10pt;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        padding: 6px 4px;
    }

    /* Section name cell */
    td.sec {
        background: #fef3c7;
        text-align: center;
        padding: 6px 4px;
        border-right: 2px solid #1e3a8a;
    }
    td.sec .cls {
        font-size: 11.5pt;
        font-weight: bold;
        color: #1e3a8a;
        line-height: 1;
        letter-spacing: 0.3px;
    }

    /* Subject + teacher cells — high-contrast text */
    td.cell {
        text-align: center;
        background: #ffffff;
        height: 14mm;
    }
    td.cell .sub {
        font-weight: bold;
        font-size: 9.5pt;
        color: #0f172a;
        line-height: 1.15;
    }
    td.cell .tch {
        font-size: 8pt;
        color: #475569;
        line-height: 1.15;
        margin-top: 2px;
        font-style: italic;
    }
    td.cell .var {
        font-size: 6pt;
        color: #be123c;
        font-style: italic;
        line-height: 1;
        margin-top: 2px;
        font-weight: bold;
    }
    td.cell.empty {
        background: #f1f5f9;
        color: #94a3b8;
        font-style: italic;
        font-size: 8pt;
    }

    /* Friday half-day note line */
    .working-days-note {
        margin-top: 5px;
        padding: 4px 6px;
        background: #fef3c7;
        border: 1px solid #fbbf24;
        border-radius: 3px;
        font-size: 8pt;
        text-align: center;
        color: #78350f;
        font-weight: bold;
    }

    /* ─── Single principal signature, centered ─── */
    .sig-row {
        margin-top: 14px;
        text-align: center;
        font-size: 8pt;
    }
    .sig-img {
        height: 30px;
        display: block;
        margin: 0 auto 2px;
    }
    .sig-line {
        display: inline-block;
        border-top: 1px solid #1e3a8a;
        width: 220px;
        padding-top: 4px;
        font-weight: bold;
        color: #0f172a;
        font-size: 9.5pt;
    }
    .sig-role {
        font-size: 7.5pt;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-top: 2px;
    }

    .ftr {
        margin-top: 6px;
        font-size: 6.5pt;
        color: #94a3b8;
        text-align: center;
        font-style: italic;
    }
</style>
</head>
<body>

{{--
    One block per (stage × section-page). Each stage's table only shows
    that stage's period slots, capped to e.g. 4 for Pre-Primary, 6 for
    Primary, 7-8 for higher stages. Sections of each stage paginate within
    the block (~14 per page) so cells don't go microscopic.
--}}
@php
    $blockPages = collect();
    foreach ($blocks as $block) {
        $pages = $block['sections']->chunk(14);
        foreach ($pages as $pi => $pageSections) {
            $blockPages->push([
                'stage' => $block['stage'],
                'stage_label' => $block['stage_label'],
                'period_slots' => $block['period_slots'],
                'sections' => $pageSections,
                'page_index' => $pi,
                'page_total' => $pages->count(),
            ]);
        }
    }
    $totalPages = $blockPages->count();
@endphp

@foreach($blockPages as $bpIdx => $block)
    @php
        $periodSlots = $block['period_slots'];
        $pageSections = $block['sections'];
        $slotMissingDays = [];
        foreach ($periodSlots as $slot) {
            $days = $slot->weekdays ?? $allWorkingDays;
            $missing = array_diff($allWorkingDays, $days);
            if (!empty($missing)) {
                $slotMissingDays[$slot->id] = array_values($missing);
            }
        }
    @endphp
    <div @class(['page' => $bpIdx < $totalPages - 1])>
        <div class="frame"><div class="frame-inner">

            {{-- ─── HEADER ─── --}}
            <div class="hdr">
                <div class="hdr-logo">
                    @if($logoPath)
                        <img src="{{ $logoPath }}" alt="">
                    @else
                        <span class="logo-ph">{{ strtoupper(substr($school->name ?? 'S', 0, 1)) }}</span>
                    @endif
                </div>
                <div class="hdr-center">
                    <div class="sch-tag">Daily Class Routine — {{ $block['stage_label'] }}</div>
                    <div class="sch-name">{{ $school->name }}</div>
                    <div class="sch-session">{{ $headerTitle }}</div>
                </div>
                <div class="hdr-right">
                    Issued<br>
                    {{ $generatedAt->format('d M Y') }}
                </div>
            </div>

            @if($block['page_total'] > 1)
                <p style="text-align:center;font-size:7pt;color:#64748b;margin:0 0 4px;font-style:italic;">
                    {{ $block['stage_label'] }} — Page {{ $block['page_index'] + 1 }} of {{ $block['page_total'] }}
                </p>
            @endif

            {{-- ─── MAIN GRID ─── --}}
            <table class="routine">
                <colgroup>
                    <col class="sec-col">
                    @foreach($periodSlots as $slot)
                        <col>
                    @endforeach
                </colgroup>
                <thead>
                    <tr>
                        <th class="sec-h">Class</th>
                        @foreach($periodSlots as $i => $slot)
                            <th class="hdr-cell">
                                <div class="nm">P{{ $i + 1 }}</div>
                                <div class="tm">{{ substr($slot->starts_at, 0, 5) }}–{{ substr($slot->ends_at, 0, 5) }}</div>
                                @if(isset($slotMissingDays[$slot->id]))
                                    <div class="skip">No: {{ implode(',', array_map(fn ($d) => $dayLabels[$d] ?? $d, $slotMissingDays[$slot->id])) }}</div>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($pageSections as $sec)
                        <tr>
                            <td class="sec">
                                <div class="cls">{{ $sec->schoolClass->name }} &mdash; {{ $sec->name }}</div>
                            </td>
                            @foreach($periodSlots as $slot)
                                @php
                                    $row = $consolidated->get($sec->id . '|' . $slot->id);
                                    $entry = $row['entry'] ?? null;
                                @endphp
                                @if($entry && $entry->subject_id)
                                    @php
                                        $subjLabel = $entry->subject?->code
                                            ?: \Illuminate\Support\Str::limit($entry->subject?->name ?? '—', 12, '…');
                                        $tchLabel = $entry->teacher
                                            ? \Illuminate\Support\Str::limit($entry->teacher->name, 14, '…')
                                            : 'No teacher';
                                    @endphp
                                    <td class="cell">
                                        @if(($show ?? 'both') !== 'teacher')
                                            <div class="sub" title="{{ $entry->subject?->name }}">{{ $subjLabel }}</div>
                                        @endif
                                        @if(($show ?? 'both') !== 'subject')
                                            <div class="tch" title="{{ $entry->teacher?->name }}">{{ $tchLabel }}</div>
                                        @endif
                                        @if(($row['has_variant'] ?? false))
                                            <div class="var">* differs some days</div>
                                        @endif
                                    </td>
                                @else
                                    <td class="cell empty">—</td>
                                @endif
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @if(!empty($slotMissingDays))
                <div class="working-days-note">
                    @foreach($slotMissingDays as $slotId => $missing)
                        @php
                            $slot = $periodSlots->firstWhere('id', $slotId);
                            $idx = $periodSlots->search(fn ($s) => $s->id === $slotId);
                        @endphp
                        @if($slot)
                            P{{ $idx + 1 }} ({{ $slot->name }}) does not run on
                            {{ implode(', ', array_map(fn ($d) => $dayLabels[$d] ?? $d, $missing)) }}.
                        @endif
                    @endforeach
                </div>
            @endif

            @if($bpIdx === $totalPages - 1)
                <div class="sig-row">
                    @if($sigPath)
                        <img src="{{ $sigPath }}" class="sig-img" alt="">
                    @endif
                    <div>
                        <div class="sig-line">{{ $principalName ?? 'Principal' }}</div>
                        <div class="sig-role">Principal &mdash; {{ $school->name }}</div>
                    </div>
                </div>
            @endif

            <div class="ftr">
                Generated {{ $generatedAt->format('d M Y, h:i A') }}
            </div>

        </div></div>
    </div>
@endforeach

</body>
</html>
