<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachmentStoreRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Lesson;
use App\Services\AttachmentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonAttachmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AttachmentService $attachmentService
    ) {}

    /**
     * Get all attachments for a lesson.
     */
    public function index(Lesson $lesson): JsonResponse
    {
        $attachments = $lesson->attachments()
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(
            AttachmentResource::collection($attachments),
            'Lesson attachments retrieved successfully'
        );
    }

    /**
     * Upload a new attachment to a lesson.
     */
    public function store(
        AttachmentStoreRequest $request,
        Lesson $lesson
    ): JsonResponse {
        $attachment = $this->attachmentService->uploadAttachment(
            $request->file('file'),
            $request->validated(),
            $lesson->id,
            null
        );

        return $this->success(
            new AttachmentResource($attachment),
            'Attachment uploaded successfully',
            201
        );
    }

    /**
     * Delete an attachment from a lesson.
     */
    public function destroy(
        Request $request,
        Lesson $lesson,
        Attachment $attachment
    ): JsonResponse {
        // Authorization: Only admin and teacher can delete attachments
        if (!($request->user()->hasRole('admin') || $request->user()->hasRole('teacher'))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this attachment.',
            ], 403);
        }

        // Ensure attachment belongs to the lesson
        if ($attachment->lesson_id !== $lesson->id) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment does not belong to this lesson.',
            ], 404);
        }

        $this->attachmentService->deleteAttachment($attachment);

        return $this->success(
            null,
            'Attachment deleted successfully'
        );
    }
}
