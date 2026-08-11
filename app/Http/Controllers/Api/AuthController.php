<?php

namespace App\Http\Controllers\Api;

use App\Events\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserLoginRequest;
use App\Http\Requests\UserRegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Services\PinatJwtService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuthService $auth,
        protected PinatJwtService $pinatJwt,
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
        $data = $request->validated();

        if (User::where('email', $data['email'])->exists()) {
            return $this->error('User with this email already exists.', 409);
        }

        $user = User::create([
            'study_class_id' => $data['study_class_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
        ]);

        UserRegistered::dispatch($user);

        return $this->success(
            new UserResource($user),
            'User registered successfully.',
            201
        );
    }

    public function login(UserLoginRequest $request)
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return $this->error('Invalid email or password.', 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Login successful.');
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully.');
    }
}
