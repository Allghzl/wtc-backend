<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Profile;
use App\Services\CertificateService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CertificateController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CertificateService $certificateService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Student endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * GET /student/certificates
     * List the authenticated user's certificates.
     */
    public function studentIndex(Request $request): JsonResponse
    {
        $profile = $request->user()->profile;

        if (!$profile) {
            return $this->error('Profile not found.', 404);
        }

        $certificates = Certificate::where('profile_id', $profile->id)
            ->with('track')
            ->orderByDesc('issued_at')
            ->get();

        return $this->success($certificates, 'Certificates retrieved successfully.');
    }

    /**
     * GET /student/certificates/{certificate}/download
     * Return the PDF/HTML file for the authenticated owner.
     */
    public function download(Request $request, Certificate $certificate): mixed
    {
        $profile = $request->user()->profile;

        if (!$profile || $certificate->profile_id !== $profile->id) {
            return $this->error('Unauthorized.', 403);
        }

        if (!$certificate->pdf_path || !Storage::disk('public')->exists($certificate->pdf_path)) {
            // Regenerate on-the-fly
            $this->certificateService->generatePdf($certificate);
            $certificate->refresh();
        }

        $fullPath = Storage::disk('public')->path($certificate->pdf_path);
        $mime     = str_ends_with($certificate->pdf_path, '.pdf') ? 'application/pdf' : 'text/html';

        return response()->file($fullPath, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="certificate-' . $certificate->certificate_number . '"',
        ]);
    }

    /**
     * POST /student/certificates/{certificate}/update
     * Regenerate certificate when an upgrade is available.
     */
    public function update(Request $request, Certificate $certificate): JsonResponse
    {
        $profile = $request->user()->profile;

        if (!$profile || $certificate->profile_id !== $profile->id) {
            return $this->error('Unauthorized.', 403);
        }

        if ($certificate->status !== 'update_available') {
            return $this->error('No update available for this certificate.', 422);
        }

        $updated = $this->certificateService->regenerate($certificate);

        return $this->success($updated->load('track'), 'Certificate regenerated successfully.');
    }

    /**
     * POST /student/certificates/{certificate}/feedback
     * Store feedback from the certificate recipient.
     */
    public function feedback(Request $request, Certificate $certificate): JsonResponse
    {
        $profile = $request->user()->profile;

        if (!$profile || $certificate->profile_id !== $profile->id) {
            return $this->error('Unauthorized.', 403);
        }

        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'rating'  => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        Log::info('Certificate feedback received', [
            'certificate_number' => $certificate->certificate_number,
            'profile_id'         => $profile->id,
            'rating'             => $request->input('rating'),
            'message'            => $request->input('message'),
        ]);

        return $this->success(null, 'Feedback submitted. Thank you!');
    }

    /*
    |--------------------------------------------------------------------------
    | Admin / Teacher endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * GET /admin/certificates
     * All certificates with optional filters (teacher_or_admin).
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Certificate::with(['profile', 'track']);

        if ($request->filled('profile_id')) {
            $query->where('profile_id', $request->input('profile_id'));
        }

        if ($request->filled('track_id')) {
            $query->where('track_id', $request->input('track_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage      = (int) $request->input('per_page', 20);
        $certificates = $query->orderByDesc('issued_at')->paginate($perPage);

        return $this->successWithPagination(
            $certificates->items(),
            'Certificates retrieved successfully.',
            $certificates
        );
    }

    /**
     * GET /admin/certificates/profile/{profile}
     * Certificates for a specific profile (teacher_or_admin).
     */
    public function profileCertificates(Profile $profile): JsonResponse
    {
        $certificates = Certificate::where('profile_id', $profile->id)
            ->with('track')
            ->orderByDesc('issued_at')
            ->get();

        return $this->success($certificates, 'Profile certificates retrieved successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | Public endpoint (no auth)
    |--------------------------------------------------------------------------
    */

    /**
     * GET /public/verify/{code}
     * Publicly verify a certificate by its certificate_number.
     */
    public function verify(string $code): JsonResponse
    {
        $certificate = Certificate::where('certificate_number', $code)
            ->with(['profile:id,display_name,nickname', 'track:id,title,slug'])
            ->first();

        if (!$certificate) {
            return $this->error('Certificate not found.', 404);
        }

        return $this->success([
            'certificate_number' => $certificate->certificate_number,
            'grade'              => $certificate->grade,
            'grade_score'        => $certificate->grade_score,
            'status'             => $certificate->status,
            'issued_at'          => $certificate->issued_at,
            'student'            => [
                'display_name' => $certificate->profile->display_name ?? $certificate->profile->nickname,
            ],
            'track' => [
                'title' => $certificate->track->title,
                'slug'  => $certificate->track->slug,
            ],
        ], 'Certificate verified.');
    }
}
