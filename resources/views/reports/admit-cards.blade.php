@php
    // Resolve image paths once for the whole batch (every card uses the same school).
    $logoPath = !empty($school?->logo) && $school?->getLogoAbsolutePath()
        ? $school?->getLogoAbsolutePath() : null;
    $principalSigPath = !empty($school?->principal_signature) && file_exists(public_path('storage/' . $school->principal_signature))
        ? public_path('storage/' . $school->principal_signature) : null;
    $officerSigPath = !empty($school?->exam_officer_signature) && file_exists(public_path('storage/' . $school->exam_officer_signature))
        ? public_path('storage/' . $school->exam_officer_signature) : null;

    // Title combines exam type + exam name → "Monthly Test June 2026".
    $examTitle = trim(($exam->examType?->name ? $exam->examType->name . ' ' : '') . $exam->name);

    // Names for the signature blocks.
    $principalName = !empty($school?->principal_name)
        ? $school->principal_name
        : ($school?->principal?->name ?? null);
    $officerName = $exam->examController?->name ?? ($school?->exam_officer_name ?? null);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admit Cards — {{ $examTitle }}</title>
<style>
    @page { size: A4 portrait; margin: 12mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #1e293b; line-height: 1.35; }

    /* One admit card per A4 page. The outer .card uses page-break-after on
       every instance — dom-pdf prints each card on its own sheet. The last
       trailing blank page is a known dom-pdf quirk that we suppress with
       page-break-after on .card:last-child below. */
    .card {
        border: 2px solid #1e3a8a;
        padding: 6px;
        page-break-after: always;
        page-break-inside: avoid;
    }
    .card:last-child { page-break-after: auto; }
    .card-inner { border: 1px solid #1e3a8a; padding: 12px; position: relative; }

    /* ─── Header ─── */
    .hdr { display: table; width: 100%; padding-bottom: 8px; border-bottom: 2px solid #1e3a8a; margin-bottom: 10px; }
    .hdr-logo { display: table-cell; width: 70px; vertical-align: middle; }
    .hdr-logo img { width: 60px; height: 60px; object-fit: contain; }
    .logo-ph {
        width: 60px; height: 60px;
        background: #1e3a8a; border-radius: 50%;
        display: inline-block; line-height: 60px; text-align: center;
        color: #fff; font-size: 22pt; font-weight: bold;
    }
    .hdr-center { display: table-cell; text-align: center; vertical-align: middle; padding: 0 10px; }
    .hdr-right { display: table-cell; width: 90px; text-align: right; vertical-align: middle; font-size: 8pt; }
    .sch-tag { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 1px; }
    .sch-name { font-size: 15pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; line-height: 1.05; letter-spacing: 0.5px; }
    .sch-addr { font-size: 8pt; color: #475569; margin-top: 3px; }

    .title-bar {
        background: #1e3a8a; color: #fff; text-align: center;
        padding: 6px 10px; margin-bottom: 12px;
        font-size: 12pt; font-weight: bold; letter-spacing: 2.5px; text-transform: uppercase;
    }

    /* ─── Body row: photo · student details · QR ─── */
    .body-row { display: table; width: 100%; margin-bottom: 12px; }
    .body-photo { display: table-cell; width: 95px; vertical-align: top; }
    .body-mid { display: table-cell; vertical-align: top; padding: 0 14px; }
    .body-qr { display: table-cell; width: 115px; vertical-align: top; text-align: center; }

    .photo-box {
        width: 80px; height: 95px;
        border: 1.5px solid #1e3a8a; background: #dbeafe;
        text-align: center; line-height: 95px;
        font-size: 30pt; font-weight: bold; color: #1e3a8a;
        overflow: hidden;
    }
    .photo-box img { width: 100%; height: 100%; object-fit: cover; }
    .photo-cap { font-size: 6.5pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; text-align: center; }

    .stu-name {
        font-size: 14pt; font-weight: bold; color: #1e3a8a;
        line-height: 1.1;
        padding-bottom: 5px;
        border-bottom: 1.5px solid #1e3a8a;
        margin-bottom: 6px;
    }
    /* Records grid — two columns of label/value pairs (so 4 td columns total).
       Labels are uppercase grey small-caps; values bold next to them.
       Labels use white-space:nowrap so multi-word labels (e.g. "Father's Name")
       don't break across two lines when the column is narrow. */
    .stu-meta { width: 100%; border-collapse: collapse; font-size: 8.5pt; table-layout: fixed; }
    .stu-meta tr { border-bottom: 1px dotted #cbd5e1; }
    .stu-meta tr:last-child { border-bottom: none; }
    .stu-meta td { padding: 4px 6px; vertical-align: top; }
    .stu-meta td.lbl {
        width: 22%;
        font-size: 7pt; color: #64748b;
        text-transform: uppercase; letter-spacing: 0.3px;
        font-weight: bold;
        white-space: nowrap;
    }
    .stu-meta td.val {
        width: 28%;
        font-weight: 600; color: #1e293b;
    }
    .seat-pill { background: #1e3a8a; color: #fff; padding: 1px 6px; border-radius: 3px; font-size: 8.5pt; font-weight: bold; }

    /* Real scannable QR — rendered as absolute-positioned divs so dompdf can
       paint it (its SVG path renderer is unreliable). Box is sized to fit
       the QR exactly with a 4px quiet-zone padding. */
    .qr-box {
        width: 110px; height: 110px;
        border: 1.5px solid #1e3a8a; background: #fff;
        padding: 4px; display: inline-block;
        text-align: center;
        line-height: 0;
    }
    .qr-cap { font-size: 6.5pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; line-height: 1.3; }
    .qr-code { font-family: monospace; font-size: 6.5pt; color: #1e293b; word-break: break-all; line-height: 1.2; margin-top: 1px; }

    /* ─── Schedule table ─── */
    .sched { width: 100%; border-collapse: collapse; font-size: 9pt; margin-top: 4px; }
    .sched th, .sched td { border: 1px solid #cbd5e1; padding: 5px 7px; text-align: left; }
    .sched th { background: #1e3a8a; color: #fff; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.4px; }
    .sched td.c { text-align: center; }
    .sched tr:nth-child(even) td { background: #f8fafc; }

    /* ─── Instructions ─── */
    .inst {
        margin-top: 12px;
        border: 1px solid #1e3a8a;
        background: #eff6ff;
        padding: 6px 10px;
    }
    .inst-title { font-size: 8.5pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
    .inst ol { padding-left: 16px; font-size: 8pt; color: #1e293b; }
    .inst li { margin-bottom: 1px; }

    /* ─── Signatures (in normal flow at the end) ─── */
    .sig {
        display: table; width: 100%;
        margin-top: 16px;
        padding-top: 10px;
        border-top: 1px solid #cbd5e1;
        page-break-inside: avoid;
    }
    .sig-cell { display: table-cell; width: 50%; text-align: center; padding: 0 16px; vertical-align: bottom; }
    .sig-img-wrap { height: 14mm; position: relative; margin-bottom: 2px; }
    .sig-img {
        position: absolute;
        left: 50%; bottom: 0;
        transform: translateX(-50%);
    }
    .sig-img img { max-height: 12mm; max-width: 35mm; }
    .sig-img .placeholder { font-size: 7pt; color: #cbd5e1; font-style: italic; line-height: 12mm; display: inline-block; }
    .sig-line { border-top: 1px solid #334155; padding-top: 3px; font-size: 9pt; font-weight: bold; }
    .sig-role { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .sig-name { font-size: 8pt; color: #0f172a; font-weight: 600; margin-top: 1px; }

    .ftr {
        margin-top: 8px; padding-top: 4px;
        border-top: 1px solid #e2e8f0;
        font-size: 7pt; color: #94a3b8;
        text-align: center;
    }
</style>
</head>
<body>

@foreach($cards as $card)
    @php
        $student = $card['student'];
        $seat = $card['seat'];
        $code = $card['code'];
        $qrSvg = $card['qrSvg'];
    @endphp
    <div class="card">
        <div class="card-inner">

            {{-- Header: logo · school name + address · issue date --}}
            <div class="hdr">
                <div class="hdr-logo">
                    @if($logoPath)
                        <img src="{{ $logoPath }}" alt="">
                    @else
                        <span class="logo-ph">{{ strtoupper(substr($school?->name ?? 'S', 0, 1)) }}</span>
                    @endif
                </div>
                <div class="hdr-center">
                    <div class="sch-tag">Admit Card</div>
                    <div class="sch-name">{{ $school?->name ?? 'School' }}</div>
                    <div class="sch-addr">
                        {{ $school?->address ?? '' }}@if(!empty($school?->phone)) · Ph: {{ $school->phone }}@endif
                    </div>
                </div>
                <div class="hdr-right">
                    <div style="font-size:7pt;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;">Issue Date</div>
                    <div style="font-weight:bold;font-size:9pt;">{{ now()->format('d M Y') }}</div>
                </div>
            </div>

            {{-- Title now combines exam type + name (e.g. "Monthly Test June 2026") --}}
            <div class="title-bar">{{ $examTitle }}</div>

            {{-- Body: student photo (left) · details (middle) · QR with code (right).
                 The two images are deliberately distinct: photo identifies the
                 student, QR encodes the verification token printed below it. --}}
            <div class="body-row">
                <div class="body-photo">
                    <div class="photo-box">
                        @if(!empty($student->photo) && file_exists(public_path('storage/' . $student->photo)))
                            <img src="{{ public_path('storage/' . $student->photo) }}" alt="">
                        @else
                            {{ strtoupper(substr($student->name ?? 'S', 0, 1)) }}
                        @endif
                    </div>
                    <div class="photo-cap">Student Photo</div>
                </div>
                <div class="body-mid">
                    <div class="stu-name">{{ $student->name }}</div>
                    {{-- Two-column key/value grid: 4-column table where odd
                         columns are labels and even columns are values. Reads
                         like an ID-card data block instead of a vertical stack. --}}
                    {{-- Use the student's OWN class + section (loaded as
                         relations) so the bulk admit-card PDF shows correct
                         info per card. The outer $schoolClass / $section
                         variables are null in bulk mode. --}}
                    <table class="stu-meta">
                        <tr>
                            <td class="lbl">Father's Name</td>
                            <td class="val">{{ $student->father_name ?? '—' }}</td>
                            <td class="lbl">Class</td>
                            <td class="val">{{ $student->schoolClass?->name ?? $schoolClass?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Admission #</td>
                            <td class="val">{{ $student->admission_no }}</td>
                            <td class="lbl">Section</td>
                            <td class="val">{{ $student->section?->name ?? $section?->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Roll #</td>
                            <td class="val">{{ $student->roll_no ?? '—' }}</td>
                            <td class="lbl">Session</td>
                            <td class="val">{{ $academicSession?->name ?? '—' }}</td>
                        </tr>
                        @if($seat)
                            <tr>
                                <td class="lbl">Room</td>
                                <td class="val">{{ $seat->room?->name ?? '—' }}</td>
                                <td class="lbl">Seat</td>
                                <td class="val"><span class="seat-pill">{{ $seat->seat_number }}</span></td>
                            </tr>
                        @endif
                    </table>
                </div>
                <div class="body-qr">
                    {{-- Real scannable QR — generated server-side as inline SVG
                         via BaconQrCode. Encodes the verify URL, so a phone
                         scan lands on the verification page. --}}
                    <div class="qr-box">{!! $qrSvg !!}</div>
                    <div class="qr-cap">Verification Code</div>
                    <div class="qr-code">{{ $code }}</div>
                </div>
            </div>

            {{-- Schedule table — filtered to THIS student's class so the
                 bulk PDF shows the right schedule on each card. The outer
                 $schedules collection might span multiple classes. --}}
            @php
                $studentSchedules = $schedules->where('school_class_id', $student->school_class_id)->values();
            @endphp
            @if($studentSchedules->isNotEmpty())
            <table class="sched">
                <thead>
                    <tr>
                        <th style="width:6%">#</th>
                        <th>Subject</th>
                        <th class="c" style="width:14%">Date</th>
                        <th class="c" style="width:13%">Day</th>
                        <th class="c" style="width:18%">Time</th>
                        <th class="c" style="width:11%">Duration</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($studentSchedules as $i => $s)
                    <tr>
                        <td class="c">{{ $i + 1 }}</td>
                        <td>{{ $s->subject?->name }}</td>
                        <td class="c">{{ optional($s->exam_date)->format('d M Y') }}</td>
                        <td class="c">{{ optional($s->exam_date)->format('l') }}</td>
                        <td class="c">{{ substr($s->start_time, 0, 5) }} – {{ substr($s->end_time, 0, 5) }}</td>
                        <td class="c">{{ $s->duration_minutes ? abs((int) $s->duration_minutes) . ' min' : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif

            {{-- Instructions block — six standard rules. The verify URL was
                 dropped per user request; the QR/code on the right serves the
                 same purpose. --}}
            <div class="inst">
                <div class="inst-title">Instructions for the Candidate</div>
                <ol>
                    <li>Carry this admit card to every paper. Entry will be denied without it.</li>
                    <li>Reach the examination centre at least 30 minutes before the start time.</li>
                    <li>Mobile phones, smart watches and any electronic devices are strictly prohibited.</li>
                    <li>Use only blue or black ink ball-point pens.</li>
                    <li>Late arrival beyond 15 minutes after the start time will not be allowed.</li>
                    <li>Any unfair means will lead to immediate cancellation of the paper.</li>
                </ol>
            </div>

            {{-- Two signature cells: Exam Controller and Principal.
                 Candidate-signature cell removed per user request. --}}
            <div class="sig">
                <div class="sig-cell">
                    <div class="sig-img-wrap">
                        <div class="sig-img">
                            @if($officerSigPath)
                                <img src="{{ $officerSigPath }}" alt="">
                            @else
                                <span class="placeholder">— signature —</span>
                            @endif
                        </div>
                    </div>
                    <div class="sig-line">Exam Controller</div>
                    @if($officerName)
                        <div class="sig-name">{{ $officerName }}</div>
                    @else
                        <div class="sig-role">Signature &amp; Date</div>
                    @endif
                </div>

                <div class="sig-cell">
                    <div class="sig-img-wrap">
                        <div class="sig-img">
                            @if($principalSigPath)
                                <img src="{{ $principalSigPath }}" alt="">
                            @else
                                <span class="placeholder">— signature —</span>
                            @endif
                        </div>
                    </div>
                    <div class="sig-line">Principal</div>
                    @if($principalName)
                        <div class="sig-name">{{ $principalName }}</div>
                    @else
                        <div class="sig-role">Signature &amp; Date</div>
                    @endif
                </div>
            </div>

            <div class="ftr">
                Generated {{ now()->format('d M Y, h:i A') }} · Computer-generated admit card
            </div>

        </div>
    </div>
@endforeach

</body>
</html>
