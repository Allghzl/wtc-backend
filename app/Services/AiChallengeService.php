<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\Module;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChallengeService
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;

    public function __construct()
    {
        $this->apiKey  = config('services.pinat_ai.api_key');
        $this->baseUrl = rtrim(config('services.pinat_ai.base_url'), '/');
        $this->model   = config('services.pinat_ai.model');
    }

    /*
    |--------------------------------------------------------------------------
    | Public Entry Points
    |--------------------------------------------------------------------------
    */

    /**
     * Generate challenge questions from a single lesson's content.
     */
    public function generateFromLesson(Lesson $lesson, array $config): array
    {
        $context = $this->buildLessonContext($lesson);
        return $this->generate($context, $config);
    }

    /**
     * Generate challenge questions from all lessons inside a module.
     */
    public function generateFromModule(Module $module, array $config): array
    {
        $module->loadMissing('lessons');
        $context = $this->buildModuleContext($module);
        return $this->generate($context, $config);
    }

    /*
    |--------------------------------------------------------------------------
    | Context Builders
    |--------------------------------------------------------------------------
    */

    private function buildLessonContext(Lesson $lesson): string
    {
        $parts = [];
        $parts[] = "Judul Lesson: {$lesson->title}";

        if (!empty($lesson->content)) {
            $plainContent = $this->extractPlainText($lesson->content);
            if (!empty($plainContent)) {
                $parts[] = "Konten Lesson:\n{$plainContent}";
            }
        }

        return implode("\n\n", $parts);
    }

    private function buildModuleContext(Module $module): string
    {
        $parts = [];
        $parts[] = "Judul Module: {$module->title}";

        $lessons = $module->lessons()->orderBy('order')->get();

        if ($lessons->isEmpty()) {
            throw new Exception('Module tidak memiliki lesson. Tambahkan lesson terlebih dahulu.');
        }

        $parts[] = "Jumlah Lesson: {$lessons->count()}";

        foreach ($lessons as $index => $lesson) {
            $no = $index + 1;
            $section = "=== Lesson {$no}: {$lesson->title} ===";

            if (!empty($lesson->content)) {
                $plainContent = $this->extractPlainText($lesson->content);
                if (!empty($plainContent)) {
                    if (strlen($plainContent) > 2000) {
                        $plainContent = substr($plainContent, 0, 2000) . '...';
                    }
                    $section .= "\n{$plainContent}";
                }
            }

            $parts[] = $section;
        }

        return implode("\n\n", $parts);
    }

    /**
     * Extract readable plain text from content — handles Lexical JSON, HTML, or plain text.
     */
    private function extractPlainText(string $content): string
    {
        $content = trim($content);

        if (empty($content)) {
            return '';
        }

        // Try Lexical JSON first
        $decoded = json_decode($content, true);

        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['root'])) {
            return trim($this->extractFromLexicalNode($decoded['root']));
        }

        // Fallback: strip HTML tags
        return trim(strip_tags($content));
    }

    /**
     * Recursively extract text from a Lexical editor node tree.
     */
    private function extractFromLexicalNode(array $node): string
    {
        $text = '';

        // Leaf text node
        if (isset($node['text'])) {
            $text .= $node['text'];
        }

        // Recurse into children
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                $text .= $this->extractFromLexicalNode($child);
            }

            // Add newline after block-level nodes
            $blockTypes = ['paragraph', 'heading', 'listitem', 'quote', 'code'];
            if (isset($node['type']) && in_array($node['type'], $blockTypes)) {
                $text .= "\n";
            }
        }

        return $text;
    }

    /*
    |--------------------------------------------------------------------------
    | Core Generation Logic
    |--------------------------------------------------------------------------
    */

    private function generate(string $context, array $config): array
    {
        $type       = $config['type'];       // multiple_choice | essay | mixed
        $difficulty = $config['difficulty']; // easy | medium | hard
        $maxScore   = (int) $config['max_score'];
        $language   = $config['language'] ?? 'id';
        $mcqCount   = (int) ($config['mcq_count'] ?? 0);
        $essayCount = (int) ($config['essay_count'] ?? 0);

        // Validate context isn't empty
        if (empty(trim($context))) {
            throw new Exception('Konten tidak cukup untuk generate challenge. Pastikan lesson memiliki konten.');
        }

        $prompt   = $this->buildPrompt($context, $type, $difficulty, $language, $mcqCount, $essayCount, $maxScore);
        $response = $this->callApi($prompt);
        $parsed   = $this->parseResponse($response);

        // Recalculate scores to match frontend logic exactly
        $parsed['questions'] = $this->applyScores($parsed['questions'], $type, $maxScore, $mcqCount, $essayCount);

        return $parsed;
    }

    /*
    |--------------------------------------------------------------------------
    | Prompt Engineering
    |--------------------------------------------------------------------------
    */

    private function buildPrompt(
        string $context,
        string $type,
        string $difficulty,
        string $language,
        int $mcqCount,
        int $essayCount,
        int $maxScore
    ): string {
        $langInstruction = $language === 'id'
            ? 'Gunakan Bahasa Indonesia yang baik dan benar.'
            : 'Use proper English.';

        $difficultyDesc = match ($difficulty) {
            'easy'   => 'mudah, cocok untuk pemula',
            'medium' => 'menengah, membutuhkan pemahaman yang cukup',
            'hard'   => 'sulit, membutuhkan pemahaman mendalam dan analitis',
            default  => 'menengah',
        };

        $questionSpec = $this->buildQuestionSpec($type, $mcqCount, $essayCount);
        $formatSpec   = $this->buildFormatSpec($type, $mcqCount, $essayCount);

        // Detect thin content — use topic-only prompt (no lesson context at all)
        $plainContext  = strip_tags($context);
        $isThinContent = strlen($plainContext) < 800;

        if ($isThinContent) {
            // Extract topic from context (first non-empty line is usually the title)
            $lines = array_filter(explode("\n", $plainContext), fn($l) => trim($l) !== '');
            $topic = trim(reset($lines) ?: $plainContext);
            // Strip common prefixes like "Judul Lesson:", "Judul Module:"
            $topic = preg_replace('/^(Judul Lesson|Judul Module|Judul):\s*/i', '', $topic);

            return <<<PROMPT
Kamu adalah asisten pendidik. Buatkan soal ujian tentang topik berikut:

Topik: {$topic}

Spesifikasi:
- Bahasa: {$langInstruction}
- Tingkat kesulitan: {$difficultyDesc}
- {$questionSpec}

Langsung kembalikan JSON dalam format berikut, tanpa komentar, tanpa penjelasan:

```json
{$formatSpec}
```
PROMPT;
        }

        return <<<PROMPT
Kamu adalah asisten pendidik yang bertugas membuat soal ujian berkualitas tinggi.

{$langInstruction}

MATERI YANG DIAJARKAN:
{$context}

INSTRUKSI PENTING:
Buatkan soal yang menguji pemahaman siswa terhadap **KONSEP DAN PENGETAHUAN** yang ada di dalam materi di atas.
- Tingkat kesulitan: {$difficultyDesc}
- {$questionSpec}

LARANGAN KERAS:
- JANGAN membuat soal tentang struktur lesson (berapa learning objective, apa tujuan lesson, dsb.)
- JANGAN membuat soal tentang metadata (judul lesson, urutan lesson, dsb.)
- SEMUA soal HARUS menguji pemahaman substantif tentang topik yang diajarkan

FORMAT RESPONS:
{$formatSpec}

PENTING:
- Kembalikan HANYA JSON valid di dalam ```json ... ```, tidak ada teks lain
- Field "answer" untuk MCQ harus berupa huruf kapital: "A", "B", "C", atau "D"
- Field "options" harus berisi tepat 4 pilihan (index 0=A, 1=B, 2=C, 3=D)
- Field "rubric" untuk essay harus berisi kriteria penilaian yang jelas
- Field "score" isi dengan 0
PROMPT;
    }

    private function buildQuestionSpec(string $type, int $mcqCount, int $essayCount): string
    {
        return match ($type) {
            'multiple_choice' => "Jumlah soal: {$mcqCount} soal pilihan ganda (MCQ)",
            'essay'           => "Jumlah soal: {$essayCount} soal essay",
            'mixed'           => "Jumlah soal: {$mcqCount} soal MCQ dan {$essayCount} soal essay",
            default           => '',
        };
    }

    private function buildFormatSpec(string $type, int $mcqCount, int $essayCount): string
    {
        $mcqExample = <<<JSON
        {
          "type": "multiple_choice",
          "question": "Pertanyaan MCQ di sini?",
          "options": ["Pilihan A", "Pilihan B", "Pilihan C", "Pilihan D"],
          "answer": "A",
          "score": 0
        }
JSON;

        $essayExample = <<<JSON
        {
          "type": "essay",
          "question": "Pertanyaan essay di sini?",
          "rubric": "Kriteria penilaian: ...",
          "score": 0
        }
JSON;

        $questionsJson = match ($type) {
            'multiple_choice' => $this->repeatExample($mcqExample, $mcqCount),
            'essay'           => $this->repeatExample($essayExample, $essayCount),
            'mixed'           => $this->repeatExample($mcqExample, $mcqCount) . ",\n" . $this->repeatExample($essayExample, $essayCount),
            default           => '',
        };

        return <<<JSON
{
  "title": "Judul challenge yang deskriptif",
  "content": "Deskripsi singkat tentang challenge ini (1-2 kalimat)",
  "questions": [
{$questionsJson}
  ]
}
JSON;
    }

    private function repeatExample(string $example, int $count): string
    {
        $items = array_fill(0, max(1, $count), trim($example));
        return implode(",\n", $items);
    }

    /*
    |--------------------------------------------------------------------------
    | API Call
    |--------------------------------------------------------------------------
    */

    private function callApi(string $prompt): string
    {
        if (empty($this->apiKey)) {
            throw new Exception('Pinat AI API key belum dikonfigurasi.');
        }

        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post("{$this->baseUrl}/chat/completions", [
                'model'       => $this->model,
                'messages'    => [
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 4096,
            ]);

        if (!$response->successful()) {
            Log::error('Pinat AI API error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new Exception("AI API error: HTTP {$response->status()}");
        }

        $data = $response->json();

        return $data['choices'][0]['message']['content'] ?? '';
    }

    /*
    |--------------------------------------------------------------------------
    | Response Parser
    |--------------------------------------------------------------------------
    */

    private function parseResponse(string $rawContent): array
    {
        if (empty($rawContent)) {
            throw new Exception('AI tidak mengembalikan respons.');
        }

        $content = null;

        // Strategy 1: extract content between ```json ... ``` or ``` ... ``` fences
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $rawContent, $matches)) {
            $content = trim($matches[1]);
        }

        // Strategy 2: find first { to last } in the raw content
        if (empty($content)) {
            $start = strpos($rawContent, '{');
            $end   = strrpos($rawContent, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $content = substr($rawContent, $start, $end - $start + 1);
            }
        }

        if (empty($content)) {
            throw new Exception(
                'Konten materi belum cukup untuk di-generate. ' .
                'Pastikan lesson memiliki materi yang substantif.'
            );
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Pinat AI parse error', [
                'raw'   => $rawContent,
                'error' => json_last_error_msg(),
            ]);

            throw new Exception(
                'Konten materi belum cukup untuk di-generate. ' .
                'Pastikan lesson memiliki materi yang substantif.'
            );
        }

        if (empty($decoded['questions']) || !is_array($decoded['questions'])) {
            throw new Exception(
                'AI tidak menghasilkan soal. Kemungkinan konten lesson belum cukup. ' .
                'Tambahkan materi yang lebih detail pada lesson terlebih dahulu.'
            );
        }

        return [
            'title'     => $decoded['title'] ?? 'Generated Challenge',
            'content'   => $decoded['content'] ?? '',
            'questions' => $decoded['questions'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Score Calculator (matches frontend calculate-score.ts logic)
    |--------------------------------------------------------------------------
    */

    /**
     * Re-apply scores after parsing, matching the frontend's calculation logic.
     */
    private function applyScores(array $questions, string $type, int $maxScore, int $mcqCount, int $essayCount): array
    {
        if (empty($questions)) {
            return $questions;
        }

        switch ($type) {
            case 'multiple_choice':
                $scoreEach = $mcqCount > 0 ? round($maxScore / $mcqCount, 2) : 0;
                foreach ($questions as &$q) {
                    $q['score'] = $scoreEach;
                }
                break;

            case 'essay':
                $scoreEach = $essayCount > 0 ? round($maxScore / $essayCount, 2) : 0;
                foreach ($questions as &$q) {
                    $q['score'] = $scoreEach;
                }
                break;

            case 'mixed':
                // MCQ gets 40%, essay gets 60% — same as frontend
                $mcqTotal   = $maxScore * 0.4;
                $essayTotal = $maxScore * 0.6;
                $mcqScore   = $mcqCount > 0   ? round($mcqTotal / $mcqCount, 2)     : 0;
                $essayScore = $essayCount > 0 ? round($essayTotal / $essayCount, 2) : 0;

                foreach ($questions as &$q) {
                    $q['score'] = $q['type'] === 'multiple_choice' ? $mcqScore : $essayScore;
                }
                break;
        }

        return $questions;
    }
}
