@php
    $content = $plan['content'] ?? [];
    $homework = $plan['homework'] ?? [];
    if (is_string($homework)) {
        $homework = array_values(array_filter(array_map('trim', preg_split('/\r?\n|;|·/u', $homework) ?: [])));
    }
    $dateStr = ($lessonDate ?? $generatedAt)->format('d-m-Y');
    $isUrdu = $isUrdu ?? false;
    $bodyFont = $isUrdu ? "xbriyaz, 'DejaVu Sans', Arial, sans-serif" : "'DejaVu Sans', Arial, sans-serif";

    // Section + sub-headings in Urdu mode. mPDF auto-shapes these.
    $lbl = $isUrdu ? [
        'slo'             => 'طلباء کے سیکھنے کے نتائج',
        'slo_sub'         => '(موضوع اور مضامین پر توجہ)',
        'content'         => 'مواد کا علم',
        'introduction'    => 'تعارف',
        'definition'      => 'تعریف',
        'characteristics' => 'خصوصیات',
        'examples'        => 'مثالیں',
        'class_activity'  => 'کلاسی سرگرمی',
        'teaching_method' => 'طریقۂ تدریس',
        'teaching_aids'   => 'تدریسی معاونات',
        'homework'        => 'گھر کا کام',
        'teacher_sig'     => 'استاد کے دستخط',
        'principal_sig'   => 'ہیڈ ٹیچر / پرنسپل',
        'teacher_note'    => 'استاد کا نوٹ:',
        'class'           => 'کلاس:', 'section' => 'سیکشن:',
        'subject'         => 'مضمون:', 'date' => 'تاریخ:',
        'teacher_name'    => 'استاد کا نام:', 'no_of_students' => 'طلباء کی تعداد:',
        'topic'           => 'موضوع:',
        'dept'            => 'محکمۂ تعلیم گلگت بلتستان',
        'title'           => 'سمارٹ سبق منصوبہ',
    ] : [
        'slo'             => 'Student Learning Outcomes',
        'slo_sub'         => '(Focus on subject and topics)',
        'content'         => 'Content Knowledge',
        'introduction'    => 'Introduction',
        'definition'      => 'Definition',
        'characteristics' => 'Characteristics',
        'examples'        => 'Examples',
        'class_activity'  => 'Class Activity',
        'teaching_method' => 'Teaching Method',
        'teaching_aids'   => 'Teaching Aids',
        'homework'        => 'Homework',
        'teacher_sig'     => "Teacher's Signature",
        'principal_sig'   => 'Head Teacher / Principal',
        'teacher_note'    => "Teacher's note:",
        'class'           => 'Class:', 'section' => 'Section:',
        'subject'         => 'Subject:', 'date' => 'Date:',
        'teacher_name'    => 'Teacher Name:', 'no_of_students' => 'No of Students:',
        'topic'           => 'Topic:',
        'dept'            => 'Schools Education Department Gilgit-Baltistan',
        'title'           => 'SMART LESSON PLAN',
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ $isUrdu ? 'ur' : 'en' }}" dir="{{ $isUrdu ? 'rtl' : 'ltr' }}">
<head>
<meta charset="UTF-8">
<title>Smart Lesson Plan — {{ $plan['topic'] ?? '' }}</title>
<style>
    /* No @page rule here — when combined with mPDF SetDirectionality('rtl')
       it blows the document up to thousands of pages. Page size + margins
       are set on the mPDF constructor in LessonPlanController. Also no
       `* { box-sizing }` reset — same bloat trigger. */
    body { font-family: {{ $bodyFont }}; font-size: 11pt; color: #000; margin: 0; @if($isUrdu) line-height: 1.8; @endif }
    h1, h2, h3, p, ul, ol, table, div { margin: 0; padding: 0; }
    .urdu-row { @if($isUrdu) direction: rtl; text-align: right; @endif }

    .dept { text-align: center; font-size: 12pt; font-weight: bold; letter-spacing: 0.3px; text-decoration: underline; }
    .sub-dept { text-align: center; font-size: 9.5pt; color: #333; margin-top: 1px; }
    .title-wrap { text-align: center; margin-top: 6px; margin-bottom: 8px; }
    .title { display: inline-block; font-size: 13.5pt; font-weight: bold; text-decoration: underline; letter-spacing: 0.6px; }

    table.meta { width: 100%; border-collapse: collapse; margin-top: 4px; margin-bottom: 10px; }
    table.meta td { border: 1px solid #000; padding: 5px 8px; font-size: 10.5pt; vertical-align: middle; }
    table.meta td.label { font-weight: bold; width: 22%; background: #f3f3f3; }
    table.meta td.val   { width: 28%; min-height: 16px; }
    table.meta td.topic { width: 78%; }

    .sec { margin-top: 10px; }
    .sec-h { font-weight: bold; font-size: 11.5pt; margin-bottom: 4px; }

    /* Content blocks */
    .body-box { border: 1px solid #000; padding: 8px 10px; font-size: 10.5pt; line-height: 1.55; }
    ol.outcomes { list-style: none; padding-left: 0; line-height: 2.2; }
    ol.outcomes li { padding-left: 4px; }
    ol.outcomes .rn { display: inline-block; width: 22px; font-weight: bold; }

    .sub-h { font-weight: bold; font-size: 10.5pt; margin-top: 6px; margin-bottom: 2px; }
    .sub-h:first-child { margin-top: 0; }
    .lead-p { margin-bottom: 4px; }
    ul.bul { list-style: disc; padding-left: 18px; margin: 2px 0 6px 0; }
    ul.bul li { padding: 1px 0; }
    ol.num { padding-left: 22px; margin: 2px 0 6px 0; }
    ol.num li { padding: 1px 0; }
    /* Real <table> for Teaching Method / Aids — mPDF struggles with
       display:table-cell divs in RTL mode and aborts with "Undefined
       array key 0" deep inside Mpdf.php:8601. Plain <table> works. */
    table.ta { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.ta td { width: 50%; vertical-align: top; padding: 0 8px 0 0; border: 0; }
    .note { font-size: 9.5pt; color: #555; font-style: italic; margin-top: 6px; padding-top: 4px; border-top: 1px dashed #ccc; }

    .foot { margin-top: 14px; width: 100%; }
    .foot td { width: 50%; padding-top: 26px; font-size: 10pt; }
    .sig { border-top: 1px solid #000; display: inline-block; min-width: 60%; padding-top: 3px; font-weight: bold; }
    .tag { margin-top: 8px; font-size: 8pt; color: #888; text-align: right; border-top: 1px solid #ddd; padding-top: 4px; }
</style>
</head>
<body>

    {{-- Include the standard logo header when a School model is passed.
         In the Urdu variant the mPDF engine still renders the raster logo
         and Latin-alphabet school name correctly alongside the RTL body. --}}
    @if(is_object($school ?? null))
        @include('reports.partials.logo-header', [
            'school' => $school,
            'title' => $lbl['title'],
            'subtitle' => $lbl['dept'],
            'logoSize' => 55,
        ])
    @else
        <div class="dept">{{ $lbl['dept'] }}</div>
        <div class="sub-dept">{{ $school->name ?? '' }}</div>
        <div class="title-wrap"><span class="title">{{ $lbl['title'] }}</span></div>
    @endif

    {{-- Meta table — Class/Section, Subject/Date, Teacher/No of Students, Topic --}}
    <table class="meta">
        <tr>
            <td class="label">{{ $lbl['class'] }}</td>
            <td class="val">{{ $plan['class'] ?? '' }}</td>
            <td class="label">{{ $lbl['section'] }}</td>
            <td class="val">{{ $section ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">{{ $lbl['subject'] }}</td>
            <td class="val">{{ $plan['subject'] ?? '' }}</td>
            <td class="label">{{ $lbl['date'] }}</td>
            <td class="val">{{ $dateStr }}</td>
        </tr>
        <tr>
            <td class="label">{{ $lbl['teacher_name'] }}</td>
            <td class="val">{{ $teacher->name ?? '' }}</td>
            <td class="label">{{ $lbl['no_of_students'] }}</td>
            <td class="val">{{ $studentsCount ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">{{ $lbl['topic'] }}</td>
            <td class="topic" colspan="3"><strong>{{ $plan['topic'] ?? '' }}</strong></td>
        </tr>
    </table>

    {{-- 1. Student Learning Outcomes (i, ii, iii, iv) --}}
    <div class="sec">
        <div class="sec-h">1. {{ $lbl['slo'] }} &nbsp;<span style="font-weight:normal;font-size:9.5pt;">{{ $lbl['slo_sub'] }}</span></div>
        <div class="body-box">
            @php
                $rn = ['i', 'ii', 'iii', 'iv', 'v', 'vi'];
                $outcomes = array_slice($plan['objectives'] ?? [], 0, 6);
            @endphp
            <ol class="outcomes">
                @foreach ($outcomes as $i => $o)
                    <li><span class="rn">{{ $rn[$i] ?? ($i+1) }}.</span> {{ $o }}</li>
                @endforeach
                @for ($i = count($outcomes); $i < 4; $i++)
                    <li><span class="rn">{{ $rn[$i] }}.</span>&nbsp;</li>
                @endfor
            </ol>
        </div>
    </div>

    {{-- 2. Content Knowledge --}}
    <div class="sec">
        <div class="sec-h">2. {{ $lbl['content'] }}</div>
        <div class="body-box">
            @if (!empty($content['introduction']))
                <div class="sub-h">{{ $lbl['introduction'] }}</div>
                <div class="lead-p">{{ $content['introduction'] }}</div>
            @endif

            @if (!empty($content['definition']))
                <div class="sub-h">{{ $lbl['definition'] }}</div>
                <div class="lead-p">{{ $content['definition'] }}</div>
            @endif

            @if (!empty($content['characteristics']))
                <div class="sub-h">{{ $lbl['characteristics'] }}</div>
                <ul class="bul">
                    @foreach ($content['characteristics'] as $c)
                        <li>{{ $c }}</li>
                    @endforeach
                </ul>
            @endif

            @if (!empty($content['examples']))
                <div class="sub-h">{{ $lbl['examples'] }}</div>
                <ol class="num">
                    @foreach ($content['examples'] as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ol>
            @endif

            @if (!empty($content['class_activity']))
                <div class="sub-h">{{ $lbl['class_activity'] }}</div>
                <div class="lead-p">{{ $content['class_activity'] }}</div>
            @endif

            <table class="ta">
                <tr>
                    <td>
                        @if (!empty($content['teaching_methods']))
                            <div class="sub-h">{{ $lbl['teaching_method'] }}</div>
                            <ul class="bul">
                                @foreach ($content['teaching_methods'] as $m)
                                    <li>{{ $m }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                    <td>
                        @if (!empty($content['teaching_aids']))
                            <div class="sub-h">{{ $lbl['teaching_aids'] }}</div>
                            <ul class="bul">
                                @foreach ($content['teaching_aids'] as $a)
                                    <li>{{ $a }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                </tr>
            </table>

            @if (!empty($content['teacher_notes']))
                <div class="note"><strong>{{ $lbl['teacher_note'] }}</strong> {{ $content['teacher_notes'] }}</div>
            @endif
        </div>
    </div>

    {{-- 3. Homework --}}
    <div class="sec">
        <div class="sec-h">3. {{ $lbl['homework'] }}</div>
        <div class="body-box">
            @if (!empty($homework))
                <ol class="num">
                    @foreach ((array) $homework as $h)
                        <li>{{ $h }}</li>
                    @endforeach
                </ol>
            @else
                <div style="min-height:18mm">&nbsp;</div>
            @endif
        </div>
    </div>

    {{-- Signatures --}}
    <table class="foot">
        <tr>
            <td><span class="sig">{{ $lbl['teacher_sig'] }}</span></td>
            <td style="text-align:right"><span class="sig">{{ $lbl['principal_sig'] }}</span></td>
        </tr>
    </table>

    <div class="tag">
        Generated {{ $generatedAt->format('d M Y, h:i A') }}
        · {{ ($plan['generated_by'] ?? '') === 'ai' ? 'AI-assisted'
            : (($plan['generated_by'] ?? '') === 'reference' ? 'Sourced (Wikipedia)' : 'Template') }}
        · {{ $school->name ?? '' }}
    </div>
</body>
</html>
