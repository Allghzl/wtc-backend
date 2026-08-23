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

    /**
     * Direct download attachment file (streams from S3 through Laravel).
     * Alternative to the file() method for simpler frontend implementation.
     */
    public function download(Request $request, Attachment $attachment)
    {
        $user = $request->user();

        // Authorization: Check if user has access to the parent resource
        // For lesson and challenge attachments, all authenticated users can access
        // Additional authorization can be added here if needed

        $disk = \Illuminate\Support\Facades\Storage::disk('s3');

        if (!$disk->exists($attachment->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Attachment file not found in storage.',
            ], 404);
        }

        // Stream file directly with proper download headers
        return $disk->download($attachment->file_path, $attachment->file_name);
    }
}
