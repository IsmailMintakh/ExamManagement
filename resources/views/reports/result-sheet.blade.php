@php
    $logoPath = !empty($school->logo) && $school?->getLogoAbsolutePath()
        ? $school?->getLogoAbsolutePath() : null;
    $principalSigPath = !empty($school->principal_signature) && file_exists(public_path('storage/' . $school->principal_signature))
        ? public_path('storage/' . $school->principal_signature) : null;
    $stampPath = !empty($school->school_stamp) && file_exists(public_path('storage/' . $school->school_stamp))
        ? public_path('storage/' . $school->school_stamp) : null;
    $controllerSigPath = !empty($school->exam_officer_signature) && file_exists(public_path('storage/' . $school->exam_officer_signature))
        ? public_path('storage/' . $school->exam_officer_signature) : null;
    $controllerName = $exam->examController?->name ?? ($school->exam_officer_name ?? null);
    // Class teacher = first section's teacher (a class result sheet may span
    // multiple sections; show the first one's teacher as a representative).
    // Section-scoped sheets prefer that section's class teacher; otherwise
    // fall back to the first section's teacher (representative sample).
    $classTeacherName = isset($section) && $section
        ? ($section->classTeacher?->name)
        : $schoolClass->sections->pluck('classTeacher.name')->filter()->first();

    // Ordinal formatter — 1 → 1st, 2 → 2nd, 3 → 3rd, 4-20 → Nth, then repeats.
    $ordinal = function ($n) {
        if ($n === null || $n === 0) return '—';
        $n = (int) $n;
        $mod100 = $n % 100;
        if ($mod100 >= 11 && $mod100 <= 13) return $n . 'th';
        return $n . match ($n % 10) { 1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th' };
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Result Sheet - {{ $exam->name }}</title>
    <style>
        /* Wider page margin so the logo has breathing room from the page
           border — was 8mm, felt cramped in the first render. */
        @page { size: A4 landscape; margin: 14mm 12mm 12mm 12mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #1a1a1a; }
        .header { display: table; width: 100%; padding: 4px 0 10px; border-bottom: 2px solid #064e3b; margin-bottom: 10px; }
        .hdr-logo { display: table-cell; width: 70px; vertical-align: middle; padding-left: 6px; }
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
                Class {{ $schoolClass->name }}@if(isset($section) && $section) · Section {{ $section->name }} @endif ·
                Session {{ $academicSession->name }} ·
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
                <th>Obtained</th>
                <th>Total</th>
                <th>%</th>
                <th>Grade</th>
                <th>Rank</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
            @foreach($results as $result)
            <tr>
                <td style="font-family:'DejaVu Sans Mono',monospace;font-size:7.5px">{{ $result->student->admission_no }}</td>
                <td>{{ $result->student->roll_no }}</td>
                {{-- Uppercase student + father names so mixed-casing data
                     ("Awais Haider" vs "SULTAN SALAHUDIN" vs "M abid")
                     renders consistently across the whole sheet. --}}
                <td class="left" style="font-weight:bold;text-transform:uppercase;letter-spacing:0.2px">
                    {{ $result->student->name }}
                </td>
                <td class="left" style="text-transform:uppercase">{{ $result->student->father_name }}</td>
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
                <td style="font-weight:bold">{{ number_format((float) $result->obtained_marks, 2) }}</td>
                <td>{{ number_format((float) $result->total_marks, 2) }}</td>
                <td style="font-weight:bold">{{ $result->percentage }}%</td>
                <td style="font-weight:bold">{{ $result->grade ?: '—' }}</td>
                <td style="font-weight:bold">{{ $ordinal($result->position) }}</td>
                <td>
                    <span class="{{ $result->is_passed ? 'pass-badge' : 'fail-badge' }}">
                        {{ $result->is_passed ? 'PASS' : 'RETRY' }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ═══════════ FOOTER — matches the school's reference sheet ═══════════
         One unified table so column widths line up exactly like the
         Excel screenshot the school shared. Layout:

           row 1: "Result Summary" | "Qualitative Result in Grades" | "Criteria"
                                                                     (banner row)
           row 2: 7 vertical-header cols       5 grade-band cols     N subject-teacher cols
                  (Total Students … Target %)  (80-84% … 60% >)      (one per subject)
           row 3: values                       values + letter grade  Teacher's Name row
           row 4:                                                     Pass %  row
           row 5:                                                     Target % row

         Column widths are chosen so left+middle sit at fixed pixel widths
         and the subject block fills the remainder. Vertical column labels
         via CSS transform (DomPDF v2+ supports rotate()).                    --}}
    @php
        $sumCols  = ['Total Students', 'Appeared', 'Absent', 'Successful', 'Unsuccessful', 'Pass %', 'Target %'];
        $sumVals  = [
            $summary['total'], $summary['appeared'], $summary['absent'],
            $summary['successful'], $summary['unsuccessful'],
            $summary['passPercentage'] . '%',
            $summary['targetPercentage'] ? $summary['targetPercentage'].'%' : '',
        ];
        $subjectN = ($subjectTeacherRows ?? collect())->count();
    @endphp

    <table style="width:100%; border-collapse:collapse; margin-top:8px; font-size:7.5px; border:1px solid #333">
        {{-- Banner row --}}
        <tr>
            <td colspan="{{ count($sumCols) }}" style="border:1px solid #333; padding:3px; text-align:center; font-weight:bold; background:#f0fdf4">
                Result Summary
            </td>
            <td colspan="{{ count($gradeBands) }}" style="border:1px solid #333; padding:3px; text-align:center; font-weight:bold; background:#f0fdf4">
                Qualitative Result in Grades
            </td>
            @if($subjectN > 0)
                <td colspan="{{ $subjectN + 1 }}" style="border:1px solid #333; padding:3px; text-align:center; font-weight:bold; background:#f0fdf4">
                    Criteria
                </td>
            @endif
        </tr>

        {{-- Column headers: 7 summary cols + 5 grade band cols + N subject cols
             (subject cols are pushed to a row-labeled block below via the
              Teacher's Name / Pass % / Target % rows). Grade band gets an
              extra letter-grade sub-row per the reference. --}}
        <tr style="background:#e7f5ec">
            @foreach($sumCols as $c)
                <th style="border:1px solid #333; padding:2px; height:52px; vertical-align:middle; width:26px">
                    <div style="transform:rotate(-90deg); white-space:nowrap; font-size:7px; font-weight:bold">
                        {{ $c }}
                    </div>
                </th>
            @endforeach
            @foreach($gradeBands as $b)
                <th style="border:1px solid #333; padding:2px; text-align:center; font-size:7px; vertical-align:middle; width:32px">
                    {{ $b['label'] }}<br><span style="font-weight:normal; color:#065f46">{{ $b['letter'] }}</span>
                </th>
            @endforeach
            @if($subjectN > 0)
                <th style="border:1px solid #333; padding:2px 4px; text-align:left; font-size:7px; width:60px">
                    &nbsp;
                </th>
                @foreach($subjectTeacherRows as $row)
                    <th style="border:1px solid #333; padding:2px; text-align:center; font-size:7px; font-weight:bold; text-transform:uppercase">
                        {{ $row['code'] }}
                    </th>
                @endforeach
            @endif
        </tr>

        {{-- Value row (aligns with all headers above) --}}
        <tr>
            @foreach($sumVals as $v)
                <td style="border:1px solid #333; padding:3px; text-align:center; font-weight:bold">{{ $v }}</td>
            @endforeach
            @foreach($gradeBands as $b)
                <td style="border:1px solid #333; padding:3px; text-align:center; font-weight:bold">{{ $b['count'] }}</td>
            @endforeach
            @if($subjectN > 0)
                <td style="border:1px solid #333; padding:3px 4px; text-align:left; font-weight:bold; background:#f8fafc">
                    Teacher's Name
                </td>
                @foreach($subjectTeacherRows as $row)
                    <td style="border:1px solid #333; padding:3px 2px; text-align:center; text-transform:uppercase; font-size:7px">
                        {{ $row['teacher_name'] }}
                    </td>
                @endforeach
            @endif
        </tr>

        {{-- Pass % row (only spans Criteria columns — leaves left/middle blank
             on that row, matching the reference). --}}
        @if($subjectN > 0)
        <tr>
            <td colspan="{{ count($sumCols) + count($gradeBands) }}" style="border:1px solid #333"></td>
            <td style="border:1px solid #333; padding:3px 4px; text-align:left; font-weight:bold; background:#f8fafc">
                Pass %
            </td>
            @foreach($subjectTeacherRows as $row)
                <td style="border:1px solid #333; padding:3px 2px; text-align:center; font-weight:bold;
                    color:{{ $row['pass_percentage'] >= 80 ? '#059669' : ($row['pass_percentage'] >= 50 ? '#d97706' : '#dc2626') }}">
                    {{ $row['pass_percentage'] }}
                </td>
            @endforeach
        </tr>
        <tr>
            <td colspan="{{ count($sumCols) + count($gradeBands) }}" style="border:1px solid #333"></td>
            <td style="border:1px solid #333; padding:3px 4px; text-align:left; font-weight:bold; background:#f8fafc">
                Target %
            </td>
            @foreach($subjectTeacherRows as $row)
                <td style="border:1px solid #333; padding:3px 2px; text-align:center; color:#94a3b8">&nbsp;</td>
            @endforeach
        </tr>
        @endif
    </table>

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
