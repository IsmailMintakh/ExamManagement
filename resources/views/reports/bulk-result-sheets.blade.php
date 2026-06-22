@php
    $logoPath = !empty($school->logo) && file_exists(public_path('storage/' . $school->logo))
        ? public_path('storage/' . $school->logo) : null;
    $principalSigPath = !empty($school->principal_signature) && file_exists(public_path('storage/' . $school->principal_signature))
        ? public_path('storage/' . $school->principal_signature) : null;
    $controllerSigPath = !empty($school->exam_officer_signature) && file_exists(public_path('storage/' . $school->exam_officer_signature))
        ? public_path('storage/' . $school->exam_officer_signature) : null;
    $controllerName = $exam->examController?->name ?? ($school->exam_officer_name ?? null);
    $principalName = $school->principal_name ?? ($school->principal?->name ?? null);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Result Sheets — {{ $exam->name }}</title>
<style>
    /* Same look as the per-class result sheet, but each class takes its own
       page so the doc reads like a stapled stack. The first page can run
       short; the page-break-before: always on .class-block ensures classes
       2..N start fresh. */
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

    .class-block { page-break-after: always; }
    .class-block:last-child { page-break-after: auto; }

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

    .sig { display: table; width: 100%; margin-top: 14px; padding-top: 8px; border-top: 1px solid #cbd5e1; }
    .sig-cell { display: table-cell; width: 33.33%; text-align: center; padding: 0 16px; vertical-align: bottom; }
    .sig-img { height: 14mm; }
    .sig-img img { max-height: 14mm; max-width: 50mm; }
    .sig-img .ph { font-size: 7pt; color: #cbd5e1; font-style: italic; line-height: 14mm; display: inline-block; }
    .sig-line { border-top: 1px solid #334155; padding-top: 2px; font-size: 8.5pt; font-weight: bold; color: #1a1a1a; }
    .sig-name { font-size: 8pt; font-weight: 600; color: #064e3b; margin-top: 1px; }
    .sig-role { font-size: 7pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 1px; }

    .footer { margin-top: 8px; text-align: center; font-size: 7px; color: #999; }
    .class-heading { background: #064e3b; color: #fff; padding: 5px 10px; font-size: 11px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 8px; }
</style>
</head>
<body>

@foreach($blocks as $block)
@php
    $cls = $block->schoolClass;
    $classTeacherName = $cls->sections->pluck('classTeacher.name')->filter()->first();
@endphp
<div class="class-block">
    <div class="header">
        <div class="hdr-logo">
            @if($logoPath)
                <img src="{{ $logoPath }}" alt="">
            @else
                <span class="logo-ph">{{ strtoupper(substr($school->name ?? 'S', 0, 1)) }}</span>
            @endif
        </div>
        <div class="hdr-c">
            <div class="school-name">{{ $school->name ?? 'School' }}</div>
            <div class="exam-title">{{ $exam->name }} — RESULT SHEET</div>
            <div class="meta">
                Class {{ $cls->name }} · Session {{ $academicSession->name }} ·
                Generated {{ now()->format('d M Y') }}
            </div>
        </div>
    </div>

    @if($block->results->isEmpty())
        <p style="text-align:center; padding:20px; color:#999;">No results generated for this class yet.</p>
    @else
        <table class="r">
            <thead>
                <tr>
                    <th style="width:30px">Pos</th>
                    <th style="width:40px">Roll</th>
                    <th class="left">Student</th>
                    <th class="left">Father</th>
                    @foreach($block->subjects as $subj)
                        <th>{{ $subj['code'] ?? $subj['name'] }}<br>({{ $subj['total'] }})</th>
                    @endforeach
                    {{-- Primary-only: Overall Assessment column (10 marks).
                         Each block (one per class) carries its own isPrimary
                         flag so a non-primary class doesn't show this. --}}
                    @if($block->isPrimary ?? false)
                        <th style="background:#ecfdf5">Assess<br>(10)</th>
                    @endif
                    <th>Total</th>
                    <th>%</th>
                    <th>Grade</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($block->results as $result)
                    <tr>
                        <td>{{ $result->position }}</td>
                        <td>{{ $result->student?->roll_no }}</td>
                        <td class="left">{{ $result->student?->name }}</td>
                        <td class="left">{{ $result->student?->father_name ?? '—' }}</td>
                        @foreach($block->subjects as $subj)
                            @php $sr = $result->subject_results[$subj['id']] ?? null; @endphp
                            <td>
                                @if($sr && ($sr['is_absent'] ?? false))
                                    <span class="fail-badge">AB</span>
                                @elseif($sr)
                                    <span @class(['fail' => $sr['failed'] ?? false])>{{ $sr['obtained'] }}</span>
                                @else
                                    <span style="color:#ccc">—</span>
                                @endif
                            </td>
                        @endforeach
                        @if($block->isPrimary ?? false)
                            @php $asmt = $result->assessment_payload ?? null; @endphp
                            <td style="background:#ecfdf5">
                                @if($asmt)
                                    <span @class(['fail' => !$asmt['passed']])>{{ $asmt['obtained'] }}</span>
                                @else
                                    <span style="color:#ccc">—</span>
                                @endif
                            </td>
                        @endif
                        <td><strong>{{ $result->obtained_marks }}</strong>/{{ $result->total_marks }}</td>
                        <td><strong>{{ $result->percentage }}%</strong></td>
                        <td>{{ $result->grade ?? '—' }}</td>
                        <td>
                            @if($result->is_passed)
                                <span class="pass-badge">PASS</span>
                            @else
                                <span class="fail-badge">FAIL</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="summary">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-value">{{ $block->summary['total'] }}</div>
                    <div class="summary-label">Total</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value" style="color:#059669">{{ $block->summary['passed'] }}</div>
                    <div class="summary-label">Passed</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value" style="color:#dc2626">{{ $block->summary['failed'] }}</div>
                    <div class="summary-label">Failed</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">{{ $block->summary['passPercentage'] }}%</div>
                    <div class="summary-label">Pass %</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">{{ $block->summary['averagePercentage'] }}%</div>
                    <div class="summary-label">Average</div>
                </div>
            </div>
        </div>

        <div class="sig">
            <div class="sig-cell">
                <div class="sig-img">
                    <span class="ph">— signature —</span>
                </div>
                <div class="sig-line">Class Teacher</div>
                @if($classTeacherName)<div class="sig-name">{{ $classTeacherName }}</div>@endif
            </div>
            <div class="sig-cell">
                <div class="sig-img">
                    @if($controllerSigPath)<img src="{{ $controllerSigPath }}" alt="">
                    @else<span class="ph">— signature —</span>@endif
                </div>
                <div class="sig-line">Exam Controller</div>
                @if($controllerName)<div class="sig-name">{{ $controllerName }}</div>@endif
            </div>
            <div class="sig-cell">
                <div class="sig-img">
                    @if($principalSigPath)<img src="{{ $principalSigPath }}" alt="">
                    @else<span class="ph">— signature —</span>@endif
                </div>
                <div class="sig-line">Principal</div>
                @if($principalName)<div class="sig-name">{{ $principalName }}</div>@endif
            </div>
        </div>

        <div class="footer">
            Generated {{ now()->format('d M Y, h:i A') }} · Computer-generated · {{ $school->name ?? '' }}
        </div>
    @endif
</div>
@endforeach

</body>
</html>
