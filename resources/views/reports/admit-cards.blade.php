<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admit Cards — {{ $exam->name }}</title>
<style>
    @page { size: A4 portrait; margin: 10mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; line-height: 1.3; }

    .card {
        border: 2px solid #1e3a8a;
        padding: 5px;
        margin-bottom: 4mm;
        height: 135mm;
        page-break-inside: avoid;
    }
    .card-inner { border: 1px solid #1e3a8a; padding: 8px; height: 100%; position: relative; }

    /* Second card on page gets page break prevention */
    .card-pair { }
    .card-pair .card:nth-child(2n) { page-break-after: always; }

    .hdr { display: table; width: 100%; padding-bottom: 5px; border-bottom: 2px solid #1e3a8a; margin-bottom: 6px; }
    .hdr-logo { display: table-cell; width: 45px; vertical-align: middle; }
    .hdr-logo img { width: 40px; height: 40px; object-fit: contain; }
    .logo-ph { width: 40px; height: 40px; background: #1e3a8a; border-radius: 50%; display: inline-block; line-height: 40px; text-align: center; color: #fff; font-size: 16pt; font-weight: bold; }
    .hdr-center { display: table-cell; text-align: center; vertical-align: middle; padding: 0 8px; }
    .hdr-right { display: table-cell; width: 70px; text-align: right; vertical-align: middle; font-size: 7pt; }
    .sch-tag { font-size: 6pt; color: #64748b; text-transform: uppercase; letter-spacing: 2px; }
    .sch-name { font-size: 12pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; line-height: 1; }
    .sch-addr { font-size: 6.5pt; color: #475569; margin-top: 2px; }

    .title-bar { background: #1e3a8a; color: #fff; text-align: center; padding: 3px 6px; margin-bottom: 6px; font-size: 9pt; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }

    .body-row { display: table; width: 100%; margin-bottom: 6px; }
    .body-left { display: table-cell; width: 60px; vertical-align: top; }
    .body-mid { display: table-cell; vertical-align: top; padding-left: 8px; }
    .body-right { display: table-cell; width: 80px; vertical-align: top; text-align: right; }

    .photo-box { width: 55px; height: 65px; border: 1.5px solid #1e3a8a; background: #dbeafe; text-align: center; line-height: 65px; font-size: 22pt; font-weight: bold; color: #1e3a8a; overflow: hidden; }
    .photo-box img { width: 100%; height: 100%; object-fit: cover; }

    .stu-name { font-size: 12pt; font-weight: bold; color: #1e3a8a; line-height: 1.1; }
    .stu-meta { font-size: 8pt; color: #475569; margin-top: 3px; line-height: 1.4; }
    .stu-meta strong { color: #1e293b; }

    /* QR box — fake square pattern */
    .qr-box { width: 70px; height: 70px; border: 1.5px solid #1e3a8a; background: #fff; padding: 3px; display: inline-block; }
    .qr-grid { width: 100%; height: 100%; border-collapse: collapse; table-layout: fixed; }
    .qr-grid td { padding: 0; }
    .qr-code { font-family: monospace; font-size: 6pt; color: #64748b; text-align: center; margin-top: 2px; word-break: break-all; line-height: 1; }

    .sched { width: 100%; border-collapse: collapse; font-size: 7.5pt; margin-top: 4px; }
    .sched th, .sched td { border: 1px solid #cbd5e1; padding: 2px 5px; text-align: left; }
    .sched th { background: #eff6ff; color: #1e3a8a; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.3px; }
    .sched td.c { text-align: center; }

    .footer-row { position: absolute; bottom: 6px; left: 8px; right: 8px; display: table; width: calc(100% - 16px); }
    .sig-cell { display: table-cell; width: 50%; vertical-align: bottom; text-align: center; padding: 0 4px; }
    .sig-line { border-top: 1px solid #334155; padding-top: 2px; font-size: 7.5pt; font-weight: bold; margin-top: 12px; }
    .sig-role { font-size: 6.5pt; color: #64748b; text-transform: uppercase; }

    .inst { font-size: 6.5pt; color: #475569; margin-top: 4px; border-top: 1px dashed #cbd5e1; padding-top: 3px; }
    .inst strong { color: #1e3a8a; }

    .verify-url { font-size: 6pt; color: #64748b; margin-top: 1px; text-align: center; }
</style>
</head>
<body>

@foreach($cards->chunk(2) as $pair)
<div class="card-pair">
    @foreach($pair as $card)
        @php
            $student = $card['student'];
            $seat = $card['seat'];
            $code = $card['code'];
            // Build a fake 10x10 QR grid deterministically from the code
            $hash = md5($code);
            $cells = [];
            for ($i = 0; $i < 100; $i++) {
                $cells[] = hexdec(substr($hash, $i % 32, 1)) % 2 === 0;
            }
            // Corner anchors for QR look
            $anchors = [
                [0, 0], [0, 1], [0, 2], [0, 7], [0, 8], [0, 9],
                [1, 0], [1, 2], [1, 7], [1, 9],
                [2, 0], [2, 1], [2, 2], [2, 7], [2, 8], [2, 9],
                [7, 0], [7, 1], [7, 2],
                [8, 0], [8, 2],
                [9, 0], [9, 1], [9, 2],
            ];
            $anchorMap = [];
            foreach ($anchors as [$r, $c]) { $anchorMap[$r * 10 + $c] = true; }
        @endphp
        <div class="card">
            <div class="card-inner">

                <div class="hdr">
                    <div class="hdr-logo">
                        @if(!empty($school?->logo) && file_exists(public_path('storage/' . $school->logo)))
                            <img src="{{ public_path('storage/' . $school->logo) }}" alt="">
                        @else
                            <span class="logo-ph">{{ strtoupper(substr($school?->name ?? 'S', 0, 1)) }}</span>
                        @endif
                    </div>
                    <div class="hdr-center">
                        <div class="sch-tag">Admit Card</div>
                        <div class="sch-name">{{ $school?->name ?? 'School' }}</div>
                        <div class="sch-addr">
                            {{ $school?->address ?? '' }}@if(!empty($school?->phone)) · {{ $school->phone }}@endif
                        </div>
                    </div>
                    <div class="hdr-right">
                        <div style="font-size:6pt;color:#64748b;text-transform:uppercase;">Issued</div>
                        <div style="font-weight:bold;">{{ now()->format('d M Y') }}</div>
                    </div>
                </div>

                <div class="title-bar">{{ $exam->name }}</div>

                <div class="body-row">
                    <div class="body-left">
                        <div class="photo-box">
                            @if(!empty($student->photo) && file_exists(public_path('storage/' . $student->photo)))
                                <img src="{{ public_path('storage/' . $student->photo) }}" alt="">
                            @else
                                {{ strtoupper(substr($student->name ?? 'S', 0, 1)) }}
                            @endif
                        </div>
                    </div>
                    <div class="body-mid">
                        <div class="stu-name">{{ $student->name }}</div>
                        <div class="stu-meta">
                            <strong>Admission:</strong> {{ $student->admission_no }} &nbsp;
                            <strong>Roll:</strong> {{ $student->roll_no ?? '—' }}<br>
                            <strong>Class:</strong> {{ $schoolClass?->name ?? '—' }} &nbsp;
                            <strong>Section:</strong> {{ $section?->name ?? '—' }}<br>
                            <strong>Session:</strong> {{ $academicSession?->name ?? '—' }}
                            @if($seat)
                                <br><strong>Room:</strong> {{ $seat->room?->name }} &nbsp;
                                <strong>Seat:</strong> <span style="background:#1e3a8a;color:#fff;padding:1px 5px;border-radius:3px;">{{ $seat->seat_number }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="body-right">
                        <div class="qr-box">
                            <table class="qr-grid">
                                @for($r = 0; $r < 10; $r++)
                                    <tr>
                                        @for($c = 0; $c < 10; $c++)
                                            @php $on = isset($anchorMap[$r * 10 + $c]) || $cells[$r * 10 + $c]; @endphp
                                            <td style="background:{{ $on ? '#1e3a8a' : '#fff' }};"></td>
                                        @endfor
                                    </tr>
                                @endfor
                            </table>
                        </div>
                        <div class="qr-code">{{ $code }}</div>
                    </div>
                </div>

                @if($schedules->isNotEmpty())
                <table class="sched">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th class="c" style="width:18%">Date</th>
                            <th class="c" style="width:20%">Time</th>
                            <th class="c" style="width:14%">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($schedules as $s)
                        <tr>
                            <td>{{ $s->subject?->name }}</td>
                            <td class="c">{{ optional($s->exam_date)->format('d M Y') }}</td>
                            <td class="c">{{ substr($s->start_time, 0, 5) }}–{{ substr($s->end_time, 0, 5) }}</td>
                            <td class="c">{{ $s->duration_minutes ? $s->duration_minutes.'m' : '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif

                <div class="inst">
                    <strong>Instructions:</strong>
                    Carry this admit card to every exam session. Arrive 15 min before start.
                    Mobile phones and unauthorized material are strictly prohibited.
                    Verify this card at: <span style="font-family:monospace;">{{ url('/verify/admit/' . $code) }}</span>
                </div>

                <div class="footer-row">
                    <div class="sig-cell">
                        <div class="sig-line">Candidate Signature</div>
                        <div class="sig-role">Signature of Student</div>
                    </div>
                    <div class="sig-cell">
                        <div class="sig-line">Principal / Controller</div>
                        <div class="sig-role">Authorised Signatory</div>
                    </div>
                </div>

            </div>
        </div>
    @endforeach
</div>
@endforeach

</body>
</html>
