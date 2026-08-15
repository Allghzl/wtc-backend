<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AttachmentStoreRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Challenge;
use App\Services\AttachmentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChallengeAttachmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AttachmentService $attachmentService
    ) {}

    /**
     * Get all attachments for a challenge.
     */
    public function index(Challenge $challenge): JsonResponse
    {
        $attachments = $challenge->attachments()
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->success(
            AttachmentResource::collection($attachments),
            'Challenge attachments retrieved successfully'
        );
    }

    /**
     * Upload a new attachment to a challenge.
     */
    public function store(
        AttachmentStoreRequest $request,
        Challenge $challenge
    ): JsonResponse {
        $attachment = $this->attachmentService->uploadAttachment(
            $request->file('file'),
            $request->validated(),
            null,
            $challenge->id
        );

        return $this->success(
            new AttachmentResource($attachment),
            'Attachment uploaded successfully',
            201
        );
    }

    /**
     * Delete an attachment from a challenge.
     */
    public function destroy(
        Request $request,
        Challenge $challenge,
        Attachment $attachment
    ): JsonResponse {
        // Authorization: Only admin and teacher can delete attachments
        if (!($request->user()->hasRole('admin') || $request->user()->hasRole('teacher'))) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this attachment.',
            ], 403);
        }

        // Ensure attachment belongs to the challenge
        if ($attachment->challenge_id !== $challenge->id) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment does not belong to this challenge.',
            ], 404);
        }

        $this->attachmentService->deleteAttachment($attachment);

        return $this->success(
            null,
            'Attachment deleted successfully'
        );
    }
}
