@php
    $logoPath = !empty($school->logo) && file_exists(public_path('storage/' . $school->logo))
        ? public_path('storage/' . $school->logo) : null;

    // 1 = full A4 portrait, 2 = side-by-side A5 on A4 landscape (cut once).
    $slipsPerPage = $slipsPerPage ?? 1;
    if (!in_array($slipsPerPage, [1, 2], true)) $slipsPerPage = 1;

    // Per-mode metrics — kept in PHP so the CSS stays simple and the values
    // are easy to tune in one place.
    $modeMetrics = [
        1 => ['body' => '10.5pt', 'sch' => '15pt', 'title' => '12pt', 'logo' => '46px',
              'pad' => '0',          'lineH' => '1.35', 'qSpace' => '6px',  'lineGap' => '4px'],
        2 => ['body' => '8.5pt',  'sch' => '12pt', 'title' => '10pt', 'logo' => '36px',
              'pad' => '4mm 6mm',    'lineH' => '1.30', 'qSpace' => '5px',  'lineGap' => '3px'],
    ];
    $m = $modeMetrics[$slipsPerPage];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $paper->title }}</title>
<style>
    /* Wide white borders on every edge — never let content touch the paper edge.
       Landscape on 2-up so each slip becomes A5 portrait shaped. */
    @page {
        size: A4 {{ $slipsPerPage === 2 ? 'landscape' : 'portrait' }};
        margin: {{ $slipsPerPage === 1 ? '25mm 28mm 25mm 28mm' : '18mm 22mm' }};
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body {
        font-family: 'DejaVu Serif', 'Times New Roman', serif;
        font-size: {{ $m['body'] }};
        color: #000;
        line-height: {{ $m['lineH'] }};
    }
    /* Inner padding inside the printable area so content never hugs the
       page-margin edge. ~4mm ≈ 12px on screen — comfortable breathing room
       on every side of every printed paper. */
    body { padding: {{ $slipsPerPage === 1 ? '4mm' : '2mm' }}; }

    /* ── 2-up grid: two slips side-by-side as a 2-column table ── */
    @if($slipsPerPage === 2)
        .slip-grid { width: 100%; border-collapse: separate; border-spacing: 8mm 0; }
        /* Slip padding gives content visible breathing room from the cut line. */
        .slip { padding: 5mm; vertical-align: top; width: 50%;
                border: 1px dashed #777; height: 188mm; }
    @endif

    /* ── Header — minimal, B&W ── */
    .hdr { display: table; width: 100%; padding-bottom: 4px;
           border-bottom: 1.5px solid #000; margin-bottom: 6px; }
    .hdr-l { display: table-cell; width: {{ $m['logo'] }}; vertical-align: middle; }
    .hdr-l img { width: {{ $m['logo'] }}; height: {{ $m['logo'] }}; object-fit: contain; }
    .logo-ph { width: {{ $m['logo'] }}; height: {{ $m['logo'] }}; background: #000;
               border-radius: 50%; display: inline-block; line-height: {{ $m['logo'] }};
               text-align: center; color: #fff;
               font-size: calc({{ $m['logo'] }} * 0.4); font-weight: bold;
               font-family: 'DejaVu Sans', sans-serif; }
    .hdr-c { display: table-cell; text-align: center; vertical-align: middle; padding: 0 6px; }

    .sch-name { font-size: {{ $m['sch'] }}; font-weight: bold;
                text-transform: uppercase; letter-spacing: 0.4px; line-height: 1.1; color: #000; }
    .sch-addr { font-size: {{ $slipsPerPage === 1 ? '8.5pt' : '7pt' }};
                color: #333; margin-top: 1px; }

    /* ── Title ── */
    .title-block { text-align: center; margin: 3px 0 5px 0; }
    .paper-title { font-size: {{ $m['title'] }}; font-weight: bold;
                   text-transform: uppercase; letter-spacing: 0.8px; }
    .exam-sub { font-size: {{ $slipsPerPage === 1 ? '9.5pt' : '8pt' }};
                margin-top: 1px; color: #333; }

    /* ── Meta row: Class | Subject | Time | Marks ── */
    .meta { display: table; width: 100%; border: 1px solid #000;
            margin-bottom: 5px;
            font-size: {{ $slipsPerPage === 1 ? '9.5pt' : '7.5pt' }}; }
    .meta-cell { display: table-cell; padding: 2px 6px; vertical-align: middle;
                 border-right: 1px solid #000; }
    .meta-cell:last-child { border-right: none; }
    .meta-lbl { font-weight: bold; }

    /* ── Candidate info ── */
    .candidate { display: table; width: 100%; margin-bottom: 5px;
                 font-size: {{ $slipsPerPage === 1 ? '9.5pt' : '7.5pt' }}; }
    .candidate-cell { display: table-cell; padding: 2px 0; vertical-align: middle; }
    .candidate-cell .lbl { font-weight: bold; }
    .candidate-cell .blank { display: inline-block; border-bottom: 1px solid #555;
                             min-width: {{ $slipsPerPage === 1 ? '50mm' : '25mm' }};
                             margin-left: 3px; }
    .candidate-cell .blank-sm { min-width: {{ $slipsPerPage === 1 ? '20mm' : '12mm' }}; }

    /* ── Instructions ── */
    .instr { border-left: 2px solid #000; background: #f5f5f5;
             padding: 3px 8px; margin-bottom: 6px;
             font-size: {{ $slipsPerPage === 1 ? '9.5pt' : '7.5pt' }}; }
    .instr h4 { font-size: {{ $slipsPerPage === 1 ? '9.5pt' : '7.5pt' }};
                margin-bottom: 1px; }

    /* ── Section heading ── */
    .section-heading { font-size: {{ $slipsPerPage === 1 ? '11pt' : '8.5pt' }};
                       font-weight: bold; text-align: center;
                       margin: 6px 0 3px 0; padding: 2px 0;
                       border-top: 1px solid #000; border-bottom: 1px solid #000;
                       text-transform: uppercase; letter-spacing: 1px; }
    .section-meta { text-align: center;
                    font-size: {{ $slipsPerPage === 1 ? '8.5pt' : '7pt' }};
                    font-style: italic; margin-bottom: 3px; color: #444; }

    /* ── Question ── */
    .q { margin-bottom: {{ $m['qSpace'] }}; page-break-inside: avoid; }
    .q-num { font-weight: bold; }
    .q-text { display: inline; }
    .q-marks { float: right; font-weight: bold;
               font-size: {{ $slipsPerPage === 1 ? '9.5pt' : '7.5pt' }}; }

    /* ── MCQ ── */
    .mcq-opts { margin-top: 2px; margin-left: 14px; display: table;
                width: calc(100% - 14px); }
    .mcq-row { display: table-row; }
    .mcq-cell { display: table-cell; width: 50%; padding: 1px 4px 1px 0;
                font-size: {{ $slipsPerPage === 1 ? '10pt' : '7.5pt' }};
                vertical-align: top; }
    .opt-letter { font-weight: bold; }

    /* ── T/F ── */
    .tf-opts { margin-left: 14px; margin-top: 2px;
               font-size: {{ $slipsPerPage === 1 ? '10pt' : '7.5pt' }}; }
    .tf-opts span { margin-right: 24px; }

    /* ── Answer space lines ── */
    .answer-lines { margin-top: 3px; margin-left: 14px; }
    .answer-lines .line { border-bottom: 1px solid #999;
                          height: {{ $slipsPerPage === 1 ? '14px' : '11px' }};
                          margin-bottom: {{ $m['lineGap'] }}; }

    .end-note { text-align: center; font-weight: bold; margin-top: 8px;
                letter-spacing: 2px;
                font-size: {{ $slipsPerPage === 1 ? '9.5pt' : '7.5pt' }}; }
</style>
</head>
<body>

@if ($slipsPerPage === 2)
    <table class="slip-grid"><tr>
        @for ($s = 0; $s < 2; $s++)
            <td class="slip">
                @include('reports.partials.question-paper-slip', [
                    'paper' => $paper, 'questions' => $questions,
                    'school' => $school, 'logoPath' => $logoPath,
                    'slipsPerPage' => $slipsPerPage,
                ])
            </td>
        @endfor
    </tr></table>
@else
    @include('reports.partials.question-paper-slip', [
        'paper' => $paper, 'questions' => $questions,
        'school' => $school, 'logoPath' => $logoPath,
        'slipsPerPage' => $slipsPerPage,
    ])
@endif

</body>
</html>
