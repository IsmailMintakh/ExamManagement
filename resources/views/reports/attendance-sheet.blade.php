@php
    $logoPath = !empty($school->logo) && file_exists(public_path('storage/' . $school->logo))
        ? public_path('storage/' . $school->logo) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Sheet - {{ $exam->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 10mm 10mm 12mm 10mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10pt; color: #1a1a1a; }

        /* One sheet per (section x subject) */
        .sheet { page-break-after: always; padding-bottom: 8mm; }
        .sheet:last-child { page-break-after: auto; }

        /* Header */
        .hdr { display: table; width: 100%; padding-bottom: 8px; border-bottom: 2px solid #064e3b; margin-bottom: 10px; }
        .hdr-l { display: table-cell; width: 60px; vertical-align: middle; }
        .hdr-l img { width: 52px; height: 52px; object-fit: contain; }
        .logo-ph { width: 52px; height: 52px; background: #064e3b; border-radius: 50%; display: inline-block;
                   line-height: 52px; text-align: center; color: #fff; font-size: 22pt; font-weight: bold; }
        .hdr-c { display: table-cell; text-align: center; vertical-align: middle; padding: 0 8px; }
        .school-name { font-size: 16pt; font-weight: bold; color: #064e3b; text-transform: uppercase; letter-spacing: 0.5px; }
        .doc-title { font-size: 10.5pt; margin-top: 3px; color: #2d3748; font-weight: bold; letter-spacing: 0.4px; }
        .meta { font-size: 8.5pt; color: #555; margin-top: 2px; }
        .hdr-r { display: table-cell; width: 90px; text-align: right; vertical-align: middle; font-size: 8pt; color: #444; }

        /* Subject / section banner */
        .banner { display: table; width: 100%; margin: 6px 0 8px; background: #ecfdf5;
                  border-left: 4px solid #064e3b; padding: 6px 10px; border-radius: 3px; }
        .banner-l { display: table-cell; vertical-align: middle; }
        .banner-r { display: table-cell; text-align: right; vertical-align: middle; font-size: 8.5pt; color: #555; }
        .subject-name { font-size: 12pt; font-weight: bold; color: #064e3b; }
        .section-line { font-size: 9pt; color: #475569; margin-top: 1px; }

        /* Date / room / invigilator strip */
        .info-strip { display: table; width: 100%; margin-bottom: 8px; font-size: 8.5pt; }
        .info-cell { display: table-cell; padding: 4px 8px; border: 1px solid #cbd5e1; background: #f8fafc; }
        .info-cell .lbl { color: #64748b; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-cell .val { font-weight: bold; color: #1e293b; min-height: 12px; }

        /* Sign-in table */
        table.signs { width: 100%; border-collapse: collapse; }
        table.signs th { background: #064e3b; color: #fff; padding: 5px 4px; font-size: 8.5pt;
                         text-transform: uppercase; letter-spacing: 0.3px; border: 1px solid #064e3b; }
        table.signs td { padding: 0 6px; border: 1px solid #94a3b8; font-size: 9.5pt;
                         height: 11mm; vertical-align: middle; }
        table.signs td.num { text-align: center; color: #6b7280; font-weight: bold; width: 7%; }
        table.signs td.roll { text-align: center; font-weight: bold; width: 9%; font-family: 'DejaVu Sans Mono', monospace; }
        table.signs td.name { width: 26%; font-weight: 600; }
        table.signs td.father { width: 22%; color: #475569; }
        table.signs td.sig { width: 36%; }
        table.signs tr:nth-child(even) td:not(.sig) { background: #fafafa; }

        /* Footer with invigilator signature */
        .foot { display: table; width: 100%; margin-top: 12px; padding-top: 8px; border-top: 1px dashed #94a3b8; }
        .foot-cell { display: table-cell; width: 50%; padding: 0 8px; vertical-align: bottom; font-size: 8.5pt; color: #475569; }
        .foot-cell .sig-line { border-top: 1px solid #334155; margin-top: 22px; padding-top: 3px; font-weight: bold; color: #1a1a1a; }
        .foot-cell .role { font-size: 7.5pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
        .pgnum { font-size: 7.5pt; color: #94a3b8; text-align: right; margin-top: 4px; }
    </style>
</head>
<body>
    @php $totalSheets = $sections->sum(fn($s) => $s->studentsList->count() > 0 ? $subjects->count() : 0); $sheetIdx = 0; @endphp
    @foreach($sections as $section)
        @if($section->studentsList->isEmpty()) @continue @endif
        @foreach($subjects as $subject)
            @php $sheetIdx++; @endphp
            <div class="sheet">
                {{-- Header --}}
                <div class="hdr">
                    <div class="hdr-l">
                        @if($logoPath)
                            <img src="{{ $logoPath }}" alt="">
                        @else
                            <span class="logo-ph">{{ strtoupper(substr($school->name ?? 'S', 0, 1)) }}</span>
                        @endif
                    </div>
                    <div class="hdr-c">
                        <div class="school-name">{{ $school->name }}</div>
                        <div class="doc-title">{{ $exam->name }} — ATTENDANCE / SIGN-IN SHEET</div>
                        <div class="meta">Session {{ $academicSession->name ?? '' }} · Generated {{ now()->format('d M Y') }}</div>
                    </div>
                    <div class="hdr-r">
                        <div>Sheet {{ $sheetIdx }}/{{ $totalSheets }}</div>
                        <div>{{ $section->studentsList->count() }} Students</div>
                    </div>
                </div>

                {{-- Subject + section banner --}}
                <div class="banner">
                    <div class="banner-l">
                        <div class="subject-name">
                            {{ $subject['name'] }}
                            @if(!empty($subject['code'])) <span style="font-size:9pt;color:#64748b;">({{ $subject['code'] }})</span> @endif
                        </div>
                        <div class="section-line">
                            Class {{ $schoolClass->name }} · Section {{ $section->name }}
                            @if($section->classTeacher?->name)
                                · Class Teacher: {{ $section->classTeacher->name }}
                            @endif
                        </div>
                    </div>
                    <div class="banner-r">
                        Total: <strong>{{ $section->studentsList->count() }}</strong>
                    </div>
                </div>

                {{-- Date / Room / Invigilator (blank, fill in by hand) --}}
                <div class="info-strip">
                    <div class="info-cell" style="width:25%">
                        <div class="lbl">Exam Date</div>
                        <div class="val">&nbsp;</div>
                    </div>
                    <div class="info-cell" style="width:20%">
                        <div class="lbl">Time</div>
                        <div class="val">&nbsp;</div>
                    </div>
                    <div class="info-cell" style="width:20%">
                        <div class="lbl">Room No.</div>
                        <div class="val">&nbsp;</div>
                    </div>
                    <div class="info-cell" style="width:35%">
                        <div class="lbl">Invigilator Name</div>
                        <div class="val">&nbsp;</div>
                    </div>
                </div>

                {{-- Sign-in table --}}
                <table class="signs">
                    <thead>
                        <tr>
                            <th style="width:7%">#</th>
                            <th style="width:9%">Roll</th>
                            <th style="width:26%;text-align:left">Student Name</th>
                            <th style="width:22%;text-align:left">Father Name</th>
                            <th style="width:36%">Signature</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section->studentsList as $idx => $student)
                            <tr>
                                <td class="num">{{ $idx + 1 }}</td>
                                <td class="roll">{{ $student->roll_no }}</td>
                                <td class="name">{{ $student->name }}</td>
                                <td class="father">{{ $student->father_name ?? '' }}</td>
                                <td class="sig"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Footer signature lines --}}
                <div class="foot">
                    <div class="foot-cell">
                        <div>Total Present: ______ &nbsp;&nbsp; Absent: ______</div>
                        <div class="sig-line">Invigilator Signature</div>
                        <div class="role">On-Duty Teacher</div>
                    </div>
                    <div class="foot-cell" style="text-align:right">
                        <div class="sig-line" style="margin-left:30%">Exam Officer / Controller</div>
                        <div class="role" style="margin-left:30%">Verified By</div>
                    </div>
                </div>
                <div class="pgnum">{{ $school->name }} · Sheet {{ $sheetIdx }} of {{ $totalSheets }}</div>
            </div>
        @endforeach
    @endforeach
</body>
</html>
