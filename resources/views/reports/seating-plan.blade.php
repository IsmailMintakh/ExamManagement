<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Seating Plan — {{ $room->name }}</title>
<style>
    @page { size: A4 landscape; margin: 10mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; line-height: 1.3; }
    .frame { border: 2px solid #1e3a8a; padding: 5px; }
    .frame-inner { border: 1px solid #1e3a8a; padding: 8px; }

    .hdr { display: table; width: 100%; padding-bottom: 6px; border-bottom: 2px solid #1e3a8a; margin-bottom: 8px; }
    .hdr-logo { display: table-cell; width: 52px; vertical-align: middle; }
    .hdr-logo img { width: 48px; height: 48px; object-fit: contain; }
    .logo-ph { width: 48px; height: 48px; background: #1e3a8a; border-radius: 50%; display: inline-block; line-height: 48px; text-align: center; color: #fff; font-size: 18pt; font-weight: bold; }
    .hdr-center { display: table-cell; text-align: center; vertical-align: middle; padding: 0 10px; }
    .hdr-right { display: table-cell; width: 100px; text-align: right; font-size: 7.5pt; vertical-align: middle; }
    .sch-tag { font-size: 6.5pt; color: #64748b; text-transform: uppercase; letter-spacing: 2px; }
    .sch-name { font-size: 15pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; line-height: 1; }
    .sch-addr { font-size: 7.5pt; color: #475569; margin-top: 2px; }
    .meta-lbl { font-size: 6.5pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .meta-val { font-weight: bold; color: #1e293b; font-size: 8.5pt; }

    .title-bar { background: #1e3a8a; color: #fff; text-align: center; padding: 5px 8px; margin-bottom: 8px; font-size: 11pt; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }

    .info-row { display: table; width: 100%; margin-bottom: 6px; font-size: 8pt; }
    .info-cell { display: table-cell; padding: 4px 8px; background: #eff6ff; border: 1px solid #bfdbfe; }

    .board { background: #1e293b; color: #f1f5f9; text-align: center; padding: 4px; font-size: 9pt; font-weight: bold; letter-spacing: 4px; text-transform: uppercase; margin-bottom: 6px; }

    table.grid { width: 100%; border-collapse: separate; border-spacing: 2px; table-layout: fixed; }
    table.grid td { border: 1px solid #cbd5e1; padding: 3px; vertical-align: top; height: 52px; }
    td.seat { background: #eff6ff; }
    td.empty { background: #f1f5f9; opacity: 0.5; }
    .seat-num { font-size: 6.5pt; font-weight: bold; color: #1e3a8a; display: block; }
    .seat-roll { font-size: 7pt; color: #64748b; display: block; }
    .seat-name { font-size: 7.5pt; font-weight: 600; color: #1e293b; margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; }

    .inv-section { margin-top: 10px; display: table; width: 100%; border: 1px dashed #cbd5e1; padding: 6px; }
    .inv-cell { display: table-cell; width: 50%; padding: 4px 8px; }
    .inv-lbl { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }

    .ftr { margin-top: 6px; font-size: 7pt; color: #94a3b8; text-align: center; }
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
        <div class="sch-tag">Seating Plan</div>
        <div class="sch-name">{{ $school?->name ?? 'School' }}</div>
        <div class="sch-addr">{{ $school?->address ?? '' }}</div>
    </div>
    <div class="hdr-right">
        <div class="meta-lbl">Room</div>
        <div class="meta-val">{{ $room->name }}</div>
        <div class="meta-lbl" style="margin-top:3px;">Layout</div>
        <div class="meta-val">{{ $room->rows }} × {{ $room->cols }} = {{ $room->rows * $room->cols }}</div>
    </div>
</div>

<div class="title-bar">{{ $exam->name }}</div>

<div class="info-row">
    <div class="info-cell">
        <div class="meta-lbl">Session</div>
        <div class="meta-val">{{ $academicSession?->name ?? '—' }}</div>
    </div>
    <div class="info-cell">
        <div class="meta-lbl">Seats Assigned</div>
        <div class="meta-val">{{ $totalSeats ?? 0 }} / {{ $room->rows * $room->cols }}</div>
    </div>
    <div class="info-cell">
        <div class="meta-lbl">Printed</div>
        <div class="meta-val">{{ now()->format('d M Y, h:i A') }}</div>
    </div>
</div>

<div class="board">⬇ Invigilator / Board / Front ⬇</div>

<table class="grid">
    @foreach($grid as $row)
    <tr>
        @foreach($row as $cell)
        <td class="{{ $cell ? 'seat' : 'empty' }}">
            @if($cell)
                <span class="seat-num">{{ $cell['seat_number'] }}</span>
                <span class="seat-roll">Roll: {{ $cell['roll_no'] ?? '—' }}</span>
                <span class="seat-name">{{ $cell['student_name'] }}</span>
            @else
                <span class="seat-num" style="color:#cbd5e1;">—</span>
            @endif
        </td>
        @endforeach
    </tr>
    @endforeach
</table>

<div class="inv-section">
    <div class="inv-cell">
        <div class="inv-lbl">Chief Invigilator</div>
        <div style="border-bottom: 1px solid #64748b; height: 20px; margin-top: 4px;"></div>
        <div class="inv-lbl" style="margin-top:2px;">Signature</div>
    </div>
    <div class="inv-cell">
        <div class="inv-lbl">Invigilator</div>
        <div style="border-bottom: 1px solid #64748b; height: 20px; margin-top: 4px;"></div>
        <div class="inv-lbl" style="margin-top:2px;">Signature</div>
    </div>
</div>

<div class="ftr">Seating Plan · {{ $room->name }} · {{ now()->format('d M Y') }}</div>

</div></div>
</body>
</html>
