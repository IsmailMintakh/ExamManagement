@php
    // First absence row gives us a school context if any. Slip is school-scoped
    // implicitly (same admin generated all the assignments). Resolve school
    // from the first sub teacher; if none, fall through to plain header.
    $firstSub = $bySub->first();
    $school = null;
    if ($firstSub && !empty($firstSub['rows']) && $firstSub['rows']->isNotEmpty()) {
        // Walk up via the first row's class. Cheapest: re-fetch via teacher_id.
    }
    // Header is generic — title carries the date, which is what matters.
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Class Adjustment Slip — {{ $date->format('d M Y') }}</title>
<style>
    @page { size: A4 portrait; margin: 12mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1e293b; line-height: 1.35; }

    .frame { border: 2px solid #be185d; padding: 6px; }
    .frame-inner { border: 1px solid #be185d; padding: 10px; }

    .hdr { text-align: center; padding-bottom: 8px; border-bottom: 2px solid #be185d; margin-bottom: 10px; }
    .sch-tag { font-size: 8pt; color: #64748b; text-transform: uppercase; letter-spacing: 3px; }
    .sch-name { font-size: 18pt; font-weight: bold; color: #be185d; text-transform: uppercase; letter-spacing: 0.5px; }
    .date-line { font-size: 11pt; color: #475569; margin-top: 4px; }

    .title-bar { background: #be185d; color: #fff; text-align: center; padding: 6px 10px; margin-bottom: 12px; font-size: 12pt; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; }

    .summary { background: #fce7f3; border: 1px solid #f9a8d4; padding: 8px 12px; margin-bottom: 12px; font-size: 9pt; text-align: center; color: #831843; }
    .summary strong { color: #be185d; font-size: 10pt; }

    .teacher-card { border: 1px solid #cbd5e1; border-radius: 4px; margin-bottom: 12px; page-break-inside: avoid; }
    .teacher-hdr { background: #be185d; color: #fff; padding: 6px 12px; font-weight: bold; font-size: 10.5pt; display: table; width: 100%; }
    .teacher-name { display: table-cell; }
    .teacher-count { display: table-cell; text-align: right; font-size: 8.5pt; font-weight: normal; opacity: 0.85; }

    table.dt { width: 100%; border-collapse: collapse; font-size: 9pt; }
    table.dt th, table.dt td { border-bottom: 1px solid #e2e8f0; padding: 6px 10px; text-align: left; }
    table.dt th { background: #fdf2f8; color: #831843; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px; }
    table.dt tr:last-child td { border-bottom: none; }
    table.dt td.tm { font-family: monospace; color: #475569; font-size: 8.5pt; white-space: nowrap; }
    table.dt td.cls { font-weight: bold; color: #1e293b; }
    table.dt td.sub { color: #475569; }
    table.dt td.repl { font-size: 8pt; color: #94a3b8; font-style: italic; }

    .sig-row { padding: 8px 12px; border-top: 1px dashed #cbd5e1; font-size: 8.5pt; display: table; width: 100%; background: #fdf2f8; }
    .sig-l { display: table-cell; }
    .sig-r { display: table-cell; text-align: right; }
    .sig-line { display: inline-block; border-bottom: 1px solid #334155; width: 160px; height: 16px; vertical-align: bottom; }

    .empty { text-align: center; padding: 30px; color: #94a3b8; font-style: italic; }

    .ftr { margin-top: 8px; font-size: 7.5pt; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
<div class="frame"><div class="frame-inner">

@include('reports.partials.logo-header', [
    'school' => $school ?? null,
    'title' => 'Daily Class Adjustment Slip',
    'subtitle' => $date->format('l, d F Y'),
    'logoSize' => 55,
])

<div class="title-bar">Today's Class Adjustments</div>

<div class="summary">
    <strong>{{ $totalAssignments }}</strong> period(s) being covered by
    <strong>{{ $bySub->count() }}</strong> teacher(s) today.
</div>

@if($bySub->isEmpty())
    <div class="empty">No class adjustments for this date.<br>
    <small>Either no teachers are absent, or the algorithm has not been run yet.</small></div>
@else
    @foreach($bySub as $group)
        <div class="teacher-card">
            <div class="teacher-hdr">
                <span class="teacher-name">{{ $group['teacher_name'] ?? 'Unknown teacher' }}</span>
                <span class="teacher-count">{{ $group['count'] }} cover{{ $group['count'] === 1 ? '' : 's' }} today</span>
            </div>
            <table class="dt">
                <thead>
                    <tr>
                        <th style="width: 90px;">Time</th>
                        <th style="width: 110px;">Slot</th>
                        <th>Class &middot; Section</th>
                        <th>Subject</th>
                        <th>Replaces</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group['rows'] as $row)
                        <tr>
                            <td class="tm">{{ $row['time_range'] ?? '—' }}</td>
                            <td>{{ $row['time_slot'] ?? '—' }}</td>
                            <td class="cls">{{ $row['class'] ?? '—' }} · {{ $row['section'] ?? '—' }}</td>
                            <td class="sub">{{ $row['subject'] ?? '—' }}</td>
                            <td class="repl">{{ $row['replaces'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="sig-row">
                <div class="sig-l">Acknowledged: <span class="sig-line"></span></div>
                <div class="sig-r">Date: <span class="sig-line" style="width: 90px;"></span></div>
            </div>
        </div>
    @endforeach
@endif

<div class="ftr">
    Generated {{ now()->format('d M Y, h:i A') }} — Issued by office of the Principal.
</div>

</div></div>
</body>
</html>
