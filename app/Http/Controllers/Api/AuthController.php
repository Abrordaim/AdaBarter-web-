<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends BaseApiController
{
    public function __construct(
        protected AuthService $authService
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->register(
                $request->validated(),
                $request->input('device_name', 'mobile')
            );

            return $this->sendResponse([
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ], 'Pendaftaran akun berhasil!', 201);
        } catch (\Exception $e) {
            return $this->sendError('Gagal mendaftar akun: ' . $e->getMessage(), [], 500);
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                $request->input('email'),
                $request->input('password'),
                $request->input('device_name', 'mobile')
            );

            return $this->sendResponse([
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ], 'Login berhasil!');
        } catch (ValidationException $e) {
            return $this->sendError('Login gagal.', $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Terjadi kesalahan pada server: ' . $e->getMessage(), [], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->sendResponse(null, 'Berhasil logout.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->sendResponse(
            new UserResource($request->user()),
            'Data profil berhasil diambil.'
        );
    }
}
