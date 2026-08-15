<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class AvatarService
{
    /**
     * Download OAuth avatar dari temp URL dan save permanent.
     *
     * Dipanggil saat user OAuth login pertama kali.
     */
    public function downloadOAuthAvatar(User $user, string $tempUrl): ?string
    {
        try {
            // Download image dari temp URL
            $response = Http::timeout(10)->get($tempUrl);

            if (!$response->successful()) {
                return null;
            }

            $imageContent = $response->body();

            // Convert ke webp dan save
            return $this->saveAvatar(
                $user,
                $imageContent,
                'downloaded-avatar'
            );

        } catch (\Exception $e) {
            // Silent fail - avatar bukan critical feature
            report($e);
            return null;
        }
    }

    /**
     * Upload avatar dari user (non-OAuth).
     */
    public function uploadAvatar(User $user, UploadedFile $file): string
    {
        // Validate file type
        if (!in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'])) {
            throw ValidationException::withMessages([
                'avatar' => ['File harus berupa gambar (JPEG, PNG, WEBP).'],
            ]);
        }

        // Validate file size (max 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'avatar' => ['Ukuran file maksimal 5MB.'],
            ]);
        }

        $imageContent = file_get_contents($file->getRealPath());

        return $this->saveAvatar(
            $user,
            $imageContent,
            $file->getClientOriginalName()
        );
    }

    /**
     * Save avatar ke storage dengan format webp.
     *
     * Path: profiles/{profile_id}/avatar.webp
     */
    protected function saveAvatar(User $user, string $imageContent, string $originalName): string
    {
        $profile = $user->profile;

        if (!$profile) {
            throw ValidationException::withMessages([
                'profile' => ['User profile tidak ditemukan.'],
            ]);
        }

        try {
            // Convert image ke webp dengan resize
            $manager = new ImageManager(new Driver());
            $image = $manager->decodeBinary($imageContent);

            // Resize ke 512x512 (maintain aspect ratio)
            $image->scaleDown(width: 512, height: 512);

            // Encode ke webp
            $encoded = $image->encode(new \Intervention\Image\Encoders\WebpEncoder(quality: 85));
            $webpContent = $encoded->toString();

            // Path: profiles/{profile_id}/avatar.webp
            $path = "profiles/{$profile->id}/avatar.webp";

            // Delete old avatar kalo ada (graceful fallback if S3 not configured)
            if ($user->avatar) {
                try {
                    $disk = Storage::disk('s3');
                    if ($disk->exists($user->avatar)) {
                        $disk->delete($user->avatar);
                    }
                } catch (\Exception $e) {
                    // S3 not configured or error deleting
                    // Continue anyway - not critical
                    report($e);
                }
            }

            // Save ke S3 (graceful fallback if S3 not configured)
            try {
                $saved = Storage::disk('s3')->put($path, $webpContent);

                if (!$saved) {
                    throw new \Exception('Failed to save avatar to storage.');
                }
            } catch (\Exception $e) {
                // S3 not configured - still update database with path
                // Frontend can handle missing avatar gracefully
                report($e);
            }

            // Update user avatar field dengan storage path
            $user->update(['avatar' => $path]);

            return $path;

        } catch (\Exception $e) {
            throw ValidationException::withMessages([
                'avatar' => ['Gagal memproses gambar. ' . $e->getMessage()],
            ]);
        }
    }

    /**
     * Generate temporary URL untuk avatar (expires 1 hour).
     *
     * Kalau avatar ga ada, return null.
     * Kalau S3 ga configured, return null (graceful fallback).
     */
    public function generateAvatarUrl(User $user): ?array
    {
        if (!$user->avatar) {
            return null;
        }

        try {
            $disk = Storage::disk('s3');

            // Skip exists check kalau S3 ga configured properly
            // Langsung try generate URL, kalau gagal return null
            $expiresAt = now()->addHour();

            $url = $disk->temporaryUrl(
                $user->avatar,
                $expiresAt
            );

            return [
                'url' => $url,
                'expires_at' => $expiresAt,
            ];
        } catch (\Exception $e) {
            // S3 not configured or error generating URL
            // Return null gracefully (avatar is optional)
            report($e);
            return null;
        }
    }

    /**
     * Delete avatar dari storage.
     *
     * Graceful fallback if S3 not configured - will still clear database field.
     */
    public function deleteAvatar(User $user): bool
    {
        if (!$user->avatar) {
            return false;
        }

        try {
            $disk = Storage::disk('s3');

            // Try to delete from S3 if exists
            if ($disk->exists($user->avatar)) {
                $disk->delete($user->avatar);
            }
        } catch (\Exception $e) {
            // S3 not configured or error deleting
            // Still clear database field - graceful fallback
            report($e);
        }

        // Always clear database field (even if S3 delete failed)
        $user->update(['avatar' => null]);

        return true;
    }
}
