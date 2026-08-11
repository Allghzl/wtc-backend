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

            $token = $user
                ->createToken('auth')
                ->plainTextToken;

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
        $user = User::where(
            'email',
            $credentials['email']
        )->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Invalid credentials.',
            ]);
        }

        if (! Hash::check(
            $credentials['password'],
            $user->password
        )) {
            throw ValidationException::withMessages([
                'password' => 'Invalid credentials.',
            ]);
        }

        $profile = $user->profile;

        if (! $profile) {
            $profile = $this->createProfile($user);
        }

        $profile->update([
            'last_login_at' => now(),
        ]);

        $profile->refresh();
        $profile->load('roles');

        $token = $user
            ->createToken('auth')
            ->plainTextToken;

        return [
            'user' => $user,
            'profile' => $profile,
            'token' => $token,
        ];
    }

    /**
     * Sinkronisasi user dari PinatAuth.
     *
     * JWT PinatAuth diverifikasi oleh AuthController.
     * AuthController juga mengambil avatar URL dari PinatAuth.
     *
     * Method ini hanya menangani:
     * - sinkronisasi identity
     * - pembuatan profile
     * - update profile
     * - pembuatan Sanctum token WTC
     */
    public function syncPinat(object $payload): array
    {
        return DB::transaction(function () use ($payload) {

            $user = User::updateOrCreate(
                [
                    'puid' => $payload->sub,
                ],
                [
                    'name' => $payload->name ?? null,
                    'email' => $payload->email ?? null,
                    'provider' => 'pinat',

                    /*
                     * Gunakan URL avatar dari PinatAuth.
                     *
                     * Jangan fallback ke avatar_key kalau
                     * targetnya memang ingin menyimpan URL.
                     */
                    'avatar' => $payload->avatar_url ?? null,

                    'email_verified_at' => now(),
                ]
            );

            $profile = $user->profile;

            if (! $profile) {
                $profile = $this->createProfile(
                    $user,
                    $payload
                );
            } else {
                $profile->update([
                    'display_name' => $payload->name
                        ?? $profile->display_name,

                    'last_login_at' => now(),
                    'last_synced_at' => now(),
                ]);
            }

            $profile->load('roles');

            /*
             * Buat Sanctum token milik WTC.
             *
             * Setelah ini frontend menggunakan token WTC,
             * bukan lagi token JWT PinatAuth untuk request
             * endpoint WTC.
             */
            $token = $user
                ->createToken('pinat-auth')
                ->plainTextToken;

            return [
                'user' => $user->fresh(),
                'profile' => $profile->fresh('roles'),
                'token' => $token,
            ];
        });
    }

    /**
     * Membuat profile pertama kali.
     */
    public function createProfile(
        User $user,
        ?object $payload = null
    ): Profile {
        return $this->profiles->create(
            $user,
            $payload
        );
    }

    /**
     * Current authenticated user.
     */
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
