<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificates - Bulk</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DejaVu Serif', 'Times New Roman', serif; color: #1f2937; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    @foreach($sheets as $i => $sheet)
        @include('reports.certificate', [
            'template' => $sheet['template'],
            'data' => $sheet['data'],
            'school' => $sheet['school'],
        ])
        @if($i < count($sheets) - 1)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
