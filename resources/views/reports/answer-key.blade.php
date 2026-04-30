<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Answer Key — {{ $paper->title }}</title>
<style>
    @page { size: A4 portrait; margin: 12mm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 10pt;
        color: #111;
        line-height: 1.35;
    }

    .hdr { border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 8px; text-align: center; }
    .sch-name { font-size: 15pt; font-weight: bold; text-transform: uppercase; }
    .sch-addr { font-size: 8.5pt; color: #333; margin-top: 2px; }

    .key-title {
        background: #111; color: #fff;
        text-align: center; padding: 6px 10px;
        font-size: 13pt; font-weight: bold;
        letter-spacing: 4px; text-transform: uppercase;
        margin-bottom: 8px;
    }

    .meta-row { display: table; width: 100%; margin-bottom: 10px; font-size: 9pt; border: 1px solid #666; }
    .meta-cell { display: table-cell; padding: 4px 8px; border-right: 1px solid #666; }
    .meta-cell:last-child { border-right: none; }
    .meta-lbl { font-weight: bold; }

    .section-heading {
        font-weight: bold; font-size: 11pt;
        border-top: 1px solid #111; border-bottom: 1px solid #111;
        padding: 3px 5px;
        text-transform: uppercase;
        margin: 10px 0 6px 0;
    }

    .grid-3 { display: table; width: 100%; table-layout: fixed; }
    .grid-3-row { display: table-row; }
    .grid-3-cell {
        display: table-cell;
        width: 33.33%;
        padding: 3px 8px 3px 0;
        vertical-align: top;
        font-size: 9.5pt;
    }
    .grid-2 { display: table; width: 100%; table-layout: fixed; }
    .grid-2-row { display: table-row; }
    .grid-2-cell {
        display: table-cell;
        width: 50%;
        padding: 3px 8px 3px 0;
        vertical-align: top;
        font-size: 9.5pt;
    }

    .qnum { font-weight: bold; display: inline-block; min-width: 32px; }
    .ans { color: #000; }
    .ans-letter { font-weight: bold; font-size: 11pt; color: #0a5; }
    .explain { font-size: 8.5pt; color: #666; font-style: italic; margin-left: 32px; }

    .ftr { margin-top: 12px; padding-top: 4px; border-top: 1px solid #ccc; font-size: 7.5pt; color: #888; text-align: center; }
</style>
</head>
<body>

<div class="hdr">
    <div class="sch-name">{{ $school->name ?? 'School Name' }}</div>
    @if(!empty($school->address))
        <div class="sch-addr">{{ $school->address }}</div>
    @endif
</div>

<div class="key-title">Answer Key</div>

<div class="meta-row">
    <div class="meta-cell"><span class="meta-lbl">Paper:</span> {{ $paper->title }}</div>
    <div class="meta-cell"><span class="meta-lbl">Subject:</span> {{ $paper->subject->name ?? '—' }}</div>
    <div class="meta-cell"><span class="meta-lbl">Class:</span> {{ $paper->schoolClass->name ?? '—' }}</div>
    <div class="meta-cell"><span class="meta-lbl">Set:</span> {{ $paper->set_code }}</div>
    <div class="meta-cell"><span class="meta-lbl">Total:</span> {{ rtrim(rtrim(number_format((float) $paper->total_marks, 2), '0'), '.') }}</div>
</div>

@php
    $romanMap = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
    $globalQNum = 0;
@endphp

@foreach($paper->sections ?? [] as $sIdx => $section)
    @php
        $secRoman = $romanMap[$sIdx] ?? ($sIdx + 1);
        $type = $section['type'] ?? '';
        // 3-column for short answers (mcq, true_false, fill_blank); 2-col for short_answer; 1-col stacking for long
        $colLayout = in_array($type, ['mcq', 'true_false', 'fill_blank'], true) ? 3 : (in_array($type, ['short_answer'], true) ? 2 : 1);
    @endphp

    <div class="section-heading">Section {{ $secRoman }} — {{ $section['label'] ?? ucfirst(str_replace('_', ' ', $type)) }}</div>

    @if($colLayout === 3)
        <div class="grid-3">
        @php $col = 0; @endphp
        @foreach($section['questions'] ?? [] as $qEntry)
            @php
                $q = $questions[$qEntry['question_id']] ?? null;
                if (!$q) continue;
                $globalQNum++;

                // Build the displayed answer
                if ($q->type === 'mcq' && is_array($q->options)) {
                    $letters = ['A','B','C','D','E','F'];
                    $order = $qEntry['option_order'] ?? array_keys($q->options);
                    $correctLetter = null;
                    foreach ($order as $pos => $origIdx) {
                        if (!empty($q->options[$origIdx]['is_correct'])) {
                            $correctLetter = $letters[$pos] ?? ($pos + 1);
                            break;
                        }
                    }
                    $ansHtml = '<span class="ans-letter">' . ($correctLetter ?? '?') . '</span>';
                    $correctText = $q->options[array_search(true, array_column($q->options, 'is_correct'), true) ?: 0]['text'] ?? '';
                    if ($correctText) $ansHtml .= ' — ' . e($correctText);
                } elseif ($q->type === 'true_false') {
                    $ansHtml = '<span class="ans-letter">' . e($q->correct_answer ?: '—') . '</span>';
                } else {
                    $ansHtml = e(\Illuminate\Support\Str::limit((string) ($q->correct_answer ?? ''), 80));
                }

                if ($col === 0) echo '<div class="grid-3-row">';
            @endphp
            <div class="grid-3-cell">
                <span class="qnum">Q{{ $globalQNum }}.</span>
                <span class="ans">{!! $ansHtml !!}</span>
            </div>
            @php
                $col++;
                if ($col === 3) { echo '</div>'; $col = 0; }
            @endphp
        @endforeach
        @php
            if ($col !== 0) {
                for ($i = $col; $i < 3; $i++) echo '<div class="grid-3-cell"></div>';
                echo '</div>';
            }
        @endphp
        </div>

    @elseif($colLayout === 2)
        <div class="grid-2">
        @php $col = 0; @endphp
        @foreach($section['questions'] ?? [] as $qEntry)
            @php
                $q = $questions[$qEntry['question_id']] ?? null;
                if (!$q) continue;
                $globalQNum++;
                $ansText = e(\Illuminate\Support\Str::limit((string) ($q->correct_answer ?? ''), 120));
                if ($col === 0) echo '<div class="grid-2-row">';
            @endphp
            <div class="grid-2-cell">
                <span class="qnum">Q{{ $globalQNum }}.</span>
                <span class="ans">{!! $ansText !!}</span>
            </div>
            @php
                $col++;
                if ($col === 2) { echo '</div>'; $col = 0; }
            @endphp
        @endforeach
        @php
            if ($col !== 0) {
                for ($i = $col; $i < 2; $i++) echo '<div class="grid-2-cell"></div>';
                echo '</div>';
            }
        @endphp
        </div>

    @else
        {{-- Long answer: one-per-row with limited text --}}
        @foreach($section['questions'] ?? [] as $qEntry)
            @php
                $q = $questions[$qEntry['question_id']] ?? null;
                if (!$q) continue;
                $globalQNum++;
            @endphp
            <div style="margin-bottom: 5px;">
                <span class="qnum">Q{{ $globalQNum }}.</span>
                <span class="ans">{{ \Illuminate\Support\Str::limit((string) ($q->correct_answer ?? '(see detailed key)'), 240) }}</span>
                @if(!empty($q->explanation))
                    <div class="explain">{{ \Illuminate\Support\Str::limit($q->explanation, 180) }}</div>
                @endif
            </div>
        @endforeach
    @endif
@endforeach

<div class="ftr">
    Answer key for paper #{{ $paper->id }} (Set {{ $paper->set_code }}) &middot; Generated {{ now()->format('d M Y, h:i A') }}
</div>

</body>
</html>
