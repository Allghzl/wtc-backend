<?php

namespace App\Http\Controllers\Api;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserLoginRequest;
use App\Http\Requests\UserRegisterRequest;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuthService $auth,
    ) {}

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
