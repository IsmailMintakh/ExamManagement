@php
    // Map our generated plan to the three official sections.
    $outcomes   = $plan['objectives'] ?? [];
    $content    = $plan['content'] ?? [];
    $homework   = $plan['homework'] ?? '';

    // "Content Knowledge" = topic summary + key points + (example/real-life if any) + teacher notes.
    $contentBlocks = [];
    if (!empty($content['summary']))                       $contentBlocks[] = $content['summary'];
    if (!empty($content['key_points']) && is_array($content['key_points'])) {
        foreach ($content['key_points'] as $k) $contentBlocks[] = '• '.$k;
    }
    if (!empty($content['example']))                       $contentBlocks[] = 'Example: '.$content['example'];
    if (!empty($content['real_life']))                     $contentBlocks[] = 'Real-life link: '.$content['real_life'];
    if (!empty($content['misconception']))                 $contentBlocks[] = 'Common misconception: '.$content['misconception'];
    if (!empty($content['teacher_notes']))                 $contentBlocks[] = 'Note: '.$content['teacher_notes'];

    $dateStr = ($lessonDate ?? $generatedAt)->format('d-m-Y');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Smart Lesson Plan — {{ $plan['topic'] ?? '' }}</title>
<style>
    @page { size: A4 portrait; margin: 12mm 14mm 14mm 14mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11pt; color: #000; }

    /* Header */
    .dept { text-align: center; font-size: 12pt; font-weight: bold; letter-spacing: 0.3px; }
    .sub-dept { text-align: center; font-size: 9.5pt; color: #333; margin-top: 1px; }
    .title-wrap { text-align: center; margin-top: 6px; margin-bottom: 8px; }
    .title { display: inline-block; font-size: 13.5pt; font-weight: bold;
             text-decoration: underline; letter-spacing: 0.6px; }

    /* Meta table */
    table.meta { width: 100%; border-collapse: collapse; margin-top: 4px; margin-bottom: 8px; }
    table.meta td { border: 1px solid #000; padding: 5px 8px; font-size: 10.5pt; vertical-align: middle; }
    table.meta td.label { font-weight: bold; width: 22%; background: #f3f3f3; }
    table.meta td.val   { width: 28%; min-height: 16px; }
    table.meta td.topic { width: 78%; }

    /* Section heading */
    .sec { margin-top: 12px; }
    .sec-h { font-weight: bold; font-size: 11.5pt; margin-bottom: 4px; }

    /* Body content boxes (ruled like the printed format) */
    .body-box { border: 1px solid #000; padding: 8px 10px; min-height: 36mm;
                line-height: 1.9; background-image: linear-gradient(to bottom, transparent 95%, #cfcfcf 95%);
                background-size: 100% 22px; background-position: 0 18px; }
    .outcomes-box { min-height: 38mm; }
    .content-box  { min-height: 70mm; }
    .homework-box { min-height: 32mm; }

    .outcomes { list-style: none; padding-left: 0; }
    .outcomes li { padding: 2px 0; }
    .outcomes .rn { display: inline-block; width: 18px; font-weight: bold; }

    .content-line { padding: 1px 0; line-height: 1.7; }

    /* Footer signature */
    .foot { margin-top: 14px; width: 100%; }
    .foot td { width: 50%; padding-top: 26px; font-size: 10pt; }
    .sig { border-top: 1px solid #000; display: inline-block; min-width: 60%; padding-top: 3px; font-weight: bold; }
    .tag { margin-top: 8px; font-size: 8pt; color: #888; text-align: right;
           border-top: 1px solid #ddd; padding-top: 4px; }
</style>
</head>
<body>

    <div class="dept">Schools Education Department Gilgit-Baltistan</div>
    <div class="sub-dept">{{ $school->name ?? '' }}</div>
    <div class="title-wrap"><span class="title">SMART LESSON PLAN</span></div>

    {{-- Meta table — Class/Section, Subject/Date, Teacher/No of Students, Topic --}}
    <table class="meta">
        <tr>
            <td class="label">Class:</td>
            <td class="val">{{ $plan['class'] ?? '' }}</td>
            <td class="label">Section:</td>
            <td class="val">{{ $section ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Subject:</td>
            <td class="val">{{ $plan['subject'] ?? '' }}</td>
            <td class="label">Date:</td>
            <td class="val">{{ $dateStr }}</td>
        </tr>
        <tr>
            <td class="label">Teacher Name:</td>
            <td class="val">{{ $teacher->name ?? '' }}</td>
            <td class="label">No of Students:</td>
            <td class="val">{{ $studentsCount ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Topic:</td>
            <td class="topic" colspan="3"><strong>{{ $plan['topic'] ?? '' }}</strong></td>
        </tr>
    </table>

    {{-- 1. Student Learning Outcomes (i, ii, iii, iv) --}}
    <div class="sec">
        <div class="sec-h">1. Student Learning Outcomes &nbsp;<span style="font-weight:normal;font-size:9.5pt;">(Focus on subject and topics)</span></div>
        <div class="body-box outcomes-box">
            @php
                $rn = ['i', 'ii', 'iii', 'iv', 'v', 'vi'];
                $outcomes = array_slice($outcomes, 0, 6);
            @endphp
            <ol class="outcomes">
                @foreach ($outcomes as $i => $o)
                    <li><span class="rn">{{ $rn[$i] ?? ($i+1) }}.</span> {{ $o }}</li>
                @endforeach
                @for ($i = count($outcomes); $i < 4; $i++)
                    <li><span class="rn">{{ $rn[$i] }}.</span></li>
                @endfor
            </ol>
        </div>
    </div>

    {{-- 2. Content Knowledge --}}
    <div class="sec">
        <div class="sec-h">2. Content Knowledge</div>
        <div class="body-box content-box">
            @foreach ($contentBlocks as $line)
                <div class="content-line">{{ $line }}</div>
            @endforeach
        </div>
    </div>

    {{-- 3. Homework --}}
    <div class="sec">
        <div class="sec-h">3. Homework</div>
        <div class="body-box homework-box">
            @if (!empty($homework))
                <div class="content-line">{{ $homework }}</div>
            @endif
        </div>
    </div>

    {{-- Signatures --}}
    <table class="foot">
        <tr>
            <td><span class="sig">Teacher's Signature</span></td>
            <td style="text-align:right"><span class="sig">Head Teacher / Principal</span></td>
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
