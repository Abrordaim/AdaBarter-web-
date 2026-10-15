<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository
    ) {}

    /**
     * Register a new user and generate a Sanctum token.
     *
     * @param array $data
     * @param string $deviceName
     * @return array{user: User, token: string}
     */
    public function register(array $data, string $deviceName = 'mobile'): array
    {
        $provinceId = $data['province_id'] ?? null;
        $regencyId = $data['regency_id'] ?? null;
        $districtId = $data['district_id'] ?? null;

        $provinceName = $data['province_name'] ?? ($provinceId ? \App\Models\Province::find($provinceId)?->name : null);
        $regencyName = $data['regency_name'] ?? ($regencyId ? \App\Models\Regency::find($regencyId)?->name : null);
        $districtName = $data['district_name'] ?? ($districtId ? \App\Models\District::find($districtId)?->name : null);

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'user',
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? ($regencyName ?? null),
            'province_id' => $provinceId,
            'regency_id' => $regencyId,
            'district_id' => $districtId,
            'province_name' => $provinceName,
            'regency_name' => $regencyName,
            'district_name' => $districtName,
            'avatar' => $data['avatar'] ?? null,
            'is_vip' => false,
            'free_post_quota' => 3,
            'bonus_post_quota' => 0,
        ];

        /** @var User $user */
        $user = $this->userRepository->create($userData);

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Authenticate a user and generate a Sanctum token.
     *
     * @param string $email
     * @param string $password
     * @param string $deviceName
     * @return array{user: User, token: string}
     * @throws ValidationException
     */
    public function login(string $email, string $password, string $deviceName = 'mobile'): array
    {
        /** @var User|null $user */
        $user = $this->userRepository->findByEmail($email);

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password yang Anda masukkan salah.'],
            ]);
        }

        // Block banned accounts from obtaining new tokens
        if ($user->isBanned()) {
            $message = 'Akun Anda telah dinonaktifkan oleh Admin AdaBarter.';
            if ($user->ban_reason) {
                $message .= ' Alasan: ' . $user->ban_reason;
            }
            throw ValidationException::withMessages([
                'email' => [$message],
            ]);
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Revoke the current access token.
     *
     * @param User $user
     * @return bool
     */
    public function logout(User $user): bool
    {
        if ($user->currentAccessToken()) {
            return (bool) $user->currentAccessToken()->delete();
        }

        return false;
    }
}
