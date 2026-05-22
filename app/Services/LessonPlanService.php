<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Smart Lesson Plan generator.
 *
 * Given a topic + subject + class (+ duration / medium / level), produces a
 * structured, classroom-ready lesson plan.
 *
 *  - If ANTHROPIC_API_KEY is configured → a real, topic-aware plan written
 *    by Claude (returned as strict JSON).
 *  - Otherwise → keyless pipeline that pulls a rich Wikipedia article and
 *    distributes its sentences across the plan fields (definition, examples,
 *    applications, key points). For Urdu mode the whole plan is translated
 *    via Google Translate's free gtx endpoint.
 */
class LessonPlanService
{
    /** Generate a plan. Always returns the same array shape. */
    public function generate(array $in): array
    {
        $in = $this->normalise($in);

        if (config('services.anthropic.key')) {
            try {
                $ai = $this->viaClaude($in);
                if ($ai) {
                    return array_merge($this->shell($in, 'ai'), $ai);
                }
            } catch (\Throwable $e) {
                report($e); // fall through
            }
        }

        // ── Wikipedia-driven keyless path ──
        // Pull a rich article and use it to write actual topic content for
        // every field of the lesson plan instead of generic placeholders.
        $facts = null;
        try {
            $facts = $this->fetchTopicFacts($in['topic'], $in['subject']);
        } catch (\Throwable $e) {
            report($e);
        }

        $base = $this->template($in, $facts);
        $by = $facts ? 'reference' : 'template';

        // English/Urdu handling.
        if ($in['language'] === 'en') {
            $base['content']['summary_ur'] = '';
            $base['content']['key_points_ur'] = [];
        } elseif ($in['language'] === 'ur') {
            // If an Urdu Wikipedia article exists, prefer its real text for
            // the summary/key points (more natural than machine-translation).
            if (!empty($base['content']['summary_ur'])) {
                $base['content']['summary'] = $base['content']['summary_ur'];
                if (!empty($base['content']['key_points_ur'])) {
                    $base['content']['key_points'] = $base['content']['key_points_ur'];
                }
            }
            $base['content']['summary_ur'] = '';
            $base['content']['key_points_ur'] = [];
            $base = $this->toUrdu($base);
            $base['content']['is_urdu'] = true;
        }

        // Teacher's own notes win — shown verbatim.
        if ($in['notes'] !== '') {
            $base['content']['teacher_notes'] = $in['notes'];
        }

        return array_merge($this->shell($in, $by), $base);
    }

    // ─────────────────────── Urdu translation ───────────────────────

    /**
     * Translate every human-readable field of the plan to Urdu using
     * Google's free, keyless translate endpoint. Any failure leaves that
     * field in English (never breaks the plan).
     */
    protected function toUrdu(array $plan): array
    {
        $plan['objectives'] = $this->trList($plan['objectives'] ?? []);
        $plan['prior_knowledge'] = $this->tr($plan['prior_knowledge'] ?? '');
        $plan['materials'] = $this->trList($plan['materials'] ?? []);
        $plan['activities'] = $this->trList($plan['activities'] ?? []);
        $plan['assessment'] = $this->trList($plan['assessment'] ?? []);
        $plan['homework'] = $this->tr($plan['homework'] ?? '');
        $plan['board_plan'] = $this->tr($plan['board_plan'] ?? '');

        $c = $plan['content'] ?? [];
        $c['summary'] = $this->tr($c['summary'] ?? '');
        $c['key_points'] = $this->trList($c['key_points'] ?? []);
        $c['example'] = $this->tr($c['example'] ?? '');
        $c['misconception'] = $this->tr($c['misconception'] ?? '');
        $c['real_life'] = $this->tr($c['real_life'] ?? '');
        $plan['content'] = $c;

        if (!empty($plan['differentiation'])) {
            $plan['differentiation']['support'] = $this->tr($plan['differentiation']['support'] ?? '');
            $plan['differentiation']['challenge'] = $this->tr($plan['differentiation']['challenge'] ?? '');
        }

        foreach ($plan['vocabulary'] ?? [] as $i => $v) {
            $plan['vocabulary'][$i]['term'] = $this->tr($v['term'] ?? '');
            $plan['vocabulary'][$i]['meaning'] = $this->tr($v['meaning'] ?? '');
        }
        foreach ($plan['lesson_flow'] ?? [] as $i => $row) {
            $plan['lesson_flow'][$i]['phase'] = $this->tr($row['phase'] ?? '');
            $plan['lesson_flow'][$i]['teacher'] = $this->tr($row['teacher'] ?? '');
            $plan['lesson_flow'][$i]['student'] = $this->tr($row['student'] ?? '');
        }

        return $plan;
    }

    /** Translate a list of strings (kept element-for-element). */
    protected function trList(array $items): array
    {
        return array_map(fn ($s) => $this->tr((string) $s), $items);
    }

    /** Translate one English string → Urdu. Returns original on failure. */
    protected function tr(string $text): string
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) < 2) {
            return $text;
        }
        try {
            $res = Http::withHeaders([
                'User-Agent' => 'GBHSS-ExamMS/1.0 (Lesson Plan translate)',
            ])->timeout(8)->get('https://translate.googleapis.com/translate_a/single', [
                'client' => 'gtx', 'sl' => 'en', 'tl' => 'ur', 'dt' => 't', 'q' => $text,
            ]);
            if (!$res->successful()) {
                return $text;
            }
            $segments = data_get($res->json(), '0', []);
            $out = '';
            foreach ((array) $segments as $seg) {
                $out .= $seg[0] ?? '';
            }
            $out = trim($out);
            return $out !== '' ? $out : $text;
        } catch (\Throwable $e) {
            report($e);
            return $text;
        }
    }

    // ──────────────────────── Wikipedia facts ────────────────────────

    /**
     * Pull a rich factual extract of the topic from Wikipedia (free, keyless),
     * subject-aware, classifies sentences into buckets (definition / examples /
     * applications / key points) so the lesson plan can show ACTUAL content
     * instead of placeholders.
     */
    protected function fetchTopicFacts(string $topic, string $subject = ''): ?array
    {
        $http = Http::withHeaders([
            'User-Agent' => 'GBHSS-ExamMS/1.0 (Smart Lesson Plan; school management system)',
        ])->timeout(8);

        // 1. Resolve the best matching article. Try "topic + subject" first
        //    (disambiguates e.g. "Cell" in Biology), then the topic alone.
        $title = null;
        foreach (array_filter([trim($topic.' '.$subject), $topic]) as $q) {
            $r = $http->get('https://en.wikipedia.org/w/api.php', [
                'action' => 'query', 'list' => 'search', 'srsearch' => $q,
                'srlimit' => 1, 'format' => 'json',
            ]);
            $title = data_get($r->json(), 'query.search.0.title');
            if ($title) {
                break;
            }
        }
        if (!$title) {
            return null;
        }

        // 2. Full plaintext extract (no exintro) so we have enough body to
        //    extract examples + applications. Limit by chars to stay sane.
        $res = $http->get('https://en.wikipedia.org/w/api.php', [
            'action' => 'query', 'prop' => 'extracts|langlinks', 'explaintext' => 1,
            'exchars' => 4500, 'exsectionformat' => 'plain', 'redirects' => 1,
            'lllang' => 'ur', 'lllimit' => 1,
            'titles' => $title, 'format' => 'json', 'formatversion' => 2,
        ]);
        $page = (array) (data_get($res->json(), 'query.pages.0', []));
        $extract = trim((string) ($page['extract'] ?? ''));
        if (mb_strlen($extract) < 80) {
            return null;
        }

        $url = 'https://en.wikipedia.org/wiki/'.str_replace(' ', '_', $title);
        $buckets = $this->bucketSentences($extract);

        // 3. Urdu extract (intro only is enough — translated fields cover the rest).
        $summaryUr = '';
        $pointsUr = [];
        $urTitle = $page['langlinks'][0]['title'] ?? null;
        if ($urTitle) {
            try {
                $ur = $http->get('https://ur.wikipedia.org/w/api.php', [
                    'action' => 'query', 'prop' => 'extracts', 'exintro' => 1,
                    'explaintext' => 1, 'redirects' => 1, 'titles' => $urTitle,
                    'format' => 'json', 'formatversion' => 2,
                ]);
                $urPage = (array) (data_get($ur->json(), 'query.pages.0', []));
                $urExtract = trim((string) ($urPage['extract'] ?? ''));
                if (mb_strlen($urExtract) > 40) {
                    [$summaryUr, $pointsUr] = $this->splitUrdu($urExtract);
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return [
            'title' => $title,
            'definition' => $buckets['definition'],
            'summary' => $buckets['summary'],
            'key_points' => $buckets['key_points'],
            'examples' => $buckets['examples'],
            'applications' => $buckets['applications'],
            'summary_ur' => $summaryUr,
            'points_ur' => $pointsUr,
            'attribution' => "Source: Wikipedia — “{$title}” (CC BY-SA). {$url}",
            'url' => $url,
        ];
    }

    /**
     * Split a Wikipedia extract into thematic buckets a lesson plan can use:
     *   - definition   first 1 sentence (the textbook definition)
     *   - summary      first 2-3 sentences read out as the introduction
     *   - key_points   factual statements the teacher can list on the board
     *   - examples     sentences containing example-marker words
     *   - applications sentences about use / importance / impact
     */
    protected function bucketSentences(string $extract): array
    {
        // Strip section markers Wikipedia returns ("== History ==").
        $extract = preg_replace('/^==+[^=]+==+$/m', '', $extract);
        $sentences = $this->splitSentences($extract);

        $definition = $sentences[0] ?? '';
        $summary = implode(' ', array_slice($sentences, 0, 3));

        $examples = [];
        $applications = [];
        $keyPoints = [];

        $exMarkers = ['/\bfor example\b/i', '/\bfor instance\b/i', '/\be\.g\./i'];
        $appMarkers = [
            '/\bused (in|for|to|by|as)\b/i', '/\bapplications?\b/i', '/\bimportant (in|for|to)\b/i',
            '/\bessential (in|for|to)\b/i',
            '/\bplay(s)? a (key|major|critical|crucial|vital|significant|central) role\b/i',
            '/\bin everyday life\b/i', '/\bcommonly (found|seen|used)\b/i',
            '/\bis the (source|basis|foundation) of\b/i', '/\bresponsible for\b/i',
            '/\bsupplies\b/i', '/\benables\b/i', '/\b(produces|provides)\b.*\b(energy|life|oxygen|food)\b/i',
        ];

        foreach (array_slice($sentences, 1) as $s) {
            $isEx = $this->matchesAny($s, $exMarkers);
            $isApp = $this->matchesAny($s, $appMarkers);

            if ($isEx && count($examples) < 3) {
                $examples[] = $s;
            } elseif ($isApp && count($applications) < 3) {
                $applications[] = $s;
            } elseif (count($keyPoints) < 7 && mb_strlen($s) >= 30) {
                $keyPoints[] = $s;
            }
        }

        return [
            'definition' => $definition,
            'summary' => $summary,
            'key_points' => array_values($keyPoints),
            'examples' => array_values($examples),
            'applications' => array_values($applications),
        ];
    }

    protected function matchesAny(string $text, array $regexes): bool
    {
        foreach ($regexes as $r) {
            if (preg_match($r, $text)) {
                return true;
            }
        }
        return false;
    }

    /** Sentence-split an English extract, dropping weird short fragments. */
    protected function splitSentences(string $extract): array
    {
        $parts = preg_split('/(?<=[.!?])\s+(?=[A-Z0-9“"])/u', $extract) ?: [];
        return array_values(array_filter(
            array_map(fn ($s) => trim((string) $s), $parts),
            fn ($s) => mb_strlen($s) >= 20
        ));
    }

    /** Sentence-split an Urdu extract (Urdu uses ۔ as the full stop). */
    protected function splitUrdu(string $extract): array
    {
        $parts = preg_split('/(?<=۔)\s+/u', $extract) ?: [];
        $sentences = array_values(array_filter(
            array_map(fn ($s) => trim((string) $s), $parts),
            fn ($s) => mb_strlen($s) > 8
        ));
        return [
            implode(' ', array_slice($sentences, 0, 3)),
            array_slice($sentences, 3, 7),
        ];
    }

    /** Render a plan array (already generated) — used by the PDF endpoint. */
    public function normaliseForPdf(array $plan, array $in): array
    {
        return array_merge($this->shell($this->normalise($in), $plan['generated_by'] ?? 'template'), $plan);
    }

    // ─────────────────────────── helpers ───────────────────────────

    protected function normalise(array $in): array
    {
        return [
            'topic'    => trim($in['topic'] ?? '') ?: 'Untitled topic',
            'subject'  => trim($in['subject'] ?? '') ?: 'General',
            'class'    => trim($in['class'] ?? '') ?: 'Class',
            'duration' => max(15, min(240, (int) ($in['duration'] ?? 40))),
            'medium'   => trim($in['medium'] ?? '') ?: 'English',
            'level'    => trim($in['level'] ?? '') ?: 'mixed-ability',
            'notes'    => trim($in['notes'] ?? ''),
            'language' => in_array($in['language'] ?? 'en', ['en', 'ur', 'both'], true)
                ? $in['language'] : 'en',
            'school'   => trim($in['school'] ?? ''),
        ];
    }

    /** Common meta wrapper merged onto every plan. */
    protected function shell(array $in, string $by): array
    {
        return [
            'topic' => $in['topic'],
            'subject' => $in['subject'],
            'class' => $in['class'],
            'duration_minutes' => $in['duration'],
            'medium' => $in['medium'],
            'level' => $in['level'],
            'language' => $in['language'] ?? 'en',
            'generated_by' => $by,
        ];
    }

    // ─────────────────────────── Claude ───────────────────────────

    protected function viaClaude(array $in): ?array
    {
        $system = <<<SYS
        You are an expert curriculum designer creating lesson plans for school
        teachers in Pakistan. Produce a practical, classroom-ready plan.
        Respond with ONLY valid minified JSON (no markdown, no commentary)
        matching exactly this schema:
        {
          "objectives": ["3-5 measurable SWBAT objectives"],
          "content": {
            "summary": "2-4 sentence accurate explanation OF THE ACTUAL TOPIC a teacher can read out",
            "summary_ur": "the same summary translated into natural Urdu",
            "key_points": ["5-8 specific factual teaching points about the topic"],
            "key_points_ur": ["the key_points translated into Urdu, same order"],
            "example": "a concrete worked example / illustration specific to the topic",
            "misconception": "a common student misconception about this topic + the correction",
            "real_life": "a real-life application/connection of the topic"
          },
          "prior_knowledge": "what students should already know",
          "materials": ["items/resources needed"],
          "vocabulary": [{"term":"","meaning":""}],
          "lesson_flow": [{"phase":"","minutes":0,"teacher":"what the teacher does","student":"what students do"}],
          "activities": ["1-3 concrete learning activities"],
          "assessment": ["checks for understanding / questions"],
          "homework": "a clear homework task",
          "differentiation": {"support":"for struggling learners","challenge":"for advanced learners"},
          "board_plan": "what to write on the board",
          "references": ["textbook chapter / resources"]
        }
        The sum of lesson_flow minutes must equal the total duration.
        SYS;

        $u = $in;
        $user = "Create a lesson plan.\n"
            . "Topic: {$u['topic']}\nSubject: {$u['subject']}\nClass/Grade: {$u['class']}\n"
            . "Lesson duration: {$u['duration']} minutes\nMedium of instruction: {$u['medium']}\n"
            . "Learner level: {$u['level']}\n"
            . ($u['notes'] !== '' ? "The teacher specifically wants this included: {$u['notes']}\n" : '')
            . match ($u['language']) {
                'ur' => "Write the ENTIRE lesson plan in Urdu — every field (objectives, content, lesson_flow, etc.) in natural Urdu. Leave the *_ur fields empty.\n",
                'both' => "Write the plan in English and ALSO fill every *_ur field with the Urdu translation.\n",
                default => "Write the plan in English. Leave the *_ur fields empty.\n",
            }
            . "Make the content accurate and specific to the subject. Return only the JSON.";

        $res = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model', 'claude-sonnet-4-6'),
            'max_tokens' => 2200,
            'system' => [[
                'type' => 'text',
                'text' => $system,
                'cache_control' => ['type' => 'ephemeral'],
            ]],
            'messages' => [['role' => 'user', 'content' => $user]],
        ]);

        if (!$res->successful()) {
            report(new \RuntimeException('Anthropic API ' . $res->status() . ': ' . $res->body()));
            return null;
        }

        $text = data_get($res->json(), 'content.0.text', '');
        $text = trim(preg_replace('/^```(json)?|```$/m', '', $text));
        $json = json_decode($text, true);

        if (!is_array($json) || empty($json['objectives'])) {
            return null;
        }
        return [
            'objectives' => array_values((array) ($json['objectives'] ?? [])),
            'content' => [
                'summary' => (string) data_get($json, 'content.summary', ''),
                'summary_ur' => (string) data_get($json, 'content.summary_ur', ''),
                'key_points' => array_values((array) data_get($json, 'content.key_points', [])),
                'key_points_ur' => array_values((array) data_get($json, 'content.key_points_ur', [])),
                'teacher_notes' => $in['notes'] ?? '',
                'example' => (string) data_get($json, 'content.example', ''),
                'misconception' => (string) data_get($json, 'content.misconception', ''),
                'real_life' => (string) data_get($json, 'content.real_life', ''),
            ],
            'prior_knowledge' => (string) ($json['prior_knowledge'] ?? ''),
            'materials' => array_values((array) ($json['materials'] ?? [])),
            'vocabulary' => array_values((array) ($json['vocabulary'] ?? [])),
            'lesson_flow' => array_values((array) ($json['lesson_flow'] ?? [])),
            'activities' => array_values((array) ($json['activities'] ?? [])),
            'assessment' => array_values((array) ($json['assessment'] ?? [])),
            'homework' => (string) ($json['homework'] ?? ''),
            'differentiation' => [
                'support' => (string) data_get($json, 'differentiation.support', ''),
                'challenge' => (string) data_get($json, 'differentiation.challenge', ''),
            ],
            'board_plan' => (string) ($json['board_plan'] ?? ''),
            'references' => array_values((array) ($json['references'] ?? [])),
        ];
    }

    // ─────────────────────── offline template ───────────────────────

    /**
     * Build a topic-aware plan from the Wikipedia $facts when available;
     * falls back to a scaffold when no article was found. Every field is
     * filled with real, topic-specific content rather than placeholders.
     */
    protected function template(array $in, ?array $facts = null): array
    {
        $t = $in['topic'];
        $s = $in['subject'];
        $d = $in['duration'];

        // Split the period into sensible phases (sums to $d).
        $intro = max(5, (int) round($d * 0.12));
        $recap = max(3, (int) round($d * 0.10));
        $assess = max(4, (int) round($d * 0.12));
        $close = max(3, (int) round($d * 0.08));
        $dev = max(5, $d - $intro - $recap - $assess - $close);

        $hasFacts = is_array($facts) && !empty($facts['summary']);
        $definition = $facts['definition'] ?? '';
        $summary = $facts['summary'] ?? '';
        $keyPoints = $facts['key_points'] ?? [];
        $examples = $facts['examples'] ?? [];
        $applications = $facts['applications'] ?? [];

        // ── Build each plan field from real content where possible. ──

        $objectives = $hasFacts
            ? array_values(array_filter([
                "Define “{$t}” in their own words: ".$this->shortDef($definition).'',
                "Identify the main features / components of “{$t}” described in the lesson.",
                $examples ? "Give at least one example of “{$t}” and explain why it qualifies." : "Apply “{$t}” to solve a simple {$s} problem.",
                $applications ? "Describe one real-life use or importance of “{$t}”." : "Relate “{$t}” to something they see in everyday life.",
            ]))
            : [
                "Define and explain the key idea of “{$t}” in their own words.",
                "Identify the main components / steps related to “{$t}”.",
                "Apply the concept of “{$t}” to solve a simple {$s} problem or example.",
                "Relate “{$t}” to a real-life situation relevant to their level.",
            ];

        $example = $hasFacts && $examples
            ? $examples[0]
            : "Solve/illustrate one clear example of “{$t}” on the board, narrating each step so students can copy the method.";

        $realLife = $hasFacts && $applications
            ? $applications[0]
            : "Connect “{$t}” to something students see in daily life so the concept feels concrete.";

        $misconception = $hasFacts && count($keyPoints) >= 2
            ? "Students often confuse “{$t}” with related ideas. Correct this by re-reading: «".$keyPoints[1]."»"
            : "Anticipate the usual mistake students make with “{$t}” (e.g. confusing it with a similar concept) and explicitly correct it.";

        $boardPlan = $hasFacts
            ? "Title: {$t}\nDefinition: ".$this->shortDef($definition)."\nKey points: ".implode(' • ', array_slice($keyPoints, 0, 3))
            : "Title: {$t}  |  Objective  |  Key definition  |  Worked example  |  Summary points";

        $vocabulary = [
            ['term' => $t, 'meaning' => $hasFacts
                ? $this->shortDef($definition)
                : "Core concept introduced in this lesson — define clearly with one example."],
        ];
        // If we have applications text, surface one more vocab term from it.
        if ($hasFacts && $applications) {
            $vocabulary[] = [
                'term' => 'Application',
                'meaning' => $applications[0],
            ];
        } else {
            $vocabulary[] = ['term' => 'Key term 2', 'meaning' => 'Supporting term the teacher should pre-select from the chapter.'];
        }

        $devTeacher = $hasFacts
            ? "Explain {$t} step-by-step using the summary on the board: «"
                .$this->shortDef($summary)
                ."». Walk through "
                .(count($keyPoints) >= 3 ? count($keyPoints) : 'the')
                ." key points one at a time, modelling each on the board with examples."
            : "Explain step-by-step with the board plan and visual aid; model 1–2 worked examples; check understanding frequently.";

        $homework = $hasFacts
            ? "Read the textbook section on “{$t}” and write a 5-sentence explanation that includes: (a) the definition, (b) two key points discussed in class, (c) one real-life example."
            : "Read the textbook section on “{$t}” and complete the end-of-topic exercise; write 3 sentences explaining it in your own words.";

        $activities = $hasFacts ? array_values(array_filter([
            "Reading task: students read the summary «".$this->shortDef($summary)."» and underline the keywords.",
            $examples ? "Example walk-through: discuss the example «".$examples[0]."» and ask students to think of one of their own." : null,
            $applications ? "Group task: in pairs, write one sentence on why “{$t}” is important using «".$applications[0]."»." : "Group task: produce a labelled diagram / 3-point summary of “{$t}”.",
        ])) : [
            "Think–Pair–Share: students discuss one question about “{$t}” then report back.",
            "Worked example walkthrough on the board, then a similar problem solved by a student.",
            "Quick group task: produce a labelled diagram / 3-point summary of “{$t}”.",
        ];

        $assessment = $hasFacts ? array_values(array_filter([
            "Oral question: \"In your own words, what is {$t}?\" Expect: ".$this->shortDef($definition),
            count($keyPoints) ? "Written question: list any 2 key points about “{$t}” discussed today." : null,
            $applications ? "MCQ-style: \"Which of these is a real-life use of {$t}?\" — accept variations of: ".$applications[0] : 'Exit ticket: each student writes one thing learned and one question.',
        ])) : [
            "Oral questions: “What is {$t}? Give one example.”",
            "1–2 written practice questions graded for understanding.",
            'Exit ticket: each student writes one thing learned and one question.',
        ];

        $references = [
            "{$s} textbook — chapter covering “{$t}”",
            'Teacher’s guide / scheme of work',
        ];
        if ($hasFacts) {
            array_unshift($references, $facts['attribution']);
        }

        return [
            'objectives' => $objectives,
            'content' => [
                'summary' => $hasFacts
                    ? $summary
                    : "Introduce “{$t}” in {$s}: state its clear definition, why it matters, and where it fits in the chapter.",
                'summary_ur' => $facts['summary_ur'] ?? '',
                'key_points' => $hasFacts && $keyPoints
                    ? array_slice($keyPoints, 0, 7)
                    : [
                        "Definition of “{$t}” — the precise meaning students must memorise.",
                        "Main parts / steps / components that make up “{$t}”.",
                        "How “{$t}” works or is carried out (process or reasoning).",
                        "A worked example showing “{$t}” applied step by step.",
                        "Why “{$t}” is important / where it is used.",
                    ],
                'key_points_ur' => $facts['points_ur'] ?? [],
                'teacher_notes' => '',
                'example' => $example,
                'misconception' => $misconception,
                'real_life' => $realLife,
            ],
            'prior_knowledge' => $hasFacts
                ? "Recall the previous {$s} lesson; review terms needed to understand “{$t}”. Start with 2–3 quick questions about prerequisites that appear in the summary above."
                : "Students should recall the previous {$s} lesson and basic terms that lead into “{$t}”. Begin with 2–3 quick questions to surface what they already know.",
            'materials' => [
                'Whiteboard / blackboard & markers or chalk',
                "{$s} textbook (relevant chapter on “{$t}”)",
                'Chart / diagram or visual aid for the topic',
                'Notebook & worksheet for practice',
            ],
            'vocabulary' => $vocabulary,
            'lesson_flow' => [
                ['phase' => 'Introduction & motivation', 'minutes' => $intro,
                 'teacher' => $hasFacts
                    ? "Hook the class with this question: \"Have you ever heard of {$t}? What do you think it means?\". Then state the lesson objective."
                    : "Hook the class with a question/real example about “{$t}”; state the lesson objective.",
                 'student' => 'Respond to the hook, share ideas, note the objective.'],
                ['phase' => 'Recall prior knowledge', 'minutes' => $recap,
                 'teacher' => 'Ask quick questions linking the last lesson to today’s topic.',
                 'student' => 'Answer orally; connect old and new ideas.'],
                ['phase' => "Development — teaching “{$t}”", 'minutes' => $dev,
                 'teacher' => $devTeacher,
                 'student' => 'Listen, take notes, attempt guided examples, ask questions.'],
                ['phase' => 'Assessment / practice', 'minutes' => $assess,
                 'teacher' => 'Set short practice questions; circulate and give feedback.',
                 'student' => 'Solve practice tasks individually or in pairs.'],
                ['phase' => 'Closure & homework', 'minutes' => $close,
                 'teacher' => 'Summarise key points, ask 2 recap questions, assign homework.',
                 'student' => 'Recap aloud, note the homework.'],
            ],
            'activities' => $activities,
            'assessment' => $assessment,
            'homework' => $homework,
            'differentiation' => [
                'support' => 'Provide a partially-filled notes sheet, pair weaker students with a peer, use a simpler example first.',
                'challenge' => "Give an extension problem applying “{$t}” to an unfamiliar context or a short ‘explain why’ question.",
            ],
            'board_plan' => $boardPlan,
            'references' => $references,
        ];
    }

    /** Trim a long sentence to ~180 chars without breaking words. */
    protected function shortDef(string $s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s));
        if (mb_strlen($s) <= 220) {
            return $s;
        }
        return rtrim(mb_substr($s, 0, 220), " ,.;:-").'…';
    }
}
