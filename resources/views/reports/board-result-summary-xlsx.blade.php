{{--
    Excel version of the class board-summary. Rendered inside a single
    <table> so PhpSpreadsheet reads it as one contiguous sheet with
    consistent column widths.

    Columns: Pos | Roll | Student | Father | Board # | <subj1..N> | Total | % | Grade | Division | Result
--}}
<table>
    <tr>
        @php $subjCount = $subjects->count(); $totalCols = 5 + $subjCount + 5; @endphp
        <th colspan="{{ $totalCols }}" style="text-align:center; font-size:12pt; font-weight:bold;">
            {{ strtoupper($exam->school->name) }} — FBISE {{ $exam->level }} · {{ $exam->title }}
        </th>
    </tr>
    <tr>
        <th colspan="{{ $totalCols }}" style="text-align:center; font-size:9.5pt; font-weight:bold;">
            Class {{ $exam->schoolClass?->name }} · {{ $exam->academicSession?->name }}
        </th>
    </tr>

    <tr style="font-weight:bold; text-align:center;">
        <th>Pos</th>
        <th>Roll</th>
        <th>Student Name</th>
        <th>Father Name</th>
        <th>Board #</th>
        @foreach($subjects as $sub)
            <th>{{ $sub->name }}</th>
        @endforeach
        <th>Total</th>
        <th>%</th>
        <th>Grade</th>
        <th>Division</th>
        <th>Result</th>
    </tr>

    @foreach($results as $r)
        <tr>
            <td style="text-align:center;">{{ $r->position ?: '' }}</td>
            <td style="text-align:center;">{{ $r->student->roll_no }}</td>
            <td>{{ $r->student->name }}</td>
            <td>{{ $r->student->father_name }}</td>
            <td style="text-align:center;">{{ $r->board_roll_no }}</td>
            @foreach($subjects as $sub)
                @php $sr = $r->subjects->firstWhere('subject_id', $sub->id); @endphp
                <td style="text-align:center;">{{ $sr ? rtrim(rtrim(number_format((float) $sr->total_marks, 1), '0'), '.') : '' }}</td>
            @endforeach
            <td style="text-align:center; font-weight:bold;">{{ rtrim(rtrim(number_format((float) $r->total_obtained, 1), '0'), '.') }}</td>
            <td style="text-align:center; font-weight:bold;">{{ number_format($r->percentage, 1) }}%</td>
            <td style="text-align:center; font-weight:bold;">{{ $r->grade }}</td>
            <td style="text-align:center;">{{ $r->is_supplementary ? 'Supply' : $r->division }}</td>
            <td style="text-align:center; font-weight:bold;">{{ $r->is_pass ? 'Pass' : ($r->is_supplementary ? 'Supply' : 'Fail') }}</td>
        </tr>
    @endforeach
</table>
