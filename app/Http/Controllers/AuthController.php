<?php

namespace App\Http\Controllers;

use Storage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\AuthLoginRequest;
use App\Http\Requests\AuthRegisterRequest;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(AuthRegisterRequest $request)
    {
        try {
            $user = User::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'password' => Hash::make($request->validated('password')),
            ]);

            $token = $user->createToken(
                'auth_token',
                ['*'],
                now()->addDays(30)
            )->plainTextToken;


            return response()->json([
                'success' => true,
                'message' => 'registration successful',
                'data' => [
                    'user' => new UserResource($user),
                    'token' => $token,
                    'token_type' => 'Bearer',
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل التسجيل',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function login(AuthLoginRequest $request)
    {
        if (!Auth::attempt($request->validated('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['credentials are incorrect'],
                'password' => ['credentials are incorrect'],
            ]);
        }

        $user = Auth::user();

        if (isset($user->is_active) && !$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['account is not active. please contact the administration'],
            ]);
        }

        if ($request->boolean('revoke_other_tokens')) {
            $user->tokens()->delete();
        }

        $deviceName = $request->header('User-Agent') ?? 'unknown-device';
        $token = $user->createToken(
            $deviceName,
            ['*'],
            now()->addDays(30)
        )->plainTextToken;


        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل الدخول بنجاح',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
            ],
        ]);
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الخروج بنجاح',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل تسجيل الخروج',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
