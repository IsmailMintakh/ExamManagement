@php
    $logoPath = !empty($school?->logo) && file_exists(public_path('storage/' . $school->logo))
        ? public_path('storage/' . $school->logo) : null;
    $sigPath = !empty($school?->principal_signature ?? null) && file_exists(public_path('storage/' . $school->principal_signature))
        ? public_path('storage/' . $school->principal_signature) : null;
    $principalName = !empty($school?->principal_name)
        ? $school->principal_name
        : ($school?->principal?->name ?? null);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Progress Booklets — {{ $section->schoolClass->name }} {{ $section->name }}</title>
<style>
    @page { size: A4 portrait; margin: 12mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1e293b; line-height: 1.35; }

    .frame { border: 2px solid #1e3a8a; padding: 5px; }
    .frame-inner { border: 1px solid #1e3a8a; padding: 8px; }

    .student-page { page-break-after: always; }
    .student-page:last-child { page-break-after: auto; }

    .hdr { display: table; width: 100%; padding-bottom: 8px; border-bottom: 2px solid #1e3a8a; margin-bottom: 10px; }
    .hdr-logo { display: table-cell; width: 60px; vertical-align: middle; }
    .hdr-logo img { width: 56px; height: 56px; object-fit: contain; }
    .hdr-logo-ph { width: 56px; height: 56px; background: #1e3a8a; border-radius: 50%; display: inline-block; line-height: 56px; text-align: center; color: #fef3c7; font-size: 20pt; font-weight: bold; }
    .hdr-center { display: table-cell; vertical-align: middle; padding: 0 12px; text-align: center; }
    .sch-tag { font-size: 7pt; color: #1e3a8a; text-transform: uppercase; letter-spacing: 2.5px; font-weight: bold; }
    .sch-name { font-size: 14pt; font-weight: bold; color: #0f172a; text-transform: uppercase; line-height: 1; margin-top: 2px; }

    .title-bar { background: #1e3a8a; color: #fff; text-align: center; padding: 6px 10px; margin-bottom: 10px; font-size: 11pt; font-weight: bold; letter-spacing: 3px; text-transform: uppercase; }

    .profile { display: table; width: 100%; margin-bottom: 10px; border: 1px solid #cbd5e1; border-radius: 4px; }
    .profile-cell { display: table-cell; padding: 4px 8px; vertical-align: top; }
    .profile-cell strong { color: #1e3a8a; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 1px; }
    .profile-cell span { font-size: 9.5pt; font-weight: bold; }

    table.exams { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-bottom: 8px; }
    table.exams th, table.exams td { border: 0.7px solid #94a3b8; padding: 4px 5px; }
    table.exams th { background: #1e3a8a; color: #fff; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.5px; text-align: center; font-weight: bold; }
    table.exams td.center { text-align: center; }

    .pass-yes { color: #047857; font-weight: bold; }
    .pass-no { color: #b91c1c; font-weight: bold; }
    .grade-pill { display: inline-block; padding: 1px 5px; border-radius: 3px; background: #dbeafe; color: #1e3a8a; font-weight: bold; font-size: 7.5pt; }

    .empty-msg { text-align: center; padding: 14px; color: #64748b; font-style: italic; }

    .sig-row { display: table; width: 100%; margin-top: 14px; padding-top: 8px; border-top: 1px dashed #94a3b8; }
    .sig-cell { display: table-cell; width: 50%; text-align: center; vertical-align: bottom; padding: 0 12px; }
    .sig-img { height: 22px; margin-bottom: 1px; }
    .sig-line { border-top: 0.7px solid #1e3a8a; padding-top: 2px; font-size: 8.5pt; font-weight: bold; }
    .sig-role { font-size: 6.5pt; color: #64748b; text-transform: uppercase; letter-spacing: 0.6px; margin-top: 1px; }
</style>
</head>
<body>

@foreach($students as $student)
    @php
        $studentResults = $resultsByStudent->get($student->id, collect());
        $totalExams = $studentResults->count();
        $passedCount = $studentResults->where('is_passed', true)->count();
        $avgPct = $totalExams > 0 ? round($studentResults->avg('percentage'), 2) : 0;
    @endphp
    <div @class(['student-page' => !$loop->last])>
        <div class="frame"><div class="frame-inner">

            <div class="hdr">
                <div class="hdr-logo">
                    @if($logoPath)
                        <img src="{{ $logoPath }}" alt="">
                    @else
                        <span class="hdr-logo-ph">{{ strtoupper(substr($school?->name ?? 'S', 0, 1)) }}</span>
                    @endif
                </div>
                <div class="hdr-center">
                    <div class="sch-tag">Student Progress Booklet</div>
                    <div class="sch-name">{{ $school?->name }}</div>
                </div>
            </div>

            <div class="title-bar">{{ $student->name }}</div>

            <div class="profile">
                <div class="profile-cell" style="width: 25%;"><strong>Roll #</strong><span>{{ $student->roll_no ?? '—' }}</span></div>
                <div class="profile-cell" style="width: 25%;"><strong>Adm #</strong><span>{{ $student->admission_no ?? '—' }}</span></div>
                <div class="profile-cell" style="width: 25%;"><strong>Father</strong><span>{{ $student->father_name ?? '—' }}</span></div>
                <div class="profile-cell" style="width: 25%;"><strong>Class · Section</strong><span>{{ $section->schoolClass->name }} · {{ $section->name }}</span></div>
            </div>

            @if($studentResults->count())
                <table class="exams">
                    <thead>
                        <tr>
                            <th style="text-align: left;">Exam</th>
                            <th>Marks</th>
                            <th>%</th>
                            <th>Grade</th>
                            <th>Position</th>
                            <th>Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($studentResults as $r)
                            <tr>
                                <td><strong>{{ $r->exam?->name ?? '—' }}</strong></td>
                                <td class="center">{{ number_format($r->obtained_marks, 1) }} / {{ number_format($r->total_marks, 0) }}</td>
                                <td class="center"><strong>{{ number_format($r->percentage, 1) }}%</strong></td>
                                <td class="center"><span class="grade-pill">{{ $r->grade ?? '—' }}</span></td>
                                <td class="center">{{ $r->position ?? '—' }}</td>
                                <td class="center {{ $r->is_passed ? 'pass-yes' : 'pass-no' }}">
                                    {{ $r->is_passed ? 'PASS' : 'RETRY' }}
                                </td>
                            </tr>
                        @endforeach
                        <tr style="background: #f1f5f9;">
                            <td><strong>Summary</strong></td>
                            <td class="center" colspan="2"><strong>Avg {{ $avgPct }}%</strong></td>
                            <td class="center pass-yes">{{ $passedCount }} passed</td>
                            <td class="center pass-no">{{ $totalExams - $passedCount }} retry</td>
                            <td class="center"><strong>{{ $totalExams }} exam(s)</strong></td>
                        </tr>
                    </tbody>
                </table>
            @else
                <div class="empty-msg">No exam results available for this student yet.</div>
            @endif

            {{-- Primary section: render the overall Assessment card per
                 student. We pull each student's row from the pre-loaded map
                 instead of querying inside the loop. --}}
            @if($isPrimary ?? false)
                @php $am = $assessmentByStudent->get($student->id); @endphp
                @if($am)
                    @php
                        $asmtPassed = (float) $am->marks_obtained >= (float) $am->passing_marks;
                    @endphp
                    <div style="border: 1.5px solid {{ $asmtPassed ? '#059669' : '#dc2626' }}; border-radius: 4px; padding: 8px 12px; margin: 6px 0 8px; background: {{ $asmtPassed ? '#ecfdf5' : '#fef2f2' }};">
                        <div style="display: table; width: 100%;">
                            <div style="display: table-cell; vertical-align: middle;">
                                <div style="font-size: 8.5pt; font-weight: bold; color: #334155;">Overall Assessment</div>
                                <div style="font-size: 7.5pt; color: #64748b;">Conduct · Participation · Attendance</div>
                            </div>
                            <div style="display: table-cell; text-align: right; vertical-align: middle; width: 35%;">
                                <span style="font-size: 13pt; font-weight: bold; color: {{ $asmtPassed ? '#059669' : '#dc2626' }};">
                                    {{ (float) $am->marks_obtained }}<span style="font-size: 9pt; color: #94a3b8; font-weight: normal;">/{{ (float) $am->marks_total }}</span>
                                </span>
                                <span style="font-size: 7pt; font-weight: bold; color: {{ $asmtPassed ? '#047857' : '#b91c1c' }}; margin-left: 6px;">
                                    {{ $asmtPassed ? 'PASS' : 'RETRY' }}
                                </span>
                            </div>
                        </div>
                        @if(!empty($am->remarks))
                            <div style="border-top: 1px dashed #cbd5e1; margin-top: 5px; padding-top: 4px; font-size: 7.5pt; color: #475569;">
                                <strong style="color: #1e3a8a;">Remarks:</strong> {{ $am->remarks }}
                            </div>
                        @endif
                    </div>
                @endif
            @endif

            <div class="sig-row">
                <div class="sig-cell">
                    <div class="sig-line">Class Teacher</div>
                    <div class="sig-role">Signature</div>
                </div>
                <div class="sig-cell">
                    @if($sigPath)<img src="{{ $sigPath }}" class="sig-img" alt="">@endif
                    <div class="sig-line">{{ $principalName ?? 'Principal' }}</div>
                    <div class="sig-role">Principal</div>
                </div>
            </div>

        </div></div>
    </div>
@endforeach

</body>
</html>
