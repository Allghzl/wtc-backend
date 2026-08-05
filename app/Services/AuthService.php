<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        protected ProfileService $profiles,
    ) {}

    /**
     * Register local account.
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {

            if (User::where('email', $data['email'])->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Email already registered.',
                ]);
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'provider' => 'local',
            ]);

            $profile = $this->createProfile($user);

            $token = $user->createToken('auth')->plainTextToken;

            $profile->load('roles');

            return [
                'user' => $user,
                'profile' => $profile,
                'token' => $token,
            ];
        });
    }

    /**
     * Login local account.
     */
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Invalid credentials.',
            ]);
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Invalid credentials.',
            ]);
        }

        $profile = $user->profile;

        if (! $profile) {
            $profile = $this->createProfile($user);
        }

        $profile->update(['last_login_at' => now()]);
        $profile->refresh();

        $token = $user->createToken('auth')->plainTextToken;

        $profile->load('roles');

        return [
            'user' => $user,
            'profile' => $profile,
            'token' => $token,
        ];
    }

    /**
     * Sinkronisasi user dari PinatAuth.
     */
    public function syncPinat(object $payload): User
    {
        return DB::transaction(function () use ($payload) {

            $user = User::updateOrCreate(
                [
                    'puid' => $payload->sub,
                ],
                [
                    'email' => $payload->email,
                    'username' => $payload->username
                        ?? explode('@', $payload->email)[0],
                    'provider' => 'pinat',
                    'avatar' => $payload->avatar_key ?? null,
                    'email_verified_at' => now(),
                ]
            );

            if (! $user->profile) {
                $this->createProfile($user, $payload);
            }

            return $user->fresh('profile');
        });
    }

    /**
     * Membuat profile pertama kali.
     */
    public function createProfile(User $user, ?object $payload = null): Profile
    {
        return $this->profiles->create($user, $payload);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        $user->load('profile.roles');

        return response()->json([
            'user' => $user,
            'profile' => $user->profile,
        ]);
    }
}
