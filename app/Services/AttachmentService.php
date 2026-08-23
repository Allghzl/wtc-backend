<?php

namespace App\Services;

use App\Models\Attachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AttachmentService
{
    /**
     * Upload a file to S3 and create attachment metadata.
     */
    public function uploadAttachment(
        UploadedFile $file,
        array $data,
        ?int $lessonId = null,
        ?int $challengeId = null
    ): Attachment {
        $timestamp = now()->format('Y/m/d');
        $fileName = uniqid() . '_' . $file->getClientOriginalName();
        $filePath = "attachments/{$timestamp}/{$fileName}";

        $disk = Storage::disk('s3');

        // Upload with Content-Disposition header to force download (not inline view)
        $uploaded = $disk->put($filePath, file_get_contents($file->getRealPath()), [
            'ContentDisposition' => 'attachment; filename="' . $file->getClientOriginalName() . '"',
            'ContentType' => $file->getMimeType(),
        ]);

        if (!$uploaded) {
            throw ValidationException::withMessages([
                'file' => ['Failed to upload file to storage.'],
            ]);
        }

        $attachment = Attachment::create([
            'lesson_id' => $lessonId,
            'challenge_id' => $challengeId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'file_path' => $filePath,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return $attachment;
    }

    /**
     * Generate a temporary download URL for an attachment file.
     */
    public function generateFileUrl(Attachment $attachment): array
    {
        $disk = Storage::disk('s3');

        if (!$disk->exists($attachment->file_path)) {
            throw ValidationException::withMessages([
                'file' => ['Attachment file not found in storage.'],
            ]);
        }

        $expiresAt = now()->addMinutes(10);

        $url = $disk->temporaryUrl(
            $attachment->file_path,
            $expiresAt,
            [
                'ResponseContentDisposition' => 'attachment; filename="' . $attachment->file_name . '"',
            ]
        );

        return [
            'name' => $attachment->file_name,
            'url' => $url,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Delete an attachment and its file from storage.
     */
    public function deleteAttachment(Attachment $attachment): bool
    {
        $disk = Storage::disk('s3');

        // Delete file from storage
        if ($disk->exists($attachment->file_path)) {
            $disk->delete($attachment->file_path);
        }

        // Delete attachment metadata
        return $attachment->delete();
    }
}
