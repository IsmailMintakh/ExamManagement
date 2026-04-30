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
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div class="school-name">{{ $school->name }}</div>
            <div class="title">MERIT / AWARD LIST</div>
            <div class="meta">{{ $exam->name }} | Session: {{ $academicSession->name }} | Date: {{ now()->format('d/m/Y') }}</div>
        </div>

        @foreach($classResults as $className => $sections)
            @foreach($sections as $sectionName => $results)
                <div class="class-section">{{ $className }} - {{ $sectionName }}</div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:8%">Position</th>
                            <th style="width:8%">Roll No</th>
                            <th style="width:25%">Student Name</th>
                            <th style="width:20%">Father Name</th>
                            <th style="width:10%">Obtained</th>
                            <th style="width:10%">Total</th>
                            <th style="width:10%">Percentage</th>
                            <th style="width:9%">Grade</th>
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
                            <td>{{ $result->student->roll_no }}</td>
                            <td class="student-name">{{ $result->student->name }}</td>
                            <td>{{ $result->student->father_name }}</td>
                            <td style="text-align:center;font-weight:bold">{{ $result->obtained_marks }}</td>
                            <td style="text-align:center">{{ $result->total_marks }}</td>
                            <td style="text-align:center;font-weight:bold">{{ $result->percentage }}%</td>
                            <td style="text-align:center"><span class="badge badge-success">{{ $result->grade }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        @endforeach

        <div class="footer">
            Generated on {{ now()->format('d/m/Y h:i A') }} | {{ $school->name }} - Exam Management System
        </div>
    </div>
</body>
</html>
