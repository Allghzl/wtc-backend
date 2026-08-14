<?php

namespace App\Http\Controllers\Api;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserLoginRequest;
use App\Http\Requests\UserRegisterRequest;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\PinatJwtService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuthService $auth,
        protected PinatJwtService $pinatJwt,
        protected \App\Services\AvatarService $avatars,
    ) {}

    /**
     * Login / synchronize user from PinatAuth.
     */
    public function sso(Request $request)
    {
        $request->validate([
            'access_token' => [
                'required',
                'string',
            ],
        ]);

        try {
            $accessToken = $request->input('access_token');

            /*
             * -------------------------------------------------------------
             * 1. Verify PinatAuth JWT
             * -------------------------------------------------------------
             */
            $payload = $this->pinatJwt->verify($accessToken);

            if (
                ! isset($payload->sub) ||
                ! isset($payload->type) ||
                $payload->type !== 'user'
            ) {
                return $this->error(
                    'Invalid PinatAuth token.',
                    401
                );
            }

            /*
             * -------------------------------------------------------------
             * 2. Get avatar URL from PinatAuth
             * -------------------------------------------------------------
             *
             * JWT hanya membawa avatar_key.
             * Endpoint avatar PinatAuth yang menghasilkan URL avatar.
             */
            $avatarResponse = Http::withToken($accessToken)
                ->get(
                    rtrim(config('pinat-auth.url'), '/')
                        . '/api/auth/avatar'
                );

            if ($avatarResponse->successful()) {
                $avatarUrl = $avatarResponse->json('url');

                if ($avatarUrl) {
                    $payload->avatar_url = $avatarUrl;
                }
            }

            /*
             * -------------------------------------------------------------
             * 3. Sync identity + create WTC Sanctum token
             * -------------------------------------------------------------
             */
            $result = $this->auth->syncPinat($payload);

            return $this->success([
                'user' => new UserResource($result['user']),
                'profile' => new ProfileResource($result['profile']),
                'token' => $result['token'],
            ], 'PinatAuth login successful.');
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 401);
        }
    }

    /**
     * Register local account.
     */
    public function register(UserRegisterRequest $request)
    {
        try {
            $result = $this->auth->register(
                $request->validated()
            );

            UserRegistered::dispatch($result['user']);

            return $this->success([
                'user' => new UserResource($result['user']),
                'profile' => new ProfileResource($result['profile']),
                'token' => $result['token'],
            ], 'User registered successfully.', 201);
        } catch (ValidationException $e) {
            return $this->error(
                $e->getMessage(),
                422
            );
        }
    }

    /**
     * Login local account.
     */
    public function login(UserLoginRequest $request)
    {
        try {
            $result = $this->auth->login(
                $request->validated()
            );

            return $this->success([
                'user' => new UserResource($result['user']),
                'profile' => new ProfileResource($result['profile']),
                'token' => $result['token'],
            ], 'Login successful.');
        } catch (ValidationException $e) {
            return $this->error(
                'Invalid email or password.',
                401
            );
        }
    }

    /**
     * Logout current user.
     */
    public function logout(Request $request)
    {
        $request
            ->user()
            ->currentAccessToken()
            ?->delete();

        return $this->success(
            null,
            'Logged out successfully.'
        );
    }

    /**
     * Current authenticated user.
     */
    public function me(Request $request)
    {
        $user = $request
            ->user()
            ->load('profile.roles');

        return $this->success([
            'user' => new UserResource($user),
            'profile' => new ProfileResource($user->profile),
        ]);
    }

    /**
     * Upload avatar untuk user.
     */
    public function uploadAvatar(\App\Http\Requests\AvatarUploadRequest $request)
    {
        try {
            $user = $request->user();

            $this->avatars->uploadAvatar(
                $user,
                $request->file('avatar')
            );

            // Reload user untuk ambil avatar yang baru
            $user->refresh();

            return $this->success(
                new UserResource($user),
                'Avatar uploaded successfully.'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error(
                $e->getMessage(),
                422
            );
        }
    }

    /**
     * Delete avatar user.
     */
    public function deleteAvatar(Request $request)
    {
        $user = $request->user();

        $deleted = $this->avatars->deleteAvatar($user);

        if (!$deleted) {
            return $this->error(
                'Avatar not found or already deleted.',
                404
            );
        }

        // Reload user
        $user->refresh();

        return $this->success(
            new UserResource($user),
            'Avatar deleted successfully.'
        );
    }
}
