<?php

namespace App\Http\Controllers\Api;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserLoginRequest;
use App\Http\Requests\UserRegisterRequest;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuthService $auth,
        protected \App\Services\PinatJwtService $pinatJwt
    ) {}

    public function sso(Request $request)
    {
        $request->validate([
            'access_token' => [
                'required',
                'string',
            ],
        ]);

        try {
            $token = $request->input('access_token');

            $payload = $this->pinatJwt->verify($token);

            logger()->info('PinatAuth JWT verified', [
                'sub' => $payload->sub ?? null,
                'email' => $payload->email ?? null,
                'name' => $payload->name ?? null,
                'type' => $payload->type ?? null,
            ]);

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

            $result = $this->auth->syncPinat($payload);

            logger()->info('PinatAuth user synced', [
                'user_id' => $result['user']->id,
                'puid' => $result['user']->puid,
                'profile_id' => $result['profile']->id,
            ]);

            return $this->success([
                'user' => new UserResource($result['user']),
                'profile' => new ProfileResource($result['profile']),
                'token' => $result['token'],
            ], 'PinatAuth login successful.');
        } catch (\Throwable $e) {

            logger()->error('PinatAuth SSO failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error(
                app()->environment('local')
                    ? $e->getMessage()
                    : 'Invalid or expired PinatAuth token.',
                401
            );
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
                'user'    => new UserResource($result['user']),
                'profile' => new ProfileResource($result['profile']),
                'token'   => $result['token'],
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
                'user'    => new UserResource($result['user']),
                'profile' => new ProfileResource($result['profile']),
                'token'   => $result['token'],
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
        $user = $request->user()->load('profile.roles');

        return $this->success([
            'user' => new UserResource($user),
            'profile' => new ProfileResource($user->profile),
        ]);
    }
}
