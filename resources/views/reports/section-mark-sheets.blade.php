<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Mark Sheets — {{ $schoolClass->name }} {{ $section->name }}</title>
<style>
    @page { size: A4 portrait; margin: 10mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; line-height: 1.3; }

    /* Each sheet fills exactly one A4 page, next sheet starts fresh */
    .sheet {
        width: 100%;
        position: relative;
        page-break-after: always;
    }
    .sheet:last-child { page-break-after: auto; }

    .frame { border: 2px solid #1e3a8a; padding: 5px; }
    .frame-inner { border: 1px solid #1e3a8a; padding: 8px; }

    .hdr { display: table; width: 100%; padding-bottom: 6px; border-bottom: 2px solid #1e3a8a; margin-bottom: 8px; }
    .hdr-logo { display: table-cell; width: 60px; vertical-align: middle; }
    .hdr-logo img { width: 55px; height: 55px; object-fit: contain; }
    .logo-ph { width: 55px; height: 55px; background: #1e3a8a; border-radius: 50%; display: inline-block; line-height: 55px; text-align: center; color: #fff; font-size: 22pt; font-weight: bold; }
    .hdr-center { display: table-cell; text-align: center; vertical-align: middle; padding: 0 10px; }
    .hdr-right { display: table-cell; width: 80px; text-align: right; font-size: 7.5pt; vertical-align: middle; }
    .sch-tag { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 1px; }
    .sch-name { font-size: 17pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1; }
    .sch-addr { font-size: 8pt; color: #475569; margin-top: 3px; }
    .hdr-right .lbl { font-size: 6.5pt; color: #64748b; text-transform: uppercase; }
    .hdr-right .val { font-weight: bold; color: #1e293b; font-size: 8pt; }

    .title-bar { background: #1e3a8a; color: #fff; text-align: center; padding: 5px 10px; margin-bottom: 8px; font-size: 11pt; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; }

    .stu-bar { display: table; width: 100%; background: #eff6ff; border: 1px solid #bfdbfe; padding: 6px 10px; margin-bottom: 8px; border-radius: 3px; }
    .stu-photo-cell { display: table-cell; width: 52px; vertical-align: middle; }
    .stu-photo { width: 46px; height: 54px; background: #dbeafe; border: 1.5px solid #1e3a8a; display: inline-block; text-align: center; line-height: 54px; color: #1e3a8a; font-size: 16pt; font-weight: bold; overflow: hidden; }
    .stu-photo img { width: 100%; height: 100%; object-fit: cover; }
    .stu-info-cell { display: table-cell; vertical-align: middle; padding-left: 12px; }
    .stu-name { font-size: 14pt; font-weight: bold; color: #1e3a8a; line-height: 1; }
    .stu-meta { font-size: 8pt; color: #475569; margin-top: 3px; }
    .stu-meta strong { color: #1e293b; }

    .info-tbl { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .info-tbl td { border: 1px solid #cbd5e1; padding: 4px 8px; width: 25%; vertical-align: top; }
    .info-tbl .lbl { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .info-tbl .val { font-weight: bold; font-size: 9pt; color: #1e293b; }

    .marks { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 8.5pt; border: 1px solid #1e3a8a; table-layout: fixed; }
    .marks th { background: #1e3a8a; color: #fff; padding: 5px 6px; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px; border-right: 1px solid #3b82f6; text-align: center; }
    .marks th:last-child { border-right: none; }
    .marks th:nth-child(2) { text-align: left; padding-left: 10px; }
    .marks td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; }
    .marks td:last-child { border-right: none; }
    .marks tr:nth-child(even) td { background: #f8fafc; }
    .subj-name { font-weight: 600; padding-left: 10px; }
    .subj-code { font-size: 7.5pt; color: #64748b; font-weight: normal; }
    .t-c { text-align: center; }
    .m-val { text-align: center; font-weight: bold; font-size: 10pt; }
    .fail { color: #dc2626; }
    .pass { color: #059669; }
    .ab-badge { color: #dc2626; font-weight: bold; background: #fee2e2; padding: 1px 4px; border-radius: 2px; font-size: 8pt; }
    .grace { color: #d97706; font-weight: bold; font-size: 7.5pt; }
    .total-row td { background: #1e3a8a !important; color: #fff !important; font-weight: bold; padding: 6px 6px !important; font-size: 10pt; border: none !important; }

    .sum-tbl { width: 100%; border-collapse: separate; border-spacing: 4px 0; table-layout: fixed; margin-bottom: 8px; }
    .sum-tbl td { border: 1px solid #cbd5e1; padding: 5px; text-align: center; background: #f8fafc; border-radius: 2px; width: 20%; }
    .sum-lbl { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .sum-val { font-size: 14pt; font-weight: bold; color: #1e293b; line-height: 1; margin-top: 2px; }
    .sum-sub { font-size: 7pt; color: #94a3b8; margin-top: 1px; }

    .verdict { padding: 8px 15px; text-align: center; border-radius: 3px; margin-bottom: 8px; display: table; width: 100%; }
    .vd-pass { background: #059669; color: #fff; }
    .vd-fail { background: #dc2626; color: #fff; }
    .vd-l { display: table-cell; text-align: left; width: 50%; vertical-align: middle; }
    .vd-r { display: table-cell; text-align: right; width: 50%; vertical-align: middle; font-size: 9pt; }
    .vd-lbl { font-size: 8pt; text-transform: uppercase; letter-spacing: 2px; opacity: 0.85; }
    .vd-val { font-size: 16pt; font-weight: bold; letter-spacing: 3px; line-height: 1.1; }

    .sig { display: table; width: 100%; margin-top: 14px; margin-bottom: 5px; }
    .sig-cell { display: table-cell; width: 33.33%; text-align: center; padding: 0 10px; vertical-align: bottom; }
    .sig-img { height: 24px; margin-bottom: 3px; }
    .sig-img img { height: 100%; }
    .sig-line { border-top: 1px solid #334155; padding-top: 3px; font-size: 8.5pt; font-weight: bold; }
    .sig-role { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 1px; }

    .ftr { display: table; width: 100%; margin-top: 8px; padding-top: 5px; border-top: 1px solid #e2e8f0; font-size: 7pt; color: #94a3b8; }
    .ftr-l { display: table-cell; text-align: left; }
    .ftr-r { display: table-cell; text-align: right; }

    /* COVER PAGE */
    .cover {
        width: 100%;
        min-height: 260mm;
        padding: 30mm 10mm;
        background: #1e3a8a;
        color: #fff;
        position: relative;
        page-break-after: always;
    }
    .cover-center { text-align: center; }
    .cover-logo-box { margin-bottom: 25mm; }
    .cover-logo-box img { width: 100px; height: 100px; object-fit: contain; filter: brightness(0) invert(1); }
    .cover-logo-ph { width: 100px; height: 100px; display: inline-block; background: rgba(255,255,255,0.15); border-radius: 8px; border: 3px solid rgba(255,255,255,0.3); line-height: 100px; font-size: 50pt; font-weight: bold; color: #fff; }
    .cover-school { font-size: 22pt; font-weight: bold; text-transform: uppercase; letter-spacing: 3px; margin-bottom: 18mm; }
    .cover-heading { font-size: 34pt; font-weight: 900; letter-spacing: 2px; line-height: 1; margin-bottom: 6mm; }
    .cover-sub { font-size: 12pt; letter-spacing: 2px; text-transform: uppercase; opacity: 0.85; margin-bottom: 30mm; }
    .cover-box { margin: 0 auto; max-width: 300px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.2); border-radius: 6px; padding: 12px 20px; font-size: 10pt; }
    .cover-box .lbl { font-size: 8pt; opacity: 0.7; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 2px; }
    .cover-box .val { font-size: 16pt; font-weight: bold; margin-bottom: 5px; }
    .cover-box .meta { font-size: 9pt; opacity: 0.85; }
    .cover-footer { margin-top: 40mm; font-size: 9pt; opacity: 0.7; text-align: center; }
</style>
</head>
<body>

<!-- COVER PAGE -->
<div class="cover">
    <div class="cover-center">
        <div class="cover-logo-box">
            @if(!empty($school->logo) && file_exists(public_path('storage/' . $school->logo)))
                <img src="{{ public_path('storage/' . $school->logo) }}" alt="">
            @else
                <span class="cover-logo-ph">{{ strtoupper(substr($school->name ?? 'S', 0, 1)) }}</span>
            @endif
        </div>
        <div class="cover-school">{{ $school->name ?? 'School' }}</div>
        <div class="cover-heading">MARK SHEETS</div>
        <div class="cover-sub">— {{ $exam->name }} —</div>
        <div class="cover-box">
            <div class="lbl">Section</div>
            <div class="val">{{ $schoolClass->name }} — {{ $section->name }}</div>
            <div class="meta">{{ $sheets->count() }} Students · {{ $academicSession->name ?? '' }}</div>
        </div>
        <div class="cover-footer">Generated on {{ now()->format('d M Y, h:i A') }}</div>
    </div>
</div>

<!-- INDIVIDUAL SHEETS -->
@foreach($sheets as $idx => $sheet)
    @php $student = $sheet->student; $result = $sheet->result; $subjectResults = $sheet->subjectResults; @endphp
    <div class="sheet"{{ $loop->last ? ' style="page-break-after: auto"' : '' }}>
    <div class="frame"><div class="frame-inner">

        <!-- Header -->
        <div class="hdr">
            <div class="hdr-logo">
                @if(!empty($school->logo) && file_exists(public_path('storage/' . $school->logo)))
                    <img src="{{ public_path('storage/' . $school->logo) }}" alt="">
                @else
                    <span class="logo-ph">{{ strtoupper(substr($school->name ?? 'S', 0, 1)) }}</span>
                @endif
            </div>
            <div class="hdr-center">
                <div class="sch-tag">Official Mark Sheet</div>
                <div class="sch-name">{{ $school->name ?? 'School' }}</div>
                <div class="sch-addr">
                    {{ $school->address ?? '' }}@if($school->phone ?? false) · Ph: {{ $school->phone }}@endif
                </div>
            </div>
            <div class="hdr-right">
                <div class="lbl">Serial No.</div>
                <div class="val">{{ strtoupper(substr(md5($result->id), 0, 8)) }}</div>
                <div class="lbl" style="margin-top:4px">Issue Date</div>
                <div class="val">{{ now()->format('d M Y') }}</div>
            </div>
        </div>

        <div class="title-bar">{{ $exam->name }}</div>

        <!-- Student Bar -->
        <div class="stu-bar">
            <div class="stu-photo-cell">
                <div class="stu-photo">
                    @if(!empty($student->photo) && file_exists(public_path('storage/' . $student->photo)))
                        <img src="{{ public_path('storage/' . $student->photo) }}" alt="">
                    @else
                        {{ strtoupper(substr($student->name ?? 'S', 0, 1)) }}
                    @endif
                </div>
            </div>
            <div class="stu-info-cell">
                <div class="stu-name">{{ $student->name }}</div>
                <div class="stu-meta">
                    <strong>Adm:</strong> {{ $student->admission_no }} &nbsp;&nbsp;
                    <strong>Roll:</strong> {{ $student->roll_no ?? '-' }} &nbsp;&nbsp;
                    <strong>Class:</strong> {{ $schoolClass->name }} &nbsp;&nbsp;
                    <strong>Section:</strong> {{ $section->name }}
                </div>
            </div>
        </div>

        <!-- Info Grid -->
        <table class="info-tbl">
            <tr>
                <td><div class="lbl">Father's Name</div><div class="val">{{ $student->father_name ?? '-' }}</div></td>
                <td><div class="lbl">Mother's Name</div><div class="val">{{ $student->mother_name ?? '-' }}</div></td>
                <td><div class="lbl">Date of Birth</div><div class="val">{{ $student->date_of_birth ? \Carbon\Carbon::parse($student->date_of_birth)->format('d M Y') : '-' }}</div></td>
                <td><div class="lbl">Session</div><div class="val">{{ $academicSession->name ?? '-' }}</div></td>
            </tr>
        </table>

        <!-- Marks Table -->
        <table class="marks">
            <thead>
                <tr>
                    <th style="width:5%">#</th>
                    <th style="width:30%">Subject</th>
                    <th style="width:9%">Max</th>
                    <th style="width:9%">Pass</th>
                    <th style="width:13%">Obtained</th>
                    <th style="width:9%">%</th>
                    <th style="width:10%">Grade</th>
                    <th style="width:15%">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($subjectResults as $i => $sr)
                <tr>
                    <td class="t-c" style="color:#64748b">{{ $i + 1 }}</td>
                    <td class="subj-name">
                        {{ $sr['subject_name'] }}
                        @if(!empty($sr['subject_code']))<span class="subj-code">({{ $sr['subject_code'] }})</span>@endif
                    </td>
                    <td class="t-c">{{ $sr['total_marks'] }}</td>
                    <td class="t-c">{{ $sr['passing_marks'] }}</td>
                    <td class="m-val {{ $sr['failed'] ? 'fail' : 'pass' }}">
                        @if($sr['is_absent'])
                            <span class="ab-badge">AB</span>
                        @else
                            {{ $sr['obtained'] }}@if($sr['grace_marks'] > 0) <span class="grace">+{{ $sr['grace_marks'] }}</span>@endif
                        @endif
                    </td>
                    <td class="t-c">{{ number_format($sr['percentage'], 1) }}</td>
                    <td class="t-c" style="font-weight:bold;color:#1e3a8a">{{ $sr['grade'] }}</td>
                    <td class="t-c" style="font-weight:bold;color:{{ $sr['failed'] ? '#dc2626' : '#059669' }}">
                        {{ $sr['failed'] ? 'FAIL' : 'PASS' }}
                    </td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="2" style="text-align:right;padding-right:10px">GRAND TOTAL</td>
                    <td class="t-c">{{ $result->total_marks }}</td>
                    <td class="t-c">—</td>
                    <td class="t-c">{{ $result->obtained_marks }}</td>
                    <td class="t-c">{{ number_format($result->percentage, 2) }}</td>
                    <td class="t-c">{{ $result->grade ?? '-' }}</td>
                    <td class="t-c">{{ $result->is_passed ? 'PASS' : 'FAIL' }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Summary -->
        <table class="sum-tbl">
            <tr>
                <td><div class="sum-lbl">Total</div><div class="sum-val">{{ $result->total_marks }}</div></td>
                <td><div class="sum-lbl">Obtained</div><div class="sum-val" style="color:#1e3a8a">{{ $result->obtained_marks }}</div></td>
                <td><div class="sum-lbl">Percentage</div><div class="sum-val" style="color:#059669">{{ number_format($result->percentage, 2) }}%</div></td>
                <td><div class="sum-lbl">Grade</div><div class="sum-val" style="color:#7c3aed">{{ $result->grade ?? '-' }}</div><div class="sum-sub">GP: {{ $result->grade_point ?? '-' }}</div></td>
                <td><div class="sum-lbl">Position</div><div class="sum-val" style="color:#d97706">#{{ $result->position ?? '-' }}</div><div class="sum-sub">of {{ $result->total_students ?? '-' }}</div></td>
            </tr>
        </table>

        <!-- Verdict -->
        <div class="verdict {{ $result->is_passed ? 'vd-pass' : 'vd-fail' }}">
            <div class="vd-l">
                <div class="vd-lbl">Final Result</div>
                <div class="vd-val">{{ $result->is_passed ? 'PASSED' : 'FAILED' }}</div>
            </div>
            <div class="vd-r">
                <strong>{{ $result->subjects_passed ?? 0 }}</strong> Passed &nbsp;·&nbsp;
                <strong>{{ $result->subjects_failed ?? 0 }}</strong> Failed<br>
                <span style="font-size:7.5pt;opacity:0.85">of {{ count($subjectResults) }} subjects</span>
            </div>
        </div>

        <!-- Signatures -->
        <div class="sig">
            <div class="sig-cell">
                <div class="sig-img"></div>
                <div class="sig-line">Class Teacher</div>
                <div class="sig-role">Signature &amp; Date</div>
            </div>
            <div class="sig-cell">
                <div class="sig-img">
                    @if(!empty($school->principal_signature) && file_exists(public_path('storage/' . $school->principal_signature)))
                        <img src="{{ public_path('storage/' . $school->principal_signature) }}" alt="">
                    @endif
                </div>
                <div class="sig-line">Principal</div>
                <div class="sig-role">School Head</div>
            </div>
            <div class="sig-cell">
                <div class="sig-img"></div>
                <div class="sig-line">Controller of Examinations</div>
                <div class="sig-role">DDO Office</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="ftr">
            <div class="ftr-l">Doc: <strong>RC-{{ $result->id }}-{{ $student->id }}</strong> · Page {{ $idx + 1 }} of {{ $sheets->count() }}</div>
            <div class="ftr-r">{{ now()->format('d M Y, h:i A') }}</div>
        </div>

    </div></div>
    </div>
@endforeach

</body>
</html>
