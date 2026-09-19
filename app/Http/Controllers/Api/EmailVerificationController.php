<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\EmailVerificationService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmailVerificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected EmailVerificationService $verification,
    ) {}

    /**
     * Verify email using the token from the link.
     *
     * GET /api/email/verify?token=...
     */
    public function verify(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string', 'size:64'],
        ]);

        try {
            $user = $this->verification->verify($request->input('token'));

            return $this->success([
                'user' => new UserResource($user),
            ], 'Email verified successfully.');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }

    /**
     * Resend verification email to the authenticated user.
     *
     * POST /api/email/verify/resend
     */
    public function resend(Request $request)
    {
        try {
            $this->verification->resend($request->user());

            return $this->success(null, 'Verification email sent.');
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422);
        }
    }
}
