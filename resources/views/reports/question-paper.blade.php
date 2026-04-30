<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $paper->title }}</title>
<style>
    @page { size: A4 portrait; margin: 14mm 14mm 18mm 14mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body {
        font-family: 'DejaVu Serif', 'Times New Roman', serif;
        font-size: 11pt;
        color: #111;
        line-height: 1.45;
    }

    .hdr { border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 10px; display: table; width: 100%; }
    .hdr-logo { display: table-cell; width: 64px; vertical-align: middle; }
    .hdr-logo img { width: 58px; height: 58px; object-fit: contain; }
    .logo-ph { width: 58px; height: 58px; background: #111; border-radius: 50%; display: inline-block; line-height: 58px; text-align: center; color: #fff; font-size: 22pt; font-weight: bold; font-family: 'DejaVu Sans', sans-serif; }
    .hdr-center { display: table-cell; text-align: center; vertical-align: middle; padding: 0 10px; }
    .hdr-right { display: table-cell; width: 90px; text-align: right; font-size: 9pt; vertical-align: middle; }
    .sch-name { font-size: 18pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.1; }
    .sch-addr { font-size: 9pt; color: #333; margin-top: 2px; }

    .title-block { text-align: center; margin: 6px 0 10px 0; }
    .paper-title { font-size: 14pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
    .exam-sub { font-size: 11pt; margin-top: 2px; color: #333; }

    .meta-row {
        display: table; width: 100%;
        border: 1px solid #111;
        margin-bottom: 10px;
        font-size: 10pt;
    }
    .meta-cell { display: table-cell; padding: 4px 8px; vertical-align: middle; border-right: 1px solid #111; }
    .meta-cell:last-child { border-right: none; }
    .meta-lbl { font-weight: bold; }

    .instr {
        border: 1px dashed #666;
        padding: 7px 10px;
        margin-bottom: 14px;
        font-size: 10pt;
        background: #fafafa;
    }
    .instr h4 { font-size: 10.5pt; margin-bottom: 3px; text-decoration: underline; }

    .section-heading {
        font-size: 12pt;
        font-weight: bold;
        text-align: center;
        margin: 14px 0 6px 0;
        padding: 4px 0;
        border-top: 1px solid #111;
        border-bottom: 1px solid #111;
        text-transform: uppercase;
        letter-spacing: 1.5px;
    }
    .section-meta { text-align: center; font-size: 9.5pt; font-style: italic; margin-bottom: 8px; color: #333; }

    .q { margin-bottom: 10px; page-break-inside: avoid; }
    .q-num { font-weight: bold; }
    .q-text { display: inline; }
    .q-marks { float: right; font-weight: bold; font-size: 10pt; }

    .mcq-opts { margin-top: 4px; margin-left: 22px; display: table; width: calc(100% - 22px); }
    .mcq-row { display: table-row; }
    .mcq-cell {
        display: table-cell;
        width: 50%;
        padding: 1px 6px 1px 0;
        font-size: 10.5pt;
        vertical-align: top;
    }
    .opt-letter { font-weight: bold; }

    .tf-opts { margin-left: 22px; margin-top: 4px; font-size: 10.5pt; }
    .tf-opts span { margin-right: 30px; }

    .answer-line { display: block; border-bottom: 1px solid #555; min-height: 18px; margin-top: 4px; }
    .answer-lines { margin-top: 4px; margin-left: 22px; }
    .answer-lines .line { border-bottom: 1px solid #666; height: 16px; margin-bottom: 6px; }

    .fill-blank { font-style: italic; }

    .ftr {
        position: fixed;
        bottom: 4mm; left: 14mm; right: 14mm;
        font-size: 8pt; color: #666;
        border-top: 1px solid #ccc;
        padding-top: 3px;
        display: table; width: 100%;
    }
    .ftr-l { display: table-cell; }
    .ftr-r { display: table-cell; text-align: right; }

    .end-note { text-align: center; font-weight: bold; margin-top: 20px; letter-spacing: 3px; }
</style>
</head>
<body>

<div class="hdr">
    <div class="hdr-logo">
        @if(!empty($school->logo) && file_exists(public_path('storage/' . $school->logo)))
            <img src="{{ public_path('storage/' . $school->logo) }}" alt="">
        @else
            <span class="logo-ph">{{ strtoupper(substr($school->name ?? 'S', 0, 1)) }}</span>
        @endif
    </div>
    <div class="hdr-center">
        <div class="sch-name">{{ $school->name ?? 'School Name' }}</div>
        <div class="sch-addr">
            {{ $school->address ?? '' }}@if(!empty($school->phone)) &middot; Ph: {{ $school->phone }}@endif
        </div>
    </div>
    <div class="hdr-right">
        <div><strong>Set</strong></div>
        <div style="font-size: 18pt; font-weight: bold; border: 1.5px solid #111; display: inline-block; width: 34px; height: 34px; line-height: 32px;">{{ $paper->set_code }}</div>
    </div>
</div>

<div class="title-block">
    <div class="paper-title">{{ $paper->title }}</div>
    @if($paper->exam_name)
        <div class="exam-sub">{{ $paper->exam_name }}</div>
    @endif
</div>

<div class="meta-row">
    <div class="meta-cell"><span class="meta-lbl">Class:</span> {{ $paper->schoolClass->name ?? '—' }}</div>
    <div class="meta-cell"><span class="meta-lbl">Subject:</span> {{ $paper->subject->name ?? '—' }}</div>
    <div class="meta-cell"><span class="meta-lbl">Duration:</span> {{ $paper->duration_minutes }} min</div>
    <div class="meta-cell"><span class="meta-lbl">Total Marks:</span> {{ rtrim(rtrim(number_format((float) $paper->total_marks, 2), '0'), '.') }}</div>
</div>

@if(!empty($paper->instructions))
<div class="instr">
    <h4>Instructions</h4>
    <div>{!! nl2br(e($paper->instructions)) !!}</div>
</div>
@endif

@php
    $romanMap = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
    $globalQNum = 0;
@endphp

@foreach($paper->sections ?? [] as $sIdx => $section)
    @php
        $secRoman = $romanMap[$sIdx] ?? ($sIdx + 1);
        $secCount = $section['count'] ?? count($section['questions'] ?? []);
        $marksEach = $section['marks_each'] ?? 1;
        $secTotal = $secCount * $marksEach;
        $marksEachLabel = rtrim(rtrim(number_format((float) $marksEach, 2), '0'), '.');
        $secTotalLabel = rtrim(rtrim(number_format((float) $secTotal, 2), '0'), '.');
    @endphp

    <div class="section-heading">Section {{ $secRoman }} — {{ $section['label'] ?? ucfirst(str_replace('_', ' ', $section['type'])) }}</div>
    <div class="section-meta">
        ({{ $secCount }} &times; {{ $marksEachLabel }} = {{ $secTotalLabel }} marks)
        @if(!empty($section['difficulty']) && $section['difficulty'] !== 'mixed')
            &middot; Difficulty: {{ ucfirst($section['difficulty']) }}
        @endif
    </div>

    @foreach($section['questions'] ?? [] as $qEntry)
        @php
            $qid = $qEntry['question_id'] ?? null;
            $q = $qid ? ($questions[$qid] ?? null) : null;
            if (!$q) continue;
            $globalQNum++;
            $qMarks = $qEntry['marks'] ?? $marksEach;
            $qMarksLabel = rtrim(rtrim(number_format((float) $qMarks, 2), '0'), '.');
            $optOrder = $qEntry['option_order'] ?? null;
        @endphp

        <div class="q">
            <span class="q-marks">[{{ $qMarksLabel }}]</span>
            <span class="q-num">Q{{ $globalQNum }}.</span>
            <span class="q-text">{{ $q->question_text }}</span>

            @if($q->type === 'mcq' && is_array($q->options))
                @php
                    $letters = ['A', 'B', 'C', 'D', 'E', 'F'];
                    $opts = $q->options;
                    $order = $optOrder && is_array($optOrder) ? $optOrder : array_keys($opts);
                @endphp
                <div class="mcq-opts">
                    @php $cellCount = 0; @endphp
                    @foreach($order as $origIdx)
                        @php
                            $opt = $opts[$origIdx] ?? null;
                            if (!$opt) continue;
                            $displayIdx = $loop->index;
                            $letter = $letters[$displayIdx] ?? ($displayIdx + 1);
                            $cellCount++;
                        @endphp
                        @if($cellCount % 2 === 1)
                        <div class="mcq-row">
                        @endif
                        <div class="mcq-cell"><span class="opt-letter">{{ $letter }})</span> {{ $opt['text'] ?? '' }}</div>
                        @if($cellCount % 2 === 0)
                        </div>
                        @endif
                    @endforeach
                    @if($cellCount % 2 === 1)
                    </div>
                    @endif
                </div>
            @elseif($q->type === 'true_false')
                <div class="tf-opts">
                    <span>(a) True</span>
                    <span>(b) False</span>
                </div>
            @elseif($q->type === 'fill_blank')
                {{-- The question_text is expected to already contain ____ blanks --}}
            @elseif($q->type === 'short_answer')
                <div class="answer-lines">
                    <div class="line"></div>
                    <div class="line"></div>
                </div>
            @elseif($q->type === 'long_answer')
                <div class="answer-lines">
                    @for($i = 0; $i < 6; $i++)
                        <div class="line"></div>
                    @endfor
                </div>
            @endif
        </div>
    @endforeach
@endforeach

<div class="end-note">— END OF PAPER —</div>

<div class="ftr">
    <div class="ftr-l">Paper generated by ExamPro on {{ now()->format('d M Y, h:i A') }}</div>
    <div class="ftr-r">Set {{ $paper->set_code }} &middot; Paper #{{ $paper->id }}</div>
</div>

</body>
</html>
