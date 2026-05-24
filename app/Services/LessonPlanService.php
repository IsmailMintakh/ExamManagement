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

        // Urdu mode: when we have a real Urdu Wikipedia intro, seed the
        // Introduction with it before translating everything else. Also
        // translate the topic / subject / class shown in the meta table so
        // the Topic field reads "حضرت محمد" rather than English carry-over.
        if ($in['language'] === 'ur') {
            if (!empty($base['content']['summary_ur'] ?? '')) {
                $base['content']['introduction'] = $base['content']['summary_ur'];
            }
            $base = $this->toUrdu($base);
            $base['content']['is_urdu'] = true;
            $in['topic']   = $this->tr($in['topic']   ?? '');
            $in['subject'] = $this->tr($in['subject'] ?? '');
            $in['class']   = $this->tr($in['class']   ?? '');
        }
        // Strip the wikipedia-only carrier fields — they're not used by the
        // PDF / page directly, only by the translation seed above.
        unset($base['content']['summary_ur'], $base['content']['key_points_ur']);

        // Teacher's own notes carried through for reference (PDF / page may
        // surface them under Content Knowledge).
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
        $plan['homework'] = $this->trList(is_array($plan['homework'] ?? null) ? $plan['homework'] : []);

        $c = $plan['content'] ?? [];
        $c['introduction'] = $this->tr($c['introduction'] ?? '');
        $c['definition'] = $this->tr($c['definition'] ?? '');
        $c['characteristics'] = $this->trList($c['characteristics'] ?? []);
        $c['examples'] = $this->trList($c['examples'] ?? []);
        $c['class_activity'] = $this->tr($c['class_activity'] ?? '');
        $c['teaching_methods'] = $this->trList($c['teaching_methods'] ?? []);
        $c['teaching_aids'] = $this->trList($c['teaching_aids'] ?? []);
        $plan['content'] = $c;

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
        // PHP 8.5 is stricter about undefined array keys. Coerce the language
        // input to a defined value BEFORE the in_array check, otherwise the
        // PDF endpoint (which doesn't post a language) blows up here.
        $lang = $in['language'] ?? 'en';
        return [
            'topic'    => trim($in['topic'] ?? '') ?: 'Untitled topic',
            'subject'  => trim($in['subject'] ?? '') ?: 'General',
            'class'    => trim($in['class'] ?? '') ?: 'Class',
            'duration' => max(15, min(240, (int) ($in['duration'] ?? 40))),
            'medium'   => trim($in['medium'] ?? '') ?: 'English',
            'level'    => trim($in['level'] ?? '') ?: 'mixed-ability',
            'notes'    => trim($in['notes'] ?? ''),
            'language' => in_array($lang, ['en', 'ur', 'both'], true) ? $lang : 'en',
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
        // Schema matches the official Schools Education Department
        // Gilgit-Baltistan SMART LESSON PLAN: three sections, with the
        // Content Knowledge section broken into the same subsections the
        // printed form uses (Introduction, Definition, Characteristics,
        // Examples, Class Activity, Teaching Method, Teaching Aids).
        $system = <<<SYS
        You are an expert curriculum designer writing lesson plans for school
        teachers in Pakistan, following the official Gilgit-Baltistan SMART
        LESSON PLAN format (Schools Education Department).

        Respond with ONLY valid minified JSON (no markdown, no commentary)
        matching exactly this schema:
        {
          "objectives": [
            "4 student learning outcomes — short, measurable, focused on the topic"
          ],
          "content": {
            "introduction": "1-2 sentence intro to the topic for the teacher to open the lesson",
            "definition": "the textbook definition of the topic in one clear sentence",
            "characteristics": ["3-6 key characteristics / properties of the topic"],
            "examples": ["3-5 concrete examples, each a short phrase"],
            "class_activity": "one practical class activity students will do — one sentence",
            "teaching_methods": ["3-5 methods, e.g. Lecture, Q&A, Group Activity, Demonstration"],
            "teaching_aids": ["3-5 aids, e.g. Whiteboard, Marker, Textbook, Computer"]
          },
          "homework": [
            "3-5 numbered homework tasks — specific, doable at home"
          ]
        }

        Rules:
        - Write content that is FACTUALLY ACCURATE for the topic in the
          given subject and class level. No filler like "Definition: state
          the textbook definition" — write the actual definition.
        - Keep each list item one line.
        - When the language is Urdu, write every value in natural Urdu.
        SYS;

        $u = $in;
        $user = "Create a SMART LESSON PLAN.\n"
            . "Topic: {$u['topic']}\nSubject: {$u['subject']}\nClass/Grade: {$u['class']}\n"
            . "Lesson duration: {$u['duration']} minutes\nMedium of instruction: {$u['medium']}\n"
            . "Learner level: {$u['level']}\n"
            . ($u['notes'] !== '' ? "The teacher specifically wants this included: {$u['notes']}\n" : '')
            . match ($u['language']) {
                'ur' => "Write the ENTIRE plan in natural Urdu.\n",
                'both' => "Write the plan in English (the printed form is English).\n",
                default => "Write the plan in English.\n",
            }
            . "Return only the JSON.";

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
        // Homework may arrive as a string OR an array — normalise to array.
        $hw = $json['homework'] ?? [];
        if (is_string($hw)) {
            $hw = array_values(array_filter(array_map('trim', preg_split('/\r?\n|;|·/u', $hw) ?: [])));
        }

        return [
            'objectives' => array_slice(array_values((array) ($json['objectives'] ?? [])), 0, 6),
            'content' => [
                'introduction' => (string) data_get($json, 'content.introduction', ''),
                'definition' => (string) data_get($json, 'content.definition', ''),
                'characteristics' => array_values((array) data_get($json, 'content.characteristics', [])),
                'examples' => array_values((array) data_get($json, 'content.examples', [])),
                'class_activity' => (string) data_get($json, 'content.class_activity', ''),
                'teaching_methods' => array_values((array) data_get($json, 'content.teaching_methods', [])),
                'teaching_aids' => array_values((array) data_get($json, 'content.teaching_aids', [])),
                'teacher_notes' => $in['notes'] ?? '',
            ],
            'homework' => array_slice(array_values((array) $hw), 0, 6),
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

        $hasFacts = is_array($facts) && !empty($facts['summary']);
        $definition = $facts['definition'] ?? '';
        $summary = $facts['summary'] ?? '';
        $keyPoints = $facts['key_points'] ?? [];
        $examples = $facts['examples'] ?? [];
        $applications = $facts['applications'] ?? [];

        // ── Build the three official sections of the GB Smart Lesson Plan ──
        // Everything below varies by SUBJECT type (Math vs Science vs CS vs
        // Language vs History) AND uses the Wikipedia facts when we have
        // them, so two different topics produce two different lesson plans.

        $kind = $this->subjectKind($s);
        $firstChar = $hasFacts && !empty($keyPoints) ? $this->shortDef($keyPoints[0]) : '';
        $firstExample = $hasFacts && !empty($examples) ? $this->shortDef($examples[0]) : '';
        $firstApp = $hasFacts && !empty($applications) ? $this->shortDef($applications[0]) : '';

        // 1. Student Learning Outcomes — four lines i/ii/iii/iv, each picks
        //    a verb appropriate to the subject + (when possible) substitutes
        //    a real fact pulled from Wikipedia so the outcome is concrete.
        $objectives = [
            $hasFacts && $definition
                ? "Define {$t}: “" . $this->shortDef($definition, 140) . "”"
                : $this->verb($kind, 'define') . " the term “{$t}” and write its meaning in their own words.",

            $firstChar
                ? $this->verb($kind, 'identify') . " the main features of “{$t}”, including: " . $firstChar
                : $this->verb($kind, 'identify') . " the main features / components of “{$t}”.",

            $firstExample
                ? $this->verb($kind, 'apply') . " “{$t}” using examples like: " . $firstExample
                : $this->verb($kind, 'apply') . " “{$t}” to a {$s} problem of the kind covered in the textbook.",

            $firstApp
                ? $this->verb($kind, 'relate') . " “{$t}” to real life — e.g. " . $firstApp
                : $this->verb($kind, 'relate') . " “{$t}” to something students see in everyday life.",
        ];

        // 2. Content Knowledge — pulled from Wikipedia when available.
        $introduction = $hasFacts
            ? "Introduce “{$t}” in {$s}. " . $this->shortDef(explode('. ', $summary)[0] ?? $summary)
            : "Introduce “{$t}” in {$s}. State the textbook definition and explain why the topic matters in this chapter.";

        $definitionLine = $hasFacts && $definition
            ? $this->shortDef($definition)
            : "Write the precise textbook definition of “{$t}” on the board.";

        $characteristics = $hasFacts && !empty($keyPoints)
            ? array_slice(array_map(fn ($p) => $this->shortDef($p), $keyPoints), 0, 5)
            : $this->fallbackCharacteristics($kind, $t);

        $exampleList = $hasFacts && !empty($examples)
            ? array_slice($examples, 0, 4)
            : $this->fallbackExamples($kind, $t);

        $classActivity = $this->classActivity($kind, $t, $firstExample);
        $teachingMethods = $this->teachingMethods($kind);
        $teachingAids = $this->teachingAids($kind, $s);

        // 3. Homework — subject-specific verbs + Wikipedia facts when present.
        $homework = $this->homework($kind, $t, $s, $hasFacts, $firstChar, $firstExample, $firstApp);

        return [
            'objectives' => $objectives,
            'content' => [
                'introduction' => $introduction,
                'definition' => $definitionLine,
                'characteristics' => $characteristics,
                'examples' => $exampleList,
                'class_activity' => $classActivity,
                'teaching_methods' => $teachingMethods,
                'teaching_aids' => $teachingAids,
                'teacher_notes' => '',
                // Urdu strings carried through for the Urdu-mode pipeline.
                'summary_ur' => $facts['summary_ur'] ?? '',
                'key_points_ur' => $facts['points_ur'] ?? [],
            ],
            'homework' => $homework,
        ];
    }

    /** Bucket the subject name into a teaching kind so we can tailor verbs/aids. */
    protected function subjectKind(string $subject): string
    {
        $s = strtolower($subject);
        if (str_contains($s, 'computer') || str_contains($s, 'ict') || str_contains($s, 'tech')) return 'cs';
        if (str_contains($s, 'math') || str_contains($s, 'algebra') || str_contains($s, 'geometry')) return 'math';
        if (str_contains($s, 'science') || str_contains($s, 'biology') || str_contains($s, 'physics') || str_contains($s, 'chemistry')) return 'science';
        if (str_contains($s, 'english') || str_contains($s, 'urdu') || str_contains($s, 'literature') || str_contains($s, 'language')) return 'language';
        if (str_contains($s, 'history') || str_contains($s, 'social') || str_contains($s, 'civics') || str_contains($s, 'pak studies') || str_contains($s, 'geo')) return 'social';
        if (str_contains($s, 'islam') || str_contains($s, 'quran') || str_contains($s, 'ethics')) return 'islamiyat';
        return 'general';
    }

    /** Subject-aware verb for the SLO sentence — keeps each plan from sounding identical. */
    protected function verb(string $kind, string $intent): string
    {
        $map = [
            'define'   => [
                'math' => 'State', 'cs' => 'Define', 'science' => 'Explain',
                'language' => 'Explain the meaning of', 'social' => 'Describe',
                'islamiyat' => 'Recall', 'general' => 'Define',
            ],
            'identify' => [
                'math' => 'List the rules / steps of', 'cs' => 'Identify the components of',
                'science' => 'Label the parts of', 'language' => 'Identify the key features of',
                'social' => 'List the main events / facts of', 'islamiyat' => 'Recall the key teachings of',
                'general' => 'Identify the main components of',
            ],
            'apply'    => [
                'math' => 'Solve problems using', 'cs' => 'Trace / write a simple example of',
                'science' => 'Demonstrate', 'language' => 'Use in their own sentences',
                'social' => 'Explain with reference to', 'islamiyat' => 'Practise',
                'general' => 'Apply',
            ],
            'relate'   => [
                'math' => 'Show where', 'cs' => 'Find a real-life use of',
                'science' => 'Connect', 'language' => 'Connect',
                'social' => 'Connect', 'islamiyat' => 'Connect',
                'general' => 'Connect',
            ],
        ];
        return ($map[$intent][$kind] ?? $map[$intent]['general'] ?? 'Discuss');
    }

    /** Subject-appropriate "Teaching Aids" row. */
    protected function teachingAids(string $kind, string $subject): array
    {
        $base = ['Whiteboard', 'Marker'];
        return match ($kind) {
            'cs'        => array_merge($base, ['Computer / Laptop', "{$subject} Textbook"]),
            'science'   => array_merge($base, ['Chart / Diagram', 'Lab Apparatus (if available)', "{$subject} Textbook"]),
            'math'      => array_merge($base, ['Calculator', 'Ruler / Geometry Set', "{$subject} Textbook"]),
            'language'  => array_merge($base, ['Reading passage / Storybook', "{$subject} Textbook"]),
            'social'    => array_merge($base, ['Map / Atlas', 'Timeline chart', "{$subject} Textbook"]),
            'islamiyat' => array_merge($base, ["{$subject} Textbook", 'Quran / Hadith reference']),
            default     => array_merge($base, ['Chart / Visual Aid', "{$subject} Textbook"]),
        };
    }

    /** Teaching methods vary slightly per subject. */
    protected function teachingMethods(string $kind): array
    {
        return match ($kind) {
            'math', 'cs' => ['Lecture Method', 'Question & Answer', 'Worked Examples', 'Pair Practice'],
            'science'    => ['Lecture Method', 'Demonstration', 'Question & Answer', 'Group Activity'],
            'language'   => ['Reading Aloud', 'Question & Answer', 'Group Discussion', 'Recitation'],
            'social'     => ['Lecture Method', 'Discussion', 'Story-telling', 'Group Activity'],
            'islamiyat'  => ['Recitation', 'Explanation', 'Question & Answer', 'Group Discussion'],
            default      => ['Lecture Method', 'Question & Answer', 'Group Activity', 'Demonstration'],
        };
    }

    /** A topic-specific class activity sentence. */
    protected function classActivity(string $kind, string $topic, string $example = ''): string
    {
        $ex = $example ? " For instance: «{$example}»" : '';
        return match ($kind) {
            'math'      => "Students will solve 2–3 short {$topic} problems on the board in pairs and explain their steps to the class.{$ex}",
            'cs'        => "Students will write a step-by-step example of {$topic} in their notebooks and trace through it on the board.{$ex}",
            'science'   => "Students will draw and label a diagram of {$topic}, then explain it to the class.{$ex}",
            'language'  => "Students will read a short passage on {$topic} and answer 3 quick comprehension questions in pairs.{$ex}",
            'social'    => "Students will work in groups to list the key facts about {$topic} and present them to the class.{$ex}",
            'islamiyat' => "Students will recite / discuss key points related to {$topic} in pairs and share with the class.{$ex}",
            default     => "Students will work in pairs to apply {$topic} to a short example and explain their reasoning.{$ex}",
        };
    }

    /** Fallback characteristics when Wikipedia returns nothing usable. */
    protected function fallbackCharacteristics(string $kind, string $t): array
    {
        return match ($kind) {
            'math' => [
                "Has a clear definition / formula",
                "Follows a fixed set of steps or rules",
                "Produces a verifiable answer",
                "Can be checked using a worked example",
            ],
            'cs' => [
                "Consists of clear, logical steps",
                "Has a finite number of steps",
                "Produces output for a given input",
                "Can be written and tested",
            ],
            'science' => [
                "Observable in the natural world",
                "Has identifiable parts / stages",
                "Can be demonstrated with an experiment",
                "Follows predictable rules",
            ],
            'language' => [
                "Has recognisable form and structure",
                "Follows grammar / spelling rules",
                "Carries a clear meaning in context",
            ],
            'social' => [
                "Tied to a specific time and place",
                "Has identifiable causes and effects",
                "Affects people's daily lives",
            ],
            default => [
                "Has a clear definition",
                "Made up of identifiable parts or steps",
                "Can be illustrated with an example",
                "Has a practical use",
            ],
        };
    }

    /** Fallback examples scaffold when Wikipedia returned nothing usable. */
    protected function fallbackExamples(string $kind, string $t): array
    {
        return match ($kind) {
            'math'  => ["Worked example: solving a {$t} step by step", "Practice example: a {$t} problem from the textbook", "Quick example for student practice"],
            'cs'    => ["Algorithm for {$t}: list the steps in order", "Pseudo-code example on the board", "Real-life {$t}: e.g. making tea step by step"],
            'science' => ["Diagram example of {$t}", "A real-life example students can observe", "An experiment that demonstrates {$t}"],
            default => ["A simple textbook example of {$t}", "A demonstrated example on the board", "A real-life example students see daily"],
        };
    }

    /** Homework that's specific to the subject + uses Wikipedia facts when present. */
    protected function homework(string $kind, string $t, string $s, bool $hasFacts, string $firstChar, string $firstExample, string $firstApp): array
    {
        $base = match ($kind) {
            'math' => [
                "Solve 5 problems on “{$t}” from the textbook exercise.",
                "Show the steps of one worked example of “{$t}” in your notebook.",
                "Make 2 of your own “{$t}” problems and solve them.",
            ],
            'cs' => [
                "Write a step-by-step algorithm for “{$t}”.",
                "Give 3 real-life examples of “{$t}” and explain each in one line.",
                "List the main characteristics of “{$t}” from the textbook.",
            ],
            'science' => [
                "Draw and label a neat diagram of “{$t}” in your notebook.",
                "Write 5 sentences explaining the process / parts of “{$t}”.",
                "Find one real-life example of “{$t}” around you and describe it.",
            ],
            'language' => [
                "Read the textbook section on “{$t}” and answer the end-of-lesson questions.",
                "Write a short paragraph (5–6 lines) using “{$t}”.",
                "Find five new words from the lesson and write their meanings.",
            ],
            'social' => [
                "Write a short note (5–7 lines) on “{$t}”.",
                "List 3 causes and 3 effects related to “{$t}”.",
                "Find one current event linked to “{$t}” and write 2 lines about it.",
            ],
            'islamiyat' => [
                "Memorise the key teachings related to “{$t}”.",
                "Write 3 lessons that we can apply from “{$t}” in daily life.",
                "Read the textbook section on “{$t}” and prepare 2 questions.",
            ],
            default => [
                "Read the textbook section on “{$t}” and write a 5-sentence summary.",
                "List 3 important points about “{$t}” discussed in class.",
                "Give one real-life example of “{$t}” and explain it briefly.",
            ],
        };

        // If Wikipedia gave us a concrete application sentence, replace the
        // last homework item with one that references it.
        if ($firstApp) {
            $base[count($base) - 1] = "Read this and write 2–3 sentences in your own words: «{$firstApp}»";
        }
        return $base;
    }

    /** Trim a long sentence without breaking words. */
    protected function shortDef(string $s, int $max = 220): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $s));
        if (mb_strlen($s) <= $max) {
            return $s;
        }
        return rtrim(mb_substr($s, 0, $max), " ,.;:-").'…';
    }
}
