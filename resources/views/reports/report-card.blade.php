<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Mark Sheet — {{ $student->name }}</title>
<style>
    @page { size: A4 portrait; margin: 10mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1e293b; line-height: 1.3; }

    .sheet { width: 100%; position: relative; }

    /* Double border frame */
    .frame { border: 2px solid #1e3a8a; padding: 5px; }
    .frame-inner { border: 1px solid #1e3a8a; padding: 8px; }

    /* HEADER */
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

    /* TITLE BAR */
    .title-bar {
        background: #1e3a8a;
        color: #fff;
        text-align: center;
        padding: 5px 10px;
        margin-bottom: 8px;
        font-size: 11pt;
        font-weight: bold;
        letter-spacing: 3px;
        text-transform: uppercase;
    }

    /* STUDENT BAR */
    .stu-bar { display: table; width: 100%; background: #eff6ff; border: 1px solid #bfdbfe; padding: 6px 10px; margin-bottom: 8px; border-radius: 3px; }
    .stu-photo-cell { display: table-cell; width: 52px; vertical-align: middle; }
    .stu-photo { width: 46px; height: 54px; background: #dbeafe; border: 1.5px solid #1e3a8a; display: inline-block; text-align: center; line-height: 54px; color: #1e3a8a; font-size: 16pt; font-weight: bold; overflow: hidden; }
    .stu-photo img { width: 100%; height: 100%; object-fit: cover; }
    .stu-info-cell { display: table-cell; vertical-align: middle; padding-left: 12px; }
    .stu-name { font-size: 14pt; font-weight: bold; color: #1e3a8a; line-height: 1; }
    .stu-meta { font-size: 8pt; color: #475569; margin-top: 3px; }
    .stu-meta strong { color: #1e293b; }

    /* INFO GRID */
    .info-tbl { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .info-tbl td { border: 1px solid #cbd5e1; padding: 4px 8px; width: 25%; vertical-align: top; }
    .info-tbl .lbl { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .info-tbl .val { font-weight: bold; font-size: 9pt; color: #1e293b; }

    /* MARKS TABLE */
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

    .total-row td {
        background: #1e3a8a !important;
        color: #fff !important;
        font-weight: bold;
        padding: 6px 6px !important;
        font-size: 10pt;
        border: none !important;
    }

    /* SUMMARY ROW */
    .sum-tbl { width: 100%; border-collapse: separate; border-spacing: 4px 0; table-layout: fixed; margin-bottom: 8px; }
    .sum-tbl td { border: 1px solid #cbd5e1; padding: 5px; text-align: center; background: #f8fafc; border-radius: 2px; width: 20%; }
    .sum-lbl { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .sum-val { font-size: 14pt; font-weight: bold; color: #1e293b; line-height: 1; margin-top: 2px; }
    .sum-sub { font-size: 7pt; color: #94a3b8; margin-top: 1px; }

    /* VERDICT */
    .verdict { padding: 8px 15px; text-align: center; border-radius: 3px; margin-bottom: 8px; display: table; width: 100%; }
    .vd-pass { background: #059669; color: #fff; }
    .vd-fail { background: #dc2626; color: #fff; }
    .vd-l { display: table-cell; text-align: left; width: 50%; vertical-align: middle; }
    .vd-r { display: table-cell; text-align: right; width: 50%; vertical-align: middle; font-size: 9pt; }
    .vd-lbl { font-size: 8pt; text-transform: uppercase; letter-spacing: 2px; opacity: 0.85; }
    .vd-val { font-size: 16pt; font-weight: bold; letter-spacing: 3px; line-height: 1.1; }

    /* GRADING */
    .grading { margin-bottom: 8px; }
    .grading-title { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; margin-bottom: 3px; }
    .grading-tbl { width: 100%; border-collapse: collapse; font-size: 8pt; }
    .grading-tbl th, .grading-tbl td { border: 1px solid #cbd5e1; padding: 2px 5px; text-align: center; }
    .grading-tbl th { background: #f1f5f9; color: #334155; font-weight: bold; font-size: 7pt; text-transform: uppercase; }
    .grade-ltr { font-weight: bold; color: #1e3a8a; font-size: 9pt; }

    /* SIGNATURES */
    .sig { display: table; width: 100%; margin-top: 14px; margin-bottom: 5px; }
    .sig-cell { display: table-cell; width: 33.33%; text-align: center; padding: 0 10px; vertical-align: bottom; }
    .sig-img { height: 24px; margin-bottom: 3px; }
    .sig-img img { height: 100%; }
    .sig-line { border-top: 1px solid #334155; padding-top: 3px; font-size: 8.5pt; font-weight: bold; }
    .sig-role { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 1px; }

    /* FOOTER */
    .ftr { display: table; width: 100%; margin-top: 8px; padding-top: 5px; border-top: 1px solid #e2e8f0; font-size: 7pt; color: #94a3b8; }
    .ftr-l { display: table-cell; text-align: left; }
    .ftr-r { display: table-cell; text-align: right; }
</style>
</head>
<body>
<div class="sheet">
<div class="frame"><div class="frame-inner">

    <!-- HEADER -->
    <div class="hdr">
        <div class="hdr-logo">
            @if(!empty($school->logo) && $school?->getLogoAbsolutePath())
                <img src="{{ $school?->getLogoAbsolutePath() }}" alt="">
            @else
                <span class="logo-ph">{{ strtoupper(substr($school->name ?? 'S', 0, 1)) }}</span>
            @endif
        </div>
        <div class="hdr-center">
            <div class="sch-tag">Official Mark Sheet</div>
            <div class="sch-name">{{ $school->name ?? 'School Name' }}</div>
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

    <!-- STUDENT BAR -->
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
                <strong>Class:</strong> {{ $schoolClass->name ?? '-' }} &nbsp;&nbsp;
                <strong>Section:</strong> {{ $section->name ?? '-' }}
            </div>
        </div>
    </div>

    <!-- INFO GRID -->
    <table class="info-tbl">
        <tr>
            <td><div class="lbl">Father's Name</div><div class="val">{{ $student->father_name ?? '-' }}</div></td>
            <td><div class="lbl">Mother's Name</div><div class="val">{{ $student->mother_name ?? '-' }}</div></td>
            <td><div class="lbl">Date of Birth</div><div class="val">{{ $student->date_of_birth ? \Carbon\Carbon::parse($student->date_of_birth)->format('d M Y') : '-' }}</div></td>
            <td><div class="lbl">Session</div><div class="val">{{ $academicSession->name ?? '-' }}</div></td>
        </tr>
    </table>

    <!-- MARKS TABLE -->
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
                    {{ $sr['failed'] ? 'RETRY' : 'PASS' }}
                </td>
            </tr>
            {{-- Primary section per-term breakdown row — only rendered when
                 the annual aggregator stamped a primary_breakdown on this
                 subject. Shows T1/T2/Final raw obtained so parents see the
                 22 + 25 + 32 → 79/100 trail behind the annual number. --}}
            @if(($isPrimary ?? false) && !empty($sr['primary_breakdown']))
            @php($pb = $sr['primary_breakdown'])
            <tr class="primary-breakdown-row">
                <td></td>
                <td colspan="7" style="font-size:7.5pt;color:#475569;padding:2px 6px 4px;background:#f8fafc">
                    <span style="color:#64748b;text-transform:uppercase;letter-spacing:0.5px;font-size:6.5pt">Term breakdown:</span>
                    <span style="margin-left:6px">1st Term <strong>{{ $pb['first']['obtained'] ?? '-' }}</strong>/{{ $pb['first']['total'] ?? '-' }}</span>
                    <span style="margin-left:10px">·</span>
                    <span style="margin-left:6px">2nd Term <strong>{{ $pb['second']['obtained'] ?? '-' }}</strong>/{{ $pb['second']['total'] ?? '-' }}</span>
                    <span style="margin-left:10px">·</span>
                    <span style="margin-left:6px">Final <strong>{{ $pb['final']['obtained'] ?? '-' }}</strong>/{{ $pb['final']['total'] ?? '-' }}</span>
                </td>
            </tr>
            @endif
            @endforeach
            {{-- Primary section assessment row — 10-mark overall conduct /
                 participation score entered by the class teacher. Spec says
                 it adds to the grand total AND a sub-pass forces overall
                 Fail (the aggregator already handles is_passed). --}}
            @if(($isPrimary ?? false) && !empty($assessment))
            <tr>
                <td class="t-c" style="color:#64748b">—</td>
                <td class="subj-name" style="background:#ecfdf5">
                    Overall Assessment
                    <span class="subj-code" style="color:#059669">(conduct, participation, attendance)</span>
                </td>
                <td class="t-c">{{ $assessment['total'] }}</td>
                <td class="t-c">{{ $assessment['passing'] }}</td>
                <td class="m-val {{ $assessment['passed'] ? 'pass' : 'fail' }}">
                    {{ $assessment['obtained'] }}
                </td>
                <td class="t-c">{{ $assessment['total'] > 0 ? number_format($assessment['obtained'] / $assessment['total'] * 100, 1) : '-' }}</td>
                <td class="t-c">—</td>
                <td class="t-c" style="font-weight:bold;color:{{ $assessment['passed'] ? '#059669' : '#dc2626' }}">
                    {{ $assessment['passed'] ? 'PASS' : 'RETRY' }}
                </td>
            </tr>
            @endif
            <tr class="total-row">
                <td colspan="2" style="text-align:right;padding-right:10px">GRAND TOTAL</td>
                <td class="t-c">{{ $result->total_marks }}</td>
                <td class="t-c">—</td>
                <td class="t-c">{{ $result->obtained_marks }}</td>
                <td class="t-c">{{ number_format($result->percentage, 2) }}</td>
                <td class="t-c">{{ $result->grade ?? '-' }}</td>
                <td class="t-c">{{ $result->is_passed ? 'PASS' : 'RETRY' }}</td>
            </tr>
        </tbody>
    </table>

    <!-- SUMMARY -->
    <table class="sum-tbl">
        <tr>
            <td><div class="sum-lbl">Total</div><div class="sum-val">{{ $result->total_marks }}</div></td>
            <td><div class="sum-lbl">Obtained</div><div class="sum-val" style="color:#1e3a8a">{{ $result->obtained_marks }}</div></td>
            <td><div class="sum-lbl">Percentage</div><div class="sum-val" style="color:#059669">{{ number_format($result->percentage, 2) }}%</div></td>
            <td><div class="sum-lbl">Grade</div><div class="sum-val" style="color:#7c3aed">{{ $result->grade ?? '-' }}</div><div class="sum-sub">GP: {{ $result->grade_point ?? '-' }}</div></td>
            <td><div class="sum-lbl">Position</div><div class="sum-val" style="color:#d97706">#{{ $result->position ?? '-' }}</div><div class="sum-sub">of {{ $result->total_students ?? '-' }}</div></td>
        </tr>
    </table>

    <!-- VERDICT -->
    <div class="verdict {{ $result->is_passed ? 'vd-pass' : 'vd-fail' }}">
        <div class="vd-l">
            <div class="vd-lbl">Final Result</div>
            <div class="vd-val">{{ $result->is_passed ? 'PASSED' : 'NEEDS RETRY' }}</div>
        </div>
        <div class="vd-r">
            <strong>{{ $result->subjects_passed ?? 0 }}</strong> Passed &nbsp;·&nbsp;
            <strong>{{ $result->subjects_failed ?? 0 }}</strong> Retry<br>
            <span style="font-size:7.5pt;opacity:0.85">of {{ count($subjectResults) }} subjects</span>
        </div>
    </div>

    <!-- GRADING -->
    @if($gradingEntries->isNotEmpty())
    <div class="grading">
        <div class="grading-title">Grading Scale</div>
        <table class="grading-tbl">
            <tr>
                <th style="width:70px">Grade</th>
                @foreach($gradingEntries as $e)<th><span class="grade-ltr">{{ $e->grade }}</span></th>@endforeach
            </tr>
            <tr>
                <td style="font-weight:bold;color:#64748b">Range (%)</td>
                @foreach($gradingEntries as $e)<td>{{ number_format($e->min_percentage, 0) }}–{{ number_format($e->max_percentage, 0) }}</td>@endforeach
            </tr>
            <tr>
                <td style="font-weight:bold;color:#64748b">Remark</td>
                @foreach($gradingEntries as $e)<td style="font-size:7.5pt">{{ $e->remark ?? $e->label ?? '-' }}</td>@endforeach
            </tr>
        </table>
    </div>
    @endif

    <!-- SIGNATURES -->
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

    <!-- FOOTER -->
    <div class="ftr">
        <div class="ftr-l">Doc: <strong>RC-{{ $result->id }}-{{ $student->id }}</strong> · {{ now()->format('d M Y, h:i A') }}</div>
        <div class="ftr-r">Computer-generated — no manual signature required</div>
    </div>

</div></div>
</div>
</body>
</html>
