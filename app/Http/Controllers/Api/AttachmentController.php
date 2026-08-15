<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttachmentController extends Controller
{
    public function __construct(
        protected AttachmentService $attachmentService
    ) {}

    /**
     * Generate a temporary download URL for an attachment file.
     */
    public function file(Request $request, Attachment $attachment): JsonResponse
    {
        $user = $request->user();

        // Authorization: Check if user has access to the parent resource
        // For lesson and challenge attachments, all authenticated users can access
        // (assuming lessons and challenges are accessible to students)
        // Additional authorization can be added here if needed

        $fileData = $this->attachmentService->generateFileUrl($attachment);

        return response()->json([
            'success' => true,
            'message' => 'Attachment file URL generated successfully.',
            'data' => [
                'file' => $fileData,
            ],
        ]);
    }
}
