@php
    $logoPath = $school?->getLogoAbsolutePath();
    // Signature / stamp paths — go through the same multi-path resolver
    // so they render regardless of which storage layout production uses.
    $principalSigPath  = $school?->resolveAssetPath('principal_signature');
    $stampPath         = $school?->resolveAssetPath('school_stamp');
    $controllerSigPath = $school?->resolveAssetPath('exam_officer_signature');
    // Class teacher signature: resolved from the User row loaded via
    // sections.classTeacher. Section-scoped sheet uses THAT section's
    // teacher; class-wide sheet falls back to the first section's teacher.
    $classTeacherModel = isset($section) && $section
        ? $section->classTeacher
        : $schoolClass->sections->pluck('classTeacher')->filter()->first();
    $classTeacherSigPath = $classTeacherModel?->signaturePath();
    $controllerName = $exam->examController?->name ?? ($school->exam_officer_name ?? null);
    // Class teacher = first section's teacher (a class result sheet may span
    // multiple sections; show the first one's teacher as a representative).
    // Section-scoped sheets prefer that section's class teacher; otherwise
    // fall back to the first section's teacher (representative sample).
    $classTeacherName = $classTeacherModel?->name;

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
                {{-- Assessment column: primary section + final-term exam
                     + at least one student has data. All three must be
                     true — controller precomputes this as $showAssessment
                     so 1st/2nd term primary sheets skip the empty column. --}}
                @if($showAssessment ?? false)
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
                {{-- Assessment cell — same gate as the header column. --}}
                @if($showAssessment ?? false)
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

            {{-- ═════════ FOOTER ROWS — appended inside the main table
                 so subject columns align perfectly with the top marks
                 grid (no repeated subject codes, no banner labels).
                 Layout mirrors the school's reference sheet:
                   Teacher's Name / Pass % / Target %
                 The first 4 columns (Adm/Roll/Name/Father) become the
                 label cell; the right-side aggregate columns
                 (Obtained/Total/%/Grade/Rank/Result [+Assessment]) merge
                 into a single compact summary cell that spans all three
                 footer rows via rowspan. ═════════ --}}
            @php
                $rightColCount = ($showAssessment ?? false) ? 7 : 6;
            @endphp
            <tr style="background:#e7f5ec">
                <td colspan="4" style="text-align:right; font-weight:bold; padding:4px 8px; border:1px solid #333">
                    Teacher's Name
                </td>
                @foreach($subjectTeacherRows as $row)
                    <td style="text-align:center; text-transform:uppercase; font-weight:bold; font-size:7.5px; padding:4px 2px; border:1px solid #333">
                        {{ $row['teacher_name'] }}
                    </td>
                @endforeach
                {{-- Right-side aggregate columns get the compact summary
                     block, spanning all three footer rows so the empty
                     space isn't wasted. --}}
                <td rowspan="3" colspan="{{ $rightColCount }}" style="vertical-align:top; padding:6px 8px; border:1px solid #333; background:#f8fafc; font-size:8px; line-height:1.6; text-align:left">
                    <div>Total: <b>{{ $summary['total'] }}</b> · Appeared: <b>{{ $summary['appeared'] }}</b> · Absent: <b>{{ $summary['absent'] }}</b></div>
                    <div>Successful: <b style="color:#059669">{{ $summary['successful'] }}</b> · Unsuccessful: <b style="color:#dc2626">{{ $summary['unsuccessful'] }}</b></div>
                    <div>Pass %: <b>{{ $summary['passPercentage'] }}%</b>@if($summary['targetPercentage']) · Target: {{ $summary['targetPercentage'] }}%@endif</div>
                    <div style="margin-top:3px; padding-top:3px; border-top:1px solid #cbd5e1; font-size:7.5px">
                        @foreach($gradeBands as $b)
                            {{ $b['letter'] }} ({{ $b['label'] }}): <b>{{ $b['count'] }}</b>{{ !$loop->last ? ' &nbsp; ' : '' }}
                        @endforeach
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="4" style="text-align:right; font-weight:bold; padding:4px 8px; border:1px solid #333">
                    Pass %
                </td>
                @foreach($subjectTeacherRows as $row)
                    <td style="text-align:center; font-weight:bold; padding:4px 2px; border:1px solid #333;
                        color:{{ $row['pass_percentage'] >= 80 ? '#059669' : ($row['pass_percentage'] >= 50 ? '#d97706' : '#dc2626') }}">
                        {{ $row['pass_percentage'] }}
                    </td>
                @endforeach
            </tr>
            <tr style="background:#e7f5ec">
                <td colspan="4" style="text-align:right; font-weight:bold; padding:4px 8px; border:1px solid #333">
                    Target %
                </td>
                @foreach($subjectTeacherRows as $row)
                    <td style="padding:4px 2px; border:1px solid #333">&nbsp;</td>
                @endforeach
            </tr>
        </tbody>
    </table>

    {{-- Signature row — Class Teacher · Principal (with stamp) · Exam Controller --}}
    <div class="sig">
        <div class="sig-cell">
            <div class="sig-img">
                @if($classTeacherSigPath)
                    <img src="{{ $classTeacherSigPath }}" alt="">
                @else
                    <span class="ph">— signature —</span>
                @endif
            </div>
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
