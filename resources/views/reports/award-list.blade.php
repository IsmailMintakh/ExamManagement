<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Award List - {{ $exam->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #1a1a1a; }
        .page { padding: 12mm; }
        .header { text-align: center; border-bottom: 3px double #1a365d; padding-bottom: 10px; margin-bottom: 15px; }
        .school-name { font-size: 18px; font-weight: bold; color: #1a365d; text-transform: uppercase; }
        .title { font-size: 14px; font-weight: bold; color: #2d3748; margin-top: 6px; background: #fef3c7; display: inline-block; padding: 3px 15px; border-radius: 3px; }
        .meta { font-size: 9px; color: #666; margin-top: 5px; }
        .class-section { background: #ebf4ff; padding: 6px 12px; margin: 10px 0; border-radius: 4px; font-weight: bold; font-size: 11px; color: #1a365d; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th { background: #1a365d; color: white; padding: 5px 8px; font-size: 9px; text-transform: uppercase; }
        td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) { background: #f7fafc; }
        .position { font-weight: bold; font-size: 12px; color: #1a365d; text-align: center; }
        .gold { color: #d69e2e; }
        .silver { color: #718096; }
        .bronze { color: #c05621; }
        .student-name { font-weight: bold; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; }
        .badge-success { background: #c6f6d5; color: #22543d; }
        .footer { text-align: center; margin-top: 20px; font-size: 8px; color: #999; border-top: 1px solid #eee; padding-top: 8px; }

        /* Standard summary + signature block (used on every report) */
        .summary { margin: 6px 0 4px; font-size: 9.5px; font-weight: bold; color: #1a365d; }
        .summary span { display: inline-block; margin-right: 22px; }
        .summary .blank { display: inline-block; min-width: 60px; border-bottom: 1px solid #1a1a1a; }
        .sign-block { display: table; width: 100%; margin: 14px 0 26px; }
        .sign-cell { display: table-cell; width: 50%; vertical-align: bottom; font-size: 9px; }
        .sign-cell.right { text-align: right; }
        .sign-line { border-top: 1px solid #1a1a1a; padding-top: 3px; font-weight: bold;
                     display: inline-block; min-width: 60%; margin-top: 60px; }
        .sig-img { height: 36px; max-width: 160px; display: block; margin-bottom: -8px; }
        .sig-name { font-size: 9px; color: #444; font-weight: normal; }
    </style>
</head>
<body>
    <div class="page">
        @include('reports.partials.logo-header', [
            'school' => $school,
            'title' => 'MERIT / AWARD LIST',
            'subtitle' => $exam->name . ' | Session: ' . $academicSession->name . ' | Date: ' . now()->format('d/m/Y'),
            'logoSize' => 60,
        ])

        @foreach($classResults as $className => $sections)
            @foreach($sections as $sectionName => $results)
                @php
                    // Detect primary section once per group — every Result
                    // in a section shares the same class, so checking the
                    // first row's flag is enough. Drives the optional
                    // Assessment column below.
                    $isPrimary = (bool) optional($results->first())->is_primary_section;
                @endphp
                <div class="class-section">
                    {{ $className }} - {{ $sectionName }}
                    <span style="float:right;">Total Marks: {{ optional($results->first())->total_marks }}</span>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:8%">Position</th>
                            <th style="width:14%">Reg No</th>
                            <th style="width:{{ $isPrimary ? '32%' : '38%' }}">Student Name</th>
                            <th style="width:{{ $isPrimary ? '14%' : '18%' }}">Obtained Marks</th>
                            @if($isPrimary)
                                <th style="width:10%;background:#0f5132">Assess<br><span style="font-weight:normal;font-size:7px;color:#d1e7dd">(10)</span></th>
                            @endif
                            <th style="width:22%">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($results as $result)
                        <tr>
                            <td class="position {{ $result->position == 1 ? 'gold' : ($result->position == 2 ? 'silver' : ($result->position == 3 ? 'bronze' : '')) }}">
                                {{ $result->position }}
                                @if($result->position <= 3)
                                    {{ $result->position == 1 ? '🥇' : ($result->position == 2 ? '🥈' : '🥉') }}
                                @endif
                            </td>
                            <td>{{ $result->student->admission_no }}</td>
                            <td class="student-name">{{ $result->student->name }}</td>
                            <td style="text-align:center;font-weight:bold">{{ $result->obtained_marks }}</td>
                            @if($isPrimary)
                                @php $asmt = $result->assessment_payload ?? null; @endphp
                                <td style="text-align:center;background:#ecfdf5">
                                    @if($asmt)
                                        <strong>{{ $asmt['obtained'] }}</strong>
                                    @else
                                        <span style="color:#94a3b8">—</span>
                                    @endif
                                </td>
                            @endif
                            <td></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Standard summary + signature (general for all reports) --}}
                <div class="summary">
                    <span>Total Students: <span class="blank">&nbsp;</span></span>
                    <span>Present: <span class="blank">&nbsp;</span></span>
                    <span>Absent: <span class="blank">&nbsp;</span></span>
                </div>
                @php
                    // Pull the class teacher off the section (eager-loaded
                    // in ReportController::awardList). When they've uploaded
                    // a signature image we drop it above the printed name
                    // so the page is auto-signed.
                    $sec = optional($results->first())->section;
                    $ct = $sec?->classTeacher;
                    $sigPath = $ct?->signaturePath();
                @endphp
                <div class="sign-block">
                    <div class="sign-cell">
                        @if ($sigPath)
                            <img src="{{ $sigPath }}" alt="" class="sig-img" />
                        @endif
                        <div class="sign-line">
                            Class Teacher / Subject Teacher
                            @if ($ct?->name)
                                <div class="sig-name">{{ $ct->name }}</div>
                            @endif
                        </div>
                    </div>
                    <div class="sign-cell right">
                        <div class="sign-line">Submitted Date</div>
                    </div>
                </div>
            @endforeach
        @endforeach

        <div class="footer">
            Generated on {{ now()->format('d/m/Y h:i A') }} | {{ $school->name }} - Exam Management System
        </div>
    </div>
</body>
</html>
