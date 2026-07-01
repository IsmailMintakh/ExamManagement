@php
    $logoPath = !empty($school->logo) && file_exists(public_path('storage/' . $school->logo))
        ? public_path('storage/' . $school->logo) : null;
    $principalSigPath = !empty($school->principal_signature) && file_exists(public_path('storage/' . $school->principal_signature))
        ? public_path('storage/' . $school->principal_signature) : null;
    $stampPath = !empty($school->school_stamp) && file_exists(public_path('storage/' . $school->school_stamp))
        ? public_path('storage/' . $school->school_stamp) : null;
    $controllerSigPath = !empty($school->exam_officer_signature) && file_exists(public_path('storage/' . $school->exam_officer_signature))
        ? public_path('storage/' . $school->exam_officer_signature) : null;
    $controllerName = $exam->examController?->name ?? ($school->exam_officer_name ?? null);
    // Class teacher = first section's teacher (a class result sheet may span
    // multiple sections; show the first one's teacher as a representative).
    $classTeacherName = $schoolClass->sections->pluck('classTeacher.name')->filter()->first();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Result Sheet - {{ $exam->name }}</title>
    <style>
        @page { size: A4 landscape; margin: 8mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #1a1a1a; }
        .header { display: table; width: 100%; padding-bottom: 8px; border-bottom: 2px solid #064e3b; margin-bottom: 8px; }
        .hdr-logo { display: table-cell; width: 60px; vertical-align: middle; }
        .hdr-logo img { width: 50px; height: 50px; object-fit: contain; }
        .logo-ph { width: 50px; height: 50px; background: #064e3b; border-radius: 50%; display: inline-block; line-height: 50px; text-align: center; color: #fff; font-size: 18pt; font-weight: bold; }
        .hdr-c { display: table-cell; text-align: center; vertical-align: middle; }
        .school-name { font-size: 16px; font-weight: bold; color: #064e3b; text-transform: uppercase; letter-spacing: 0.5px; }
        .exam-title { font-size: 11px; margin-top: 3px; color: #2d3748; font-weight: bold; }
        .meta { font-size: 8.5px; color: #666; margin-top: 2px; }
        .hdr-r { display: table-cell; width: 70px; text-align: right; font-size: 8px; vertical-align: middle; color: #666; }

        table.r { width: 100%; border-collapse: collapse; }
        table.r th { background: #064e3b; color: white; padding: 4px 3px; font-size: 8px; text-transform: uppercase; letter-spacing: 0.3px; }
        table.r td { padding: 3px; border: 1px solid #ddd; font-size: 8px; text-align: center; }
        table.r td.left { text-align: left; padding-left: 5px; }
        table.r tr:nth-child(even) td { background: #f8fafc; }
        .fail { color: #dc2626; font-weight: bold; }
        .pass-badge { background: #d1fae5; color: #064e3b; padding: 1px 4px; border-radius: 2px; font-size: 7px; font-weight: bold; }
        .fail-badge { background: #fee2e2; color: #7f1d1d; padding: 1px 4px; border-radius: 2px; font-size: 7px; font-weight: bold; }

        .summary { margin-top: 10px; }
        .summary-grid { display: table; width: 100%; }
        .summary-item { display: table-cell; text-align: center; padding: 6px; border: 1px solid #ddd; background: #f0fdf4; }
        .summary-value { font-size: 14px; font-weight: bold; color: #064e3b; }
        .summary-label { font-size: 7.5px; color: #666; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Signature row at the bottom */
        .sig { display: table; width: 100%; margin-top: 14px; padding-top: 8px; border-top: 1px solid #cbd5e1; }
        .sig-cell { display: table-cell; width: 33.33%; text-align: center; padding: 0 16px; vertical-align: bottom; position: relative; }
        .sig-img { height: 14mm; }
        .sig-img img { max-height: 14mm; max-width: 50mm; }
        .sig-img .ph { font-size: 7pt; color: #cbd5e1; font-style: italic; line-height: 14mm; display: inline-block; }
        .sig-line { border-top: 1px solid #334155; padding-top: 2px; font-size: 8.5pt; font-weight: bold; color: #1a1a1a; }
        .sig-name { font-size: 8pt; font-weight: 600; color: #064e3b; margin-top: 1px; }
        .sig-role { font-size: 7pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 1px; }
        .stamp-overlay { position: absolute; left: 50%; bottom: 4px; transform: translateX(-50%); opacity: 0.5; width: 22mm; height: 22mm; z-index: 0; }
        .stamp-overlay img { width: 100%; height: 100%; object-fit: contain; }

        .footer { margin-top: 8px; text-align: center; font-size: 7px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <div class="hdr-logo">
            @if($logoPath)
                <img src="{{ $logoPath }}" alt="">
            @else
                <span class="logo-ph">{{ strtoupper(substr($school->name ?? 'S', 0, 1)) }}</span>
            @endif
        </div>
        <div class="hdr-c">
            <div class="school-name">{{ $school->name }}</div>
            <div class="exam-title">{{ $exam->name }} — RESULT SHEET</div>
            <div class="meta">
                Class {{ $schoolClass->name }} · Session {{ $academicSession->name }} ·
                Generated {{ now()->format('d M Y') }}
            </div>
        </div>
        <div class="hdr-r">
            <div>{{ $results->count() }} Students</div>
            <div>{{ count($subjects) }} Subjects</div>
        </div>
    </div>

    <table class="r">
        <thead>
            <tr>
                {{-- POS column removed; replaced with Admission No per user request --}}
                <th style="width:7%">Adm. No</th>
                <th style="width:5%">Roll</th>
                <th style="width:14%" class="left">Student Name</th>
                <th style="width:11%" class="left">Father Name</th>
                @foreach($subjects as $subject)
                    <th>{{ $subject['code'] }}<br><span style="font-weight:normal;font-size:7px">({{ $subject['total'] }})</span></th>
                @endforeach
                {{-- Primary-only: Overall Assessment column (10 marks). --}}
                @if($isPrimary ?? false)
                    <th style="background:#ecfdf5">Assess<br><span style="font-weight:normal;font-size:7px">(10)</span></th>
                @endif
                <th>Total</th>
                <th>%</th>
                <th>Grade</th>
                <th>Pos</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
            @foreach($results as $result)
            <tr>
                <td style="font-family:'DejaVu Sans Mono',monospace;font-size:7.5px">{{ $result->student->admission_no }}</td>
                <td>{{ $result->student->roll_no }}</td>
                <td class="left" style="font-weight:500">{{ $result->student->name }}</td>
                <td class="left">{{ $result->student->father_name }}</td>
                @foreach($subjects as $subject)
                    @php $sr = $result->subject_results[$subject['id']] ?? null; @endphp
                    <td class="{{ ($sr['failed'] ?? false) ? 'fail' : '' }}">
                        {{ ($sr['is_absent'] ?? false) ? 'AB' : ($sr['obtained'] ?? '-') }}
                    </td>
                @endforeach
                {{-- Primary-only: render the student's Assessment cell.
                     Empty dash when not yet entered, red when below pass. --}}
                @if($isPrimary ?? false)
                    @php $asmt = $result->assessment_payload ?? null; @endphp
                    <td class="{{ ($asmt && !$asmt['passed']) ? 'fail' : '' }}" style="background:#ecfdf5">
                        @if($asmt)
                            {{ $asmt['obtained'] }}
                        @else
                            <span style="color:#94a3b8">—</span>
                        @endif
                    </td>
                @endif
                <td style="font-weight:bold">{{ $result->obtained_marks }}/{{ $result->total_marks }}</td>
                <td style="font-weight:bold">{{ $result->percentage }}%</td>
                <td>{{ $result->grade }}</td>
                <td style="font-weight:bold">{{ $result->position ?? '—' }}</td>
                <td>
                    <span class="{{ $result->is_passed ? 'pass-badge' : 'fail-badge' }}">
                        {{ $result->is_passed ? 'PASS' : 'RETRY' }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-value">{{ $summary['total'] }}</div>
                <div class="summary-label">Total Students</div>
            </div>
            <div class="summary-item">
                <div class="summary-value" style="color:#059669">{{ $summary['passed'] }}</div>
                <div class="summary-label">Passed</div>
            </div>
            <div class="summary-item">
                <div class="summary-value" style="color:#dc2626">{{ $summary['failed'] }}</div>
                <div class="summary-label">Retry</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">{{ $summary['passPercentage'] }}%</div>
                <div class="summary-label">Pass Rate</div>
            </div>
            <div class="summary-item">
                <div class="summary-value">{{ $summary['averagePercentage'] }}%</div>
                <div class="summary-label">Class Average</div>
            </div>
        </div>
    </div>

    {{-- Signature row — Class Teacher · Principal (with stamp) · Exam Controller --}}
    <div class="sig">
        <div class="sig-cell">
            <div class="sig-img"><span class="ph">— signature —</span></div>
            <div class="sig-line">Class Teacher</div>
            <div class="sig-name">{{ $classTeacherName ?? '&nbsp;' }}</div>
        </div>
        <div class="sig-cell">
            @if($stampPath)
                <div class="stamp-overlay"><img src="{{ $stampPath }}" alt=""></div>
            @endif
            <div class="sig-img" style="position:relative;z-index:1;">
                @if($principalSigPath)
                    <img src="{{ $principalSigPath }}" alt="">
                @else
                    <span class="ph">— signature —</span>
                @endif
            </div>
            <div class="sig-line">Principal</div>
            <div class="sig-role">School Head</div>
        </div>
        <div class="sig-cell">
            <div class="sig-img">
                @if($controllerSigPath)
                    <img src="{{ $controllerSigPath }}" alt="">
                @else
                    <span class="ph">— signature —</span>
                @endif
            </div>
            <div class="sig-line">{{ $exam->examController ? 'Exam Controller' : 'Examination Officer' }}</div>
            <div class="sig-name">{{ $controllerName ?? '&nbsp;' }}</div>
        </div>
    </div>

    <div class="footer">
        Generated {{ now()->format('d M Y, h:i A') }} · {{ $school->name }}
    </div>
</body>
</html>
