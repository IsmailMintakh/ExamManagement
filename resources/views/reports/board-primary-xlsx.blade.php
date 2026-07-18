{{--
    Excel-flavoured version of the board-primary term-wise result. Same
    data as the PDF template but uses simple HTML tables PhpSpreadsheet can
    parse via maatwebsite's FromView. Styling is applied in the
    BoardPrimaryResultExport::registerEvents() AfterSheet hook.
--}}
<table>
    <tr>
        <th colspan="20" style="text-align:center">SCHOOL EDUCATION DEPARTMENT DISTRICT ____________</th>
    </tr>
    <tr>
        <th colspan="20" style="text-align:center">Term-wise Result for the Academic Year {{ $academicSession?->name ?? now()->year }}</th>
    </tr>
    <tr>
        <td colspan="10">INSTITUTION: {{ $school->name }}</td>
        <td colspan="10">Class: {{ $schoolClass->name }}{{ $section->name ? ' - '.$section->name : '' }}</td>
    </tr>
    <tr><td>&nbsp;</td></tr>
</table>

<table>
    <thead>
        <tr>
            <th rowspan="3">S</th>
            <th rowspan="3">Ad</th>
            <th rowspan="3">Student Name</th>
            <th rowspan="3">F. Name</th>
            <th rowspan="3">DOB</th>
            @foreach($subjects as $s)
                <th colspan="4">{{ $s['name'] }}</th>
            @endforeach
            <th rowspan="3">Co Curr</th>
            <th rowspan="3">Total O.M</th>
            <th rowspan="3">%</th>
            <th rowspan="3">Grade</th>
            <th rowspan="3">Remarks</th>
        </tr>
        <tr>
            @foreach($subjects as $s)
                <th>T-I</th><th>T-II</th><th>T-III</th><th>Total</th>
            @endforeach
        </tr>
        <tr>
            @foreach($subjects as $s)
                <th>{{ $termColTotal }}</th>
                <th>{{ $termColTotal }}</th>
                <th>{{ $termColTotal }}</th>
                <th>{{ $termColTotal * $termCount }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $idx => $row)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $row['admission_no'] }}</td>
                <td>{{ $row['name'] }}</td>
                <td>{{ $row['father_name'] }}</td>
                <td>{{ $row['dob'] }}</td>
                @foreach($row['subjects'] as $sr)
                    <td>{{ $sr['t1'] }}</td>
                    <td>{{ $sr['t2'] }}</td>
                    <td>{{ $sr['t3'] }}</td>
                    <td>{{ $sr['total'] }}</td>
                @endforeach
                <td>{{ $row['co_curr'] }}</td>
                <td>{{ $row['grand_obtained'] }}</td>
                <td>{{ number_format($row['percentage'], 2) }}</td>
                <td>{{ $row['grade'] }}</td>
                <td>{{ $row['remarks'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table><tr><td>&nbsp;</td></tr></table>

{{-- Summary + grade distribution + subject teacher row --}}
<table>
    <thead>
        <tr>
            <th>Metric</th>
            <th>Value</th>
            <th>&nbsp;</th>
            <th>Grade</th>
            <th>#</th>
            <th>Range</th>
        </tr>
    </thead>
    <tbody>
        @php $rowsPair = [
            ['Average',           number_format($summary['average_percentage'], 2)],
            ['Total Percentage',  number_format($summary['average_percentage'], 2)],
            ['Appeared',          $summary['appeared']],
            ['Passed',            $summary['passed']],
            ['Failed',            $summary['failed']],
            ['Pass %',            number_format($summary['pass_percentage'], 2).'%'],
            ['Total',             $summary['total_students']],
        ]; @endphp
        @foreach($gradeBands as $i => $b)
            <tr>
                <td>{{ $rowsPair[$i][0] ?? '' }}</td>
                <td>{{ $rowsPair[$i][1] ?? '' }}</td>
                <td>&nbsp;</td>
                <td>{{ $b['grade'] }}</td>
                <td>{{ $gradeCounts[$b['grade']] ?? 0 }}</td>
                <td>{{ $b['label'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table><tr><td>&nbsp;</td></tr></table>

<table>
    <tr>
        <th>Name of Sub. Teacher</th>
        @foreach($subjects as $s)
            <th>{{ $s['name'] }}</th>
        @endforeach
    </tr>
    <tr>
        <td>Teacher</td>
        @foreach($subjects as $s)
            <td>{{ $s['teacher'] ?: '—' }}</td>
        @endforeach
    </tr>
</table>

<table><tr><td>&nbsp;</td></tr></table>

<table>
    <tr>
        <th>Prepared By</th>
        <th>Checked By (Class Tr){{ $classTeacher ? ' — '.$classTeacher : '' }}</th>
        <th>Signature of HM/DDO</th>
        <th>Counter Signed by</th>
    </tr>
    <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
</table>
