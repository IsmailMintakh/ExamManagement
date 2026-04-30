<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Result Sheet - {{ $exam->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #1a1a1a; }
        .page { padding: 10mm; }
        .header { text-align: center; margin-bottom: 10px; }
        .school-name { font-size: 16px; font-weight: bold; color: #1a365d; }
        .exam-title { font-size: 12px; margin-top: 4px; color: #2d3748; }
        .meta { font-size: 9px; color: #666; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #1a365d; color: white; padding: 4px 3px; font-size: 8px; text-transform: uppercase; letter-spacing: 0.3px; }
        td { padding: 3px; border: 1px solid #ddd; font-size: 8px; text-align: center; }
        td.left { text-align: left; padding-left: 5px; }
        tr:nth-child(even) { background: #f8fafc; }
        .fail { color: #e53e3e; font-weight: bold; }
        .pass-badge { background: #c6f6d5; color: #22543d; padding: 1px 4px; border-radius: 2px; font-size: 7px; font-weight: bold; }
        .fail-badge { background: #fed7d7; color: #742a2a; padding: 1px 4px; border-radius: 2px; font-size: 7px; font-weight: bold; }
        .summary { margin-top: 15px; }
        .summary-grid { display: table; width: 100%; }
        .summary-item { display: table-cell; text-align: center; padding: 8px; border: 1px solid #ddd; }
        .summary-value { font-size: 16px; font-weight: bold; color: #1a365d; }
        .summary-label { font-size: 8px; color: #666; margin-top: 2px; }
        .footer { margin-top: 15px; text-align: center; font-size: 8px; color: #999; }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="school-name">{{ $school->name }}</div>
            <div class="exam-title">{{ $exam->name }} - RESULT SHEET</div>
            <div class="meta">
                Class: {{ $schoolClass->name }} | Session: {{ $academicSession->name }} |
                Date: {{ now()->format('d/m/Y') }}
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:3%">Pos</th>
                    <th style="width:4%">Roll</th>
                    <th style="width:15%" class="left">Student Name</th>
                    <th style="width:12%" class="left">Father Name</th>
                    @foreach($subjects as $subject)
                        <th>{{ $subject['code'] }}<br><span style="font-weight:normal;font-size:7px">({{ $subject['total'] }})</span></th>
                    @endforeach
                    <th>Total</th>
                    <th>%</th>
                    <th>Grade</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                @foreach($results as $result)
                <tr>
                    <td>{{ $result->position }}</td>
                    <td>{{ $result->student->roll_no }}</td>
                    <td class="left" style="font-weight:500">{{ $result->student->name }}</td>
                    <td class="left">{{ $result->student->father_name }}</td>
                    @foreach($subjects as $subject)
                        @php $sr = $result->subject_results[$subject['id']] ?? null; @endphp
                        <td class="{{ ($sr['failed'] ?? false) ? 'fail' : '' }}">
                            {{ ($sr['is_absent'] ?? false) ? 'AB' : ($sr['obtained'] ?? '-') }}
                        </td>
                    @endforeach
                    <td style="font-weight:bold">{{ $result->obtained_marks }}/{{ $result->total_marks }}</td>
                    <td style="font-weight:bold">{{ $result->percentage }}%</td>
                    <td>{{ $result->grade }}</td>
                    <td>
                        <span class="{{ $result->is_passed ? 'pass-badge' : 'fail-badge' }}">
                            {{ $result->is_passed ? 'PASS' : 'FAIL' }}
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
                    <div class="summary-value" style="color:#38a169">{{ $summary['passed'] }}</div>
                    <div class="summary-label">Passed</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value" style="color:#e53e3e">{{ $summary['failed'] }}</div>
                    <div class="summary-label">Failed</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">{{ $summary['passPercentage'] }}%</div>
                    <div class="summary-label">Pass Percentage</div>
                </div>
                <div class="summary-item">
                    <div class="summary-value">{{ $summary['averagePercentage'] }}%</div>
                    <div class="summary-label">Class Average</div>
                </div>
            </div>
        </div>

        <div class="footer">
            Generated on {{ now()->format('d/m/Y h:i A') }} | {{ $school->name }} - Exam Management System
        </div>
    </div>
</body>
</html>
