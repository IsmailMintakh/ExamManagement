@php
    $logo = $school && !empty($school->logo) && file_exists(public_path('storage/'.$school->logo))
        ? public_path('storage/'.$school->logo) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Lesson Plan — {{ $plan['topic'] }}</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'DejaVu Sans', Arial, sans-serif; font-size:10.5px; color:#1a1a1a; }
    .page { padding:14mm; }
    .hdr { text-align:center; border-bottom:3px double #4c1d95; padding-bottom:8px; margin-bottom:12px; }
    .sch { font-size:17px; font-weight:bold; color:#4c1d95; text-transform:uppercase; }
    .doc { font-size:12px; font-weight:bold; color:#2d3748; margin-top:4px;
           background:#ede9fe; display:inline-block; padding:3px 16px; border-radius:3px; }
    .meta { width:100%; border-collapse:collapse; margin-bottom:12px; font-size:10px; }
    .meta td { border:1px solid #cbd5e1; padding:5px 8px; }
    .meta .lbl { background:#f5f3ff; font-weight:bold; width:18%; color:#4c1d95; text-transform:uppercase; font-size:8.5px; }
    h2 { font-size:11.5px; color:#4c1d95; border-bottom:1px solid #ddd6fe; padding-bottom:3px;
         margin:14px 0 6px; text-transform:uppercase; letter-spacing:.3px; }
    ul { margin:0 0 4px 18px; } li { margin-bottom:3px; line-height:1.45; }
    p { line-height:1.5; margin-bottom:4px; }
    table.flow { width:100%; border-collapse:collapse; margin-top:4px; font-size:9.5px; }
    table.flow th { background:#4c1d95; color:#fff; padding:5px 6px; text-align:left; font-size:8.5px; text-transform:uppercase; }
    table.flow td { border:1px solid #c7c7d9; padding:5px 6px; vertical-align:top; }
    table.flow td.m { text-align:center; font-weight:bold; width:8%; }
    .two { width:100%; } .two td { width:50%; vertical-align:top; padding-right:10px; }
    .box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; padding:6px 9px; font-family:'DejaVu Sans Mono', monospace; font-size:9.5px; }
    .foot { margin-top:26px; width:100%; }
    .foot td { width:50%; padding-top:34px; font-size:9px; }
    .sig { border-top:1px solid #1a1a1a; padding-top:3px; font-weight:bold; display:inline-block; min-width:62%; }
    .tag { font-size:8px; color:#888; text-align:right; margin-top:18px; border-top:1px solid #eee; padding-top:6px; }
</style>
</head>
<body>
<div class="page">
    <div class="hdr">
        <div class="sch">{{ $school->name ?? 'School' }}</div>
        <div class="doc">LESSON PLAN</div>
    </div>

    <table class="meta">
        <tr>
            <td class="lbl">Topic</td><td><strong>{{ $plan['topic'] }}</strong></td>
            <td class="lbl">Subject</td><td>{{ $plan['subject'] }}</td>
        </tr>
        <tr>
            <td class="lbl">Class</td><td>{{ $plan['class'] }}</td>
            <td class="lbl">Duration</td><td>{{ $plan['duration_minutes'] }} minutes</td>
        </tr>
        <tr>
            <td class="lbl">Teacher</td><td>{{ $teacher->name ?? '—' }}</td>
            <td class="lbl">Date</td><td>{{ $generatedAt->format('d M Y') }}</td>
        </tr>
    </table>

    <h2>Learning Objectives</h2>
    <ul>@foreach($plan['objectives'] ?? [] as $o)<li>{{ $o }}</li>@endforeach</ul>

    @if(!empty($plan['content']['teacher_notes']))
        <h2>Teacher's Notes</h2>
        <p>{{ $plan['content']['teacher_notes'] }}</p>
    @endif

    @if(!empty($plan['content']))
        <h2>Topic Content</h2>
        @if(!empty($plan['content']['summary']))<p>{{ $plan['content']['summary'] }}</p>@endif
        @if(!empty($plan['content']['key_points']))
            <ul>@foreach($plan['content']['key_points'] as $k)<li>{{ $k }}</li>@endforeach</ul>
        @endif
        @if(!empty($plan['content']['example']))<p><strong>Example:</strong> {{ $plan['content']['example'] }}</p>@endif
        @if(!empty($plan['content']['misconception']))<p><strong>Common misconception:</strong> {{ $plan['content']['misconception'] }}</p>@endif
        @if(!empty($plan['content']['real_life']))<p><strong>Real-life link:</strong> {{ $plan['content']['real_life'] }}</p>@endif
    @endif

    @if(!empty($plan['prior_knowledge']))
        <h2>Prior Knowledge</h2>
        <p>{{ $plan['prior_knowledge'] }}</p>
    @endif

    @if(!empty($plan['materials']))
        <h2>Materials &amp; Resources</h2>
        <ul>@foreach($plan['materials'] as $m)<li>{{ $m }}</li>@endforeach</ul>
    @endif

    @if(!empty($plan['vocabulary']))
        <h2>Key Vocabulary</h2>
        <ul>@foreach($plan['vocabulary'] as $v)<li><strong>{{ $v['term'] ?? '' }}</strong> — {{ $v['meaning'] ?? '' }}</li>@endforeach</ul>
    @endif

    @if(!empty($plan['lesson_flow']))
        <h2>Lesson Flow</h2>
        <table class="flow">
            <thead><tr><th>Phase</th><th style="width:8%;text-align:center">Min</th><th>Teacher activity</th><th>Student activity</th></tr></thead>
            <tbody>
            @foreach($plan['lesson_flow'] as $p)
                <tr>
                    <td><strong>{{ $p['phase'] ?? '' }}</strong></td>
                    <td class="m">{{ $p['minutes'] ?? '' }}</td>
                    <td>{{ $p['teacher'] ?? '' }}</td>
                    <td>{{ $p['student'] ?? '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <table class="two"><tr>
        <td>
            @if(!empty($plan['activities']))
                <h2>Activities</h2>
                <ul>@foreach($plan['activities'] as $a)<li>{{ $a }}</li>@endforeach</ul>
            @endif
        </td>
        <td>
            @if(!empty($plan['assessment']))
                <h2>Assessment</h2>
                <ul>@foreach($plan['assessment'] as $a)<li>{{ $a }}</li>@endforeach</ul>
            @endif
        </td>
    </tr></table>

    @if(!empty($plan['differentiation']))
        <h2>Differentiation</h2>
        <p><strong>Support:</strong> {{ $plan['differentiation']['support'] ?? '' }}</p>
        <p><strong>Challenge:</strong> {{ $plan['differentiation']['challenge'] ?? '' }}</p>
    @endif

    @if(!empty($plan['homework']))
        <h2>Homework</h2>
        <p>{{ $plan['homework'] }}</p>
    @endif

    @if(!empty($plan['board_plan']))
        <h2>Board Plan</h2>
        <div class="box">{{ $plan['board_plan'] }}</div>
    @endif

    @if(!empty($plan['references']))
        <h2>References</h2>
        <ul>@foreach($plan['references'] as $r)<li>{{ $r }}</li>@endforeach</ul>
    @endif

    <table class="foot">
        <tr>
            <td><span class="sig">Teacher's Signature</span></td>
            <td style="text-align:right"><span class="sig">Head Teacher / Coordinator</span></td>
        </tr>
    </table>

    <div class="tag">
        Generated {{ $generatedAt->format('d M Y, h:i A') }}
        · {{ ($plan['generated_by'] ?? '') === 'ai' ? 'AI-assisted' : (($plan['generated_by'] ?? '') === 'reference' ? 'Sourced (Wikipedia)' : 'Template') }}
        · {{ $school->name ?? '' }}
    </div>
</div>
</body>
</html>
