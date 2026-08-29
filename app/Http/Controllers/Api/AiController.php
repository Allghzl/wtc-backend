<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateChallengeRequest;
use App\Models\Lesson;
use App\Models\Module;
use App\Services\AiChallengeService;
use App\Traits\ApiResponse;
use Exception;

class AiController extends Controller
{
    use ApiResponse;

    public function __construct(private AiChallengeService $aiService) {}

    /**
     * Generate challenge questions based on a single lesson's content.
     *
     * POST /api/lessons/{lesson}/generate-challenge
     */
    public function generateForLesson(GenerateChallengeRequest $request, Lesson $lesson)
    {
        if (empty($lesson->content)) {
            return $this->error(
                'Lesson belum memiliki konten. Tambahkan konten lesson terlebih dahulu.',
                422
            );
        }

        try {
            $result = $this->aiService->generateFromLesson($lesson, $request->validated());

            return $this->success($result, 'Challenge berhasil di-generate');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Generate challenge questions based on all lessons inside a module.
     *
     * POST /api/modules/{module}/generate-challenge
     */
    public function generateForModule(GenerateChallengeRequest $request, Module $module)
    {
        try {
            $result = $this->aiService->generateFromModule($module, $request->validated());

            return $this->success($result, 'Challenge berhasil di-generate');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
