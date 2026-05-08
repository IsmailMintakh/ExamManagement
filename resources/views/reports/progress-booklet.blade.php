@php
    $logoPath = !empty($school?->logo) && file_exists(public_path('storage/' . $school->logo))
        ? public_path('storage/' . $school->logo) : null;
    $sigPath = !empty($school?->principal_signature ?? null) && file_exists(public_path('storage/' . $school->principal_signature))
        ? public_path('storage/' . $school->principal_signature) : null;
    $principalName = !empty($school?->principal_name)
        ? $school->principal_name
        : ($school?->principal?->name ?? null);

    // Pull aggregate numbers across all results
    $totalExams = $results->count();
    $passedCount = $results->where('is_passed', true)->count();
    $avgPct = $totalExams > 0 ? round($results->avg('percentage'), 2) : 0;
    $bestExam = $results->sortByDesc('percentage')->first();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Progress Booklet — {{ $student->name }}</title>
<style>
    @page { size: A4 portrait; margin: 12mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1e293b; line-height: 1.35; }

    .frame { border: 2px solid #1e3a8a; padding: 5px; }
    .frame-inner { border: 1px solid #1e3a8a; padding: 8px; }

    .page { page-break-after: always; }
    .page:last-child { page-break-after: auto; }

    /* Cover */
    .cover-hdr { display: table; width: 100%; padding-bottom: 10px; border-bottom: 2.5px solid #1e3a8a; margin-bottom: 14px; }
    .cover-logo { display: table-cell; width: 70px; vertical-align: middle; }
    .cover-logo img { width: 64px; height: 64px; object-fit: contain; }
    .cover-logo-ph { width: 64px; height: 64px; background: #1e3a8a; border-radius: 50%; display: inline-block; line-height: 64px; text-align: center; color: #fff; font-size: 22pt; font-weight: bold; }
    .cover-center { display: table-cell; vertical-align: middle; padding: 0 12px; text-align: center; }
    .cover-tag { font-size: 8pt; color: #1e3a8a; text-transform: uppercase; letter-spacing: 3px; font-weight: bold; }
    .cover-name { font-size: 16pt; font-weight: bold; color: #0f172a; text-transform: uppercase; line-height: 1; margin-top: 3px; }
    .cover-addr { font-size: 8pt; color: #475569; margin-top: 3px; }

    .title-bar { background: #1e3a8a; color: #ffffff; text-align: center; padding: 8px 12px; margin-bottom: 14px; font-size: 13pt; font-weight: bold; letter-spacing: 4px; text-transform: uppercase; }

    /* Student profile card */
    .profile { display: table; width: 100%; margin-bottom: 14px; border: 1px solid #cbd5e1; border-radius: 4px; }
    .profile-photo { display: table-cell; width: 90px; padding: 8px; vertical-align: top; text-align: center; background: #f1f5f9; border-right: 1px solid #cbd5e1; }
    .profile-photo img { width: 80px; height: 100px; object-fit: cover; border: 1px solid #1e3a8a; }
    .profile-photo-ph { width: 80px; height: 100px; background: #1e3a8a; color: #fef3c7; line-height: 100px; text-align: center; font-size: 28pt; font-weight: bold; }
    .profile-data { display: table-cell; padding: 10px 14px; vertical-align: top; }
    .profile-row { display: table; width: 100%; padding: 2px 0; }
    .profile-l { display: table-cell; width: 110px; font-size: 8pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; font-weight: bold; }
    .profile-v { display: table-cell; font-size: 10pt; color: #0f172a; font-weight: bold; }

    /* Summary cards */
    .summary { display: table; width: 100%; margin-bottom: 14px; border-spacing: 6px 0; }
    .summary-cell { display: table-cell; width: 25%; padding: 8px 6px; border: 1px solid #cbd5e1; border-radius: 4px; text-align: center; vertical-align: middle; background: #f8fafc; }
    .summary-cell .lbl { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
    .summary-cell .val { font-size: 18pt; font-weight: bold; color: #1e3a8a; line-height: 1; margin-top: 3px; }
    .summary-cell.passed { background: #ecfdf5; border-color: #10b981; }
    .summary-cell.passed .val { color: #047857; }
    .summary-cell.failed { background: #fef2f2; border-color: #f87171; }
    .summary-cell.failed .val { color: #b91c1c; }
    .summary-cell.avg { background: #eff6ff; border-color: #3b82f6; }

    /* Results table */
    .section-title { font-size: 10pt; font-weight: bold; color: #1e3a8a; text-transform: uppercase; letter-spacing: 1.5px; padding-bottom: 3px; border-bottom: 1.5px solid #1e3a8a; margin-bottom: 6px; margin-top: 8px; }

    table.exams { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-bottom: 12px; }
    table.exams th, table.exams td { border: 0.7px solid #94a3b8; padding: 5px 6px; }
    table.exams th { background: #1e3a8a; color: #fff; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; font-weight: bold; }
    table.exams td.exam-name { font-weight: bold; }
    table.exams td.center { text-align: center; }
    table.exams td.percent { font-weight: bold; }
    .grade-pill { display: inline-block; padding: 1px 6px; border-radius: 3px; background: #dbeafe; color: #1e3a8a; font-weight: bold; font-size: 8pt; }
    .pass-yes { color: #047857; font-weight: bold; }
    .pass-no { color: #b91c1c; font-weight: bold; }

    /* Subject trend */
    table.trend { width: 100%; border-collapse: collapse; font-size: 8pt; }
    table.trend th, table.trend td { border: 0.7px solid #cbd5e1; padding: 4px; text-align: center; }
    table.trend th { background: #1e3a8a; color: #fff; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5px; }
    table.trend td.subj { text-align: left; font-weight: bold; padding-left: 6px; }

    /* Signature row */
    .sig-row { display: table; width: 100%; margin-top: 16px; padding-top: 10px; border-top: 1px dashed #94a3b8; }
    .sig-cell { display: table-cell; width: 50%; text-align: center; vertical-align: bottom; padding: 0 14px; }
    .sig-img { height: 28px; margin-bottom: 2px; }
    .sig-line { border-top: 0.7px solid #1e3a8a; padding-top: 3px; font-size: 9pt; font-weight: bold; }
    .sig-role { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px; margin-top: 1px; }

    .ftr { margin-top: 6px; font-size: 7pt; color: #94a3b8; text-align: center; font-style: italic; }
</style>
</head>
<body>

@php
    $photoPath = !empty($student->photo) && file_exists(public_path('storage/' . $student->photo))
        ? public_path('storage/' . $student->photo) : null;
@endphp

{{-- ─── PAGE 1 — Cover + Profile + Summary ─── --}}
<div class="frame"><div class="frame-inner">

    <div class="cover-hdr">
        <div class="cover-logo">
            @if($logoPath)
                <img src="{{ $logoPath }}" alt="">
            @else
                <span class="cover-logo-ph">{{ strtoupper(substr($school?->name ?? 'S', 0, 1)) }}</span>
            @endif
        </div>
        <div class="cover-center">
            <div class="cover-tag">Student Progress Booklet</div>
            <div class="cover-name">{{ $school?->name ?? 'School' }}</div>
            @if(!empty($school?->address))
                <div class="cover-addr">{{ $school->address }}</div>
            @endif
        </div>
    </div>

    <div class="title-bar">Academic Progress Report — Session {{ $session?->name ?? '—' }}</div>

    {{-- Student profile --}}
    <div class="profile">
        <div class="profile-photo">
            @if($photoPath)
                <img src="{{ $photoPath }}" alt="">
            @else
                <div class="profile-photo-ph">{{ strtoupper(substr($student->name ?? '?', 0, 1)) }}</div>
            @endif
            <p style="font-size: 7pt; color: #64748b; margin-top: 4px;">Roll #{{ $student->roll_no }}</p>
        </div>
        <div class="profile-data">
            <div class="profile-row"><div class="profile-l">Name</div><div class="profile-v">{{ $student->name }}</div></div>
            <div class="profile-row"><div class="profile-l">Father</div><div class="profile-v">{{ $student->father_name ?? '—' }}</div></div>
            <div class="profile-row"><div class="profile-l">Admission #</div><div class="profile-v">{{ $student->admission_no ?? '—' }}</div></div>
            <div class="profile-row"><div class="profile-l">Class · Section</div><div class="profile-v">{{ $student->schoolClass?->name }} · {{ $student->section?->name }}</div></div>
            <div class="profile-row"><div class="profile-l">Date of Birth</div><div class="profile-v">{{ $student->dob ? \Carbon\Carbon::parse($student->dob)->format('d M Y') : '—' }}</div></div>
            <div class="profile-row"><div class="profile-l">Guardian phone</div><div class="profile-v">{{ $student->guardian_phone ?? '—' }}</div></div>
        </div>
    </div>

    {{-- Summary stats --}}
    <div class="summary">
        <div class="summary-cell">
            <div class="lbl">Exams</div>
            <div class="val">{{ $totalExams }}</div>
        </div>
        <div class="summary-cell passed">
            <div class="lbl">Passed</div>
            <div class="val">{{ $passedCount }}</div>
        </div>
        <div class="summary-cell {{ $totalExams - $passedCount > 0 ? 'failed' : '' }}">
            <div class="lbl">Failed</div>
            <div class="val">{{ $totalExams - $passedCount }}</div>
        </div>
        <div class="summary-cell avg">
            <div class="lbl">Avg %</div>
            <div class="val">{{ $avgPct }}%</div>
        </div>
    </div>

    {{-- Exam-wise results --}}
    <p class="section-title">Exam Performance</p>
    @if($results->count())
        <table class="exams">
            <thead>
                <tr>
                    <th style="text-align: left;">Exam</th>
                    <th>Type</th>
                    <th>Marks</th>
                    <th>%</th>
                    <th>Grade</th>
                    <th>Position</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                @foreach($results as $r)
                    <tr>
                        <td class="exam-name">{{ $r->exam?->name ?? '—' }}</td>
                        <td class="center">{{ $r->exam?->examType?->name ?? '—' }}</td>
                        <td class="center">{{ number_format($r->obtained_marks, 1) }} / {{ number_format($r->total_marks, 0) }}</td>
                        <td class="center percent">{{ number_format($r->percentage, 1) }}%</td>
                        <td class="center"><span class="grade-pill">{{ $r->grade ?? '—' }}</span></td>
                        <td class="center">{{ $r->position ?? '—' }}</td>
                        <td class="center {{ $r->is_passed ? 'pass-yes' : 'pass-no' }}">
                            {{ $r->is_passed ? 'PASS' : 'FAIL' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="text-align: center; padding: 14px; color: #64748b; font-style: italic;">
            No exam results available for this session yet.
        </p>
    @endif

    {{-- Subject-wise trend --}}
    @if(count($subjectTrend) > 0 && $results->count() > 1)
        <p class="section-title">Subject-wise Trend</p>
        <table class="trend">
            <thead>
                <tr>
                    <th style="text-align: left; padding-left: 6px;">Subject</th>
                    @foreach($results as $r)
                        <th>{{ \Illuminate\Support\Str::limit($r->exam?->name ?? '—', 12) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($subjectTrend as $st)
                    <tr>
                        <td class="subj">{{ $st['subject_name'] }}</td>
                        @foreach($st['series'] as $point)
                            <td>
                                @if($point['is_absent'])
                                    <span style="color: #94a3b8; font-style: italic;">A</span>
                                @else
                                    {{ $point['percentage'] !== null ? $point['percentage'] . '%' : '—' }}
                                    @if($point['grade'])
                                        <br><span style="font-size: 6.5pt; color: #1e3a8a;">{{ $point['grade'] }}</span>
                                    @endif
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="sig-row">
        <div class="sig-cell">
            <div class="sig-line">Class Teacher</div>
            <div class="sig-role">Signature</div>
        </div>
        <div class="sig-cell">
            @if($sigPath)
                <img src="{{ $sigPath }}" class="sig-img" alt="">
            @endif
            <div class="sig-line">{{ $principalName ?? 'Principal' }}</div>
            <div class="sig-role">Principal — {{ $school?->name }}</div>
        </div>
    </div>

    <div class="ftr">Generated {{ now()->format('d M Y, h:i A') }} — handover at PTM</div>

</div></div>

</body>
</html>
