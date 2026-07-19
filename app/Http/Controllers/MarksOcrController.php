<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Marks-photo OCR endpoint.
 *
 * Ships a JPEG/PNG of the handwritten "roll_no marks" page to a vision
 * LLM and returns { pairs: [{roll_no, marks}...] }.
 *
 * Provider = Google Gemini (aistudio.google.com free tier: 1,500 req/day,
 * no credit card, best-in-class handwriting accuracy). If Gemini is not
 * configured but Groq is AND Groq has a vision model on the account, we
 * fall through to that. Otherwise: actionable 503 telling the operator
 * exactly which .env key to set.
 *
 * Config precedence:
 *   1. GEMINI_API_KEY  — preferred (Google AI Studio)
 *   2. GROQ_API_KEY    — fallback (Groq free vision, if enabled on account)
 */
class MarksOcrController extends Controller
{
    public function extract(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'max:8192'],   // 8 MB
            'max_marks' => ['nullable', 'numeric', 'min:1'],
        ]);

        $file = $request->file('image');
        $bytes = file_get_contents($file->getRealPath());
        $mime  = $file->getMimeType() ?: 'image/jpeg';
        $maxMarks = $request->input('max_marks');
        $prompt = $this->buildPrompt($maxMarks);

        $geminiKey = config('services.gemini.key') ?: env('GEMINI_API_KEY');
        $groqKey   = config('services.groq.key')   ?: env('GROQ_API_KEY');

        if (!$geminiKey && !$groqKey) {
            return response()->json([
                'ok' => false,
                'error' => 'OCR provider not configured. Add GEMINI_API_KEY to your .env '
                          .'(free key at aistudio.google.com — no credit card required), '
                          .'then run `php artisan config:clear`.',
            ], 503);
        }

        // ─── Try Gemini first ───────────────────────────────────────────
        if ($geminiKey) {
            [$content, $error] = $this->callGemini($geminiKey, $prompt, $bytes, $mime);
            if ($content === null) {
                return response()->json(['ok' => false, 'error' => $error], 502);
            }
            return $this->respondWithPairs($content);
        }

        // ─── Fallback: Groq ─────────────────────────────────────────────
        [$content, $error] = $this->callGroq($groqKey, $prompt, $bytes, $mime);
        if ($content === null) {
            return response()->json(['ok' => false, 'error' => $error], 502);
        }
        return $this->respondWithPairs($content);
    }

    private function buildPrompt(?string $maxMarks): string
    {
        $maxHint = $maxMarks ? " Maximum possible mark per student is {$maxMarks}." : '';
        return <<<TXT
You are reading a photo of a handwritten table with two columns: roll number and marks.
Extract every row as JSON. Rules:
  • Output ONLY a JSON object of the form: {"pairs":[{"roll_no":"1","marks":"10"}, ...]}
  • Both roll_no and marks are strings.
  • roll_no is the integer on the left of each row.
  • marks is the number on the right of each row (may include a decimal like 20.5).
  • Skip rows that are blank or unreadable.
  • Do NOT invent rows that are not on the page. Do NOT include commentary or explanation.
  • Recognise regional handwriting: a "5" may look like an "S"; a "4" may look like a "u" or "y".{$maxHint}
TXT;
    }

    /**
     * Google Gemini call. Returns [content_string, null] on success or
     * [null, error_message] on failure.
     */
    private function callGemini(string $key, string $prompt, string $bytes, string $mime): array
    {
        $model = config('services.gemini.model') ?: env('GEMINI_MODEL', 'gemini-2.0-flash');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
        try {
            $resp = Http::timeout(45)->post($url, [
                'contents' => [[
                    'parts' => [
                        ['text' => $prompt],
                        ['inline_data' => ['mime_type' => $mime, 'data' => base64_encode($bytes)]],
                    ],
                ]],
                'generationConfig' => [
                    'temperature' => 0,
                    'responseMimeType' => 'application/json',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('marks-ocr: gemini HTTP failed', ['msg' => $e->getMessage()]);
            return [null, 'Could not reach the OCR service. Check your internet and try again.'];
        }

        if (!$resp->successful()) {
            $msg = data_get($resp->json(), 'error.message', "Provider returned HTTP {$resp->status()}.");
            Log::warning('marks-ocr: gemini upstream error', ['status' => $resp->status(), 'body' => $resp->json()]);
            return [null, "OCR service error: {$msg}"];
        }

        $content = data_get($resp->json(), 'candidates.0.content.parts.0.text', '');
        return [$content, null];
    }

    /**
     * Groq call (OpenAI-compatible schema). Fallback for when a vision
     * model is available on the account.
     */
    private function callGroq(string $key, string $prompt, string $bytes, string $mime): array
    {
        $model = config('services.groq.model') ?: env('GROQ_MODEL', 'meta-llama/llama-4-scout-17b-16e-instruct');
        $dataUrl = 'data:'.$mime.';base64,'.base64_encode($bytes);
        try {
            $resp = Http::timeout(45)
                ->withToken($key)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => $model,
                    'temperature' => 0,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [[
                        'role' => 'user',
                        'content' => [
                            ['type' => 'text', 'text' => $prompt],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                        ],
                    ]],
                ]);
        } catch (\Throwable $e) {
            Log::warning('marks-ocr: groq HTTP failed', ['msg' => $e->getMessage()]);
            return [null, 'Could not reach the OCR service. Check your internet and try again.'];
        }

        if (!$resp->successful()) {
            $msg = data_get($resp->json(), 'error.message', "Provider returned HTTP {$resp->status()}.");
            Log::warning('marks-ocr: groq upstream error', ['status' => $resp->status(), 'body' => $resp->json()]);
            return [null, "OCR service error: {$msg}"];
        }

        $content = data_get($resp->json(), 'choices.0.message.content', '');
        return [$content, null];
    }

    /**
     * Parse the model's JSON payload into a normalised pairs response.
     * Tolerant of Gemini quirks: extra trailing `}`, ```json ... ``` code
     * fences, and prose wrapped around the JSON — extracts the first
     * balanced {...} block and parses only that.
     */
    private function respondWithPairs(string $content): JsonResponse
    {
        $parsed = json_decode($content, true);
        if (!is_array($parsed) || !isset($parsed['pairs'])) {
            // Second attempt: pull out the first balanced {...} block.
            $extracted = $this->extractFirstJsonObject($content);
            if ($extracted !== null) {
                $parsed = json_decode($extracted, true);
            }
        }
        if (!is_array($parsed) || !isset($parsed['pairs']) || !is_array($parsed['pairs'])) {
            Log::warning('marks-ocr: unparseable model output', ['content' => $content]);
            return response()->json([
                'ok' => false,
                'error' => 'The photo could not be read. Try a clearer picture with better light.',
                'raw'   => $content,
            ], 200);
        }

        // Normalise + dedupe: last-wins on duplicate roll_no, since teachers
        // often overwrite a value later on the page.
        $pairs = [];
        foreach ($parsed['pairs'] as $p) {
            if (!is_array($p)) continue;
            $roll = trim((string) ($p['roll_no'] ?? ''));
            $marks = trim((string) ($p['marks'] ?? ''));
            if ($roll === '') continue;
            // Strip anything that isn't digit / dot / minus so a stray
            // decorative char in the model's answer can't slip through.
            $roll  = preg_replace('/[^\d]/', '', $roll);
            $marks = preg_replace('/[^\d.\-]/', '', $marks);
            if ($roll === '') continue;
            $pairs[$roll] = ['roll_no' => $roll, 'marks' => $marks];
        }

        return response()->json([
            'ok'    => true,
            'pairs' => array_values($pairs),
        ]);
    }

    /**
     * Walk the string, find the first `{`, then bracket-count forward
     * (skipping chars inside "..." strings + honouring backslash escapes)
     * until the matching close-brace. Returns the balanced substring or
     * null if no balanced object exists. Immune to Gemini's habit of
     * appending stray `}` or wrapping the payload in ```json ... ```.
     */
    private function extractFirstJsonObject(string $s): ?string
    {
        $start = strpos($s, '{');
        if ($start === false) return null;
        $depth = 0;
        $inString = false;
        $escape = false;
        $len = strlen($s);
        for ($i = $start; $i < $len; $i++) {
            $ch = $s[$i];
            if ($escape) { $escape = false; continue; }
            if ($ch === '\\') { $escape = true; continue; }
            if ($ch === '"') { $inString = !$inString; continue; }
            if ($inString) continue;
            if ($ch === '{') $depth++;
            elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) return substr($s, $start, $i - $start + 1);
            }
        }
        return null;
    }
}
