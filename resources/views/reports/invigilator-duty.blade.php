<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invigilator Duty Chart — {{ $exam->name }}</title>
<style>
    @page { size: A4 portrait; margin: 12mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1e293b; line-height: 1.35; }

    .frame { border: 2px solid #1e3a8a; padding: 6px; }
    .frame-inner { border: 1px solid #1e3a8a; padding: 10px; }

    .hdr { display: table; width: 100%; padding-bottom: 8px; border-bottom: 2px solid #1e3a8a; margin-bottom: 10px; }
    .hdr-logo { display: table-cell; width: 60px; vertical-align: middle; }
    .hdr-logo img { width: 55px; height: 55px; object-fit: contain; }
    .logo-ph { width: 55px; height: 55px; background: #1e3a8a; border-radius: 50%; display: inline-block; line-height: 55px; text-align: center; color: #fff; font-size: 20pt; font-weight: bold; }
    .hdr-center { display: table-cell; text-align: center; vertical-align: middle; padding: 0 10px; }
    .sch-tag { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 2px; }
    .sch-name { font-size: 16pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; line-height: 1; }
    .sch-addr { font-size: 8pt; color: #475569; margin-top: 3px; }

    .title-bar { background: #1e3a8a; color: #fff; text-align: center; padding: 6px 10px; margin-bottom: 10px; font-size: 12pt; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; }

    .teacher-card { border: 1px solid #cbd5e1; border-radius: 4px; margin-bottom: 10px; page-break-inside: avoid; }
    .teacher-hdr { background: #1e3a8a; color: #fff; padding: 5px 10px; font-weight: bold; font-size: 10pt; display: table; width: 100%; }
    .teacher-name { display: table-cell; }
    .teacher-count { display: table-cell; text-align: right; font-size: 8pt; font-weight: normal; opacity: 0.85; }

    table.dt { width: 100%; border-collapse: collapse; font-size: 9pt; }
    table.dt th, table.dt td { border-bottom: 1px solid #e2e8f0; padding: 5px 8px; text-align: left; }
    table.dt th { background: #f1f5f9; color: #334155; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px; }
    table.dt tr:last-child td { border-bottom: none; }

    .role-chief { color: #1e3a8a; font-weight: bold; text-transform: uppercase; font-size: 8pt; }
    .role-invig { color: #334155; font-weight: 600; text-transform: uppercase; font-size: 8pt; }
    .role-relief { color: #d97706; font-weight: bold; text-transform: uppercase; font-size: 8pt; }

    .sig-row { padding: 8px 10px; border-top: 1px dashed #cbd5e1; font-size: 8pt; display: table; width: 100%; }
    .sig-l { display: table-cell; }
    .sig-r { display: table-cell; text-align: right; }
    .sig-line { display: inline-block; border-bottom: 1px solid #334155; width: 160px; height: 16px; vertical-align: bottom; }

    .ftr { margin-top: 10px; font-size: 7pt; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
<div class="frame"><div class="frame-inner">

<div class="hdr">
    <div class="hdr-logo">
        @if(!empty($school?->logo) && file_exists(public_path('storage/' . $school->logo)))
            <img src="{{ public_path('storage/' . $school->logo) }}" alt="">
        @else
            <span class="logo-ph">{{ strtoupper(substr($school?->name ?? 'S', 0, 1)) }}</span>
        @endif
    </div>
    <div class="hdr-center">
        <div class="sch-tag">Invigilator Duty Chart</div>
        <div class="sch-name">{{ $school?->name ?? 'School' }}</div>
        <div class="sch-addr">{{ $school?->address ?? '' }}</div>
    </div>
</div>

<div class="title-bar">{{ $exam->name }}</div>

@forelse($byTeacher as $teacherName => $duties)
<div class="teacher-card">
    <div class="teacher-hdr">
        <span class="teacher-name">{{ $teacherName }}</span>
        <span class="teacher-count">{{ $duties->count() }} dut{{ $duties->count() === 1 ? 'y' : 'ies' }}</span>
    </div>
    <table class="dt">
        <thead>
            <tr>
                <th style="width:5%">#</th>
                <th style="width:15%">Date</th>
                <th style="width:13%">Time</th>
                <th style="width:15%">Room</th>
                <th style="width:22%">Subject</th>
                <th style="width:15%">Class</th>
                <th style="width:15%">Role</th>
            </tr>
        </thead>
        <tbody>
            @foreach($duties->sortBy(fn($d) => optional($d->schedule?->exam_date)->format('Y-m-d') . ' ' . ($d->schedule?->start_time ?? '')) as $i => $d)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ optional($d->schedule?->exam_date)->format('d M Y') ?? '—' }}</td>
                <td>{{ $d->schedule?->start_time ? substr($d->schedule->start_time, 0, 5) : '—' }}–{{ $d->schedule?->end_time ? substr($d->schedule->end_time, 0, 5) : '—' }}</td>
                <td><strong>{{ $d->room?->name ?? '—' }}</strong></td>
                <td>{{ $d->schedule?->subject?->name ?? '—' }}</td>
                <td>{{ $d->schedule?->schoolClass?->name ?? '—' }}</td>
                <td>
                    <span class="{{ 'role-' . ($d->role === 'chief' ? 'chief' : ($d->role === 'relief' ? 'relief' : 'invig')) }}">
                        {{ ucfirst($d->role) }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="sig-row">
        <span class="sig-l">Received and accepted duty: <span class="sig-line"></span></span>
        <span class="sig-r">Date: <span class="sig-line" style="width:100px;"></span></span>
    </div>
</div>
@empty
<p style="padding:20px; text-align:center; color:#94a3b8;">No invigilator assignments recorded.</p>
@endforelse

<div class="ftr">Invigilator Duty Chart · Generated {{ now()->format('d M Y, h:i A') }}</div>

</div></div>
</body>
</html>
