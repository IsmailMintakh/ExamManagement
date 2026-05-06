{{-- One question-paper "slip" — the same body content gets rendered 1, 2 or 3
     times per A4 page depending on $slipsPerPage. Stays purely B&W (printer
     ink-friendly) and skips the SET letter from the visible header.
     Sizes for fonts/spacing are governed by CSS classes from the parent. --}}

<div class="hdr">
    <div class="hdr-l">
        @if($logoPath)
            <img src="{{ $logoPath }}" alt="">
        @else
            <span class="logo-ph">{{ strtoupper(substr($school->name ?? 'S', 0, 1)) }}</span>
        @endif
    </div>
    <div class="hdr-c">
        <div class="sch-name">{{ $school->name ?? 'School' }}</div>
        @if(!empty($school->address) || !empty($school->phone))
            <div class="sch-addr">
                {{ $school->address ?? '' }}@if(!empty($school->phone)) &middot; Ph: {{ $school->phone }}@endif
            </div>
        @endif
    </div>
</div>

<div class="title-block">
    <div class="paper-title">{{ $paper->title }}</div>
    @if($paper->exam_name)
        <div class="exam-sub">{{ $paper->exam_name }}</div>
    @endif
</div>

<div class="meta">
    <div class="meta-cell"><span class="meta-lbl">Class:</span> {{ $paper->schoolClass->name ?? '—' }}</div>
    <div class="meta-cell"><span class="meta-lbl">Subject:</span> {{ $paper->subject->name ?? '—' }}</div>
    <div class="meta-cell"><span class="meta-lbl">Time:</span> {{ $paper->duration_minutes }} min</div>
    <div class="meta-cell"><span class="meta-lbl">Marks:</span> {{ rtrim(rtrim(number_format((float) $paper->total_marks, 2), '0'), '.') }}</div>
</div>

<div class="candidate">
    <div class="candidate-cell" style="width:50%">
        <span class="lbl">Name:</span> <span class="blank"></span>
    </div>
    <div class="candidate-cell" style="width:25%">
        <span class="lbl">Roll:</span> <span class="blank blank-sm"></span>
    </div>
    <div class="candidate-cell" style="width:25%">
        <span class="lbl">Date:</span> <span class="blank blank-sm"></span>
    </div>
</div>

@if(!empty($paper->instructions))
<div class="instr">
    <h4>Instructions</h4>
    <div>{!! nl2br(e($paper->instructions)) !!}</div>
</div>
@endif

@php
    $qNum = 0; // running counter across the paper; reset per-section if requested
    $defaultLinesByType = [
        'mcq' => 0,
        'true_false' => 0,
        'fill_blank' => 0,
        'short_answer' => 2,
        'long_answer' => 6,
    ];
    if ($slipsPerPage === 2) {
        $defaultLinesByType['short_answer'] = 1;
        $defaultLinesByType['long_answer'] = 3;
    }

    $typeNames = [
        'mcq' => 'Multiple Choice Questions',
        'short_answer' => 'Short Answer Questions',
        'long_answer' => 'Long Answer Questions',
        'true_false' => 'True / False',
        'fill_blank' => 'Fill in the Blanks',
    ];

    /**
     * Format a 1-based number using the per-section numbering style.
     * arabic = 1, 2, 3 · roman = I, II, III · alpha_upper = A, B, C · alpha_lower = a, b, c
     */
    $formatNumber = function (int $n, string $style): string {
        if ($n < 1) return (string) $n;
        if ($style === 'roman') {
            $map = [1000=>'M',900=>'CM',500=>'D',400=>'CD',100=>'C',90=>'XC',
                    50=>'L',40=>'XL',10=>'X',9=>'IX',5=>'V',4=>'IV',1=>'I'];
            $out = '';
            foreach ($map as $val => $sym) {
                while ($n >= $val) { $out .= $sym; $n -= $val; }
            }
            return $out;
        }
        if ($style === 'alpha_upper' || $style === 'alpha_lower') {
            // Spreadsheet-style: 1=A, 2=B, ... 26=Z, 27=AA, 28=AB, ...
            $out = '';
            $base = $style === 'alpha_upper' ? 'A' : 'a';
            $baseCode = ord($base);
            while ($n > 0) {
                $n--;
                $out = chr($baseCode + ($n % 26)) . $out;
                $n = intdiv($n, 26);
            }
            return $out;
        }
        return (string) $n; // arabic
    };
@endphp

@foreach($paper->sections ?? [] as $sIdx => $section)
    @php
        $sectionQuestions = $section['questions'] ?? [];
        $secCount = count($sectionQuestions);
        // Source of truth: sum the per-question marks of THIS section's
        // actual picked questions. Falls back to stored section_total_marks
        // (set at generation time) for older papers.
        $secTotal = $section['section_total_marks']
            ?? array_sum(array_map(fn ($qe) => (float) ($qe['marks'] ?? 0), $sectionQuestions));
        $secTotalLabel = rtrim(rtrim(number_format((float) $secTotal, 2), '0'), '.');
        $sectionAnswerLines = array_key_exists('answer_lines', $section) ? $section['answer_lines'] : null;
        $sectionName = !empty($section['label'])
            ? $section['label']
            : ($typeNames[$section['type']] ?? ucfirst(str_replace('_', ' ', $section['type'])));

        // Per-section numbering: restart counter if asked, pick the style.
        if (!empty($section['restart_numbering'])) {
            $qNum = 0;
        }
        $numberingStyle = $section['numbering_style'] ?? 'arabic';
    @endphp

    @if($paper->show_sections ?? true)
        <div class="section-heading">{{ $sectionName }}</div>
        <div class="section-meta">
            ({{ $secCount }} {{ $secCount === 1 ? 'question' : 'questions' }} &middot; {{ $secTotalLabel }} marks)
            @if(!empty($section['difficulty']) && $section['difficulty'] !== 'mixed')
                &middot; {{ ucfirst($section['difficulty']) }}
            @endif
        </div>
    @endif

    @foreach($section['questions'] ?? [] as $qEntry)
        @php
            $qid = $qEntry['question_id'] ?? null;
            $q = $qid ? ($questions[$qid] ?? null) : null;
            if (!$q) continue;
            $qNum++;
            $qLabel = $formatNumber($qNum, $numberingStyle);
            // Marks displayed = stored per-question marks (set when question
            // was created), falling back to the question model's own marks
            // value if that's missing. Never overridden by section default.
            $qMarks = $qEntry['marks'] ?? ($q->marks ?? 1);
            $qMarksLabel = rtrim(rtrim(number_format((float) $qMarks, 2), '0'), '.');
            $optOrder = $qEntry['option_order'] ?? null;

            $linesForThisQ = $sectionAnswerLines !== null
                ? (int) $sectionAnswerLines
                : ($defaultLinesByType[$q->type] ?? 0);
        @endphp

        <div class="q">
            <span class="q-marks">[{{ $qMarksLabel }}]</span>
            <span class="q-num">{{ $qLabel }}.</span>
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
            @endif

            @if($linesForThisQ > 0)
                <div class="answer-lines">
                    @for($i = 0; $i < $linesForThisQ; $i++)
                        <div class="line"></div>
                    @endfor
                </div>
            @endif
        </div>
    @endforeach
@endforeach

<div class="end-note">— END —</div>
