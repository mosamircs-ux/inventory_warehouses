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
    public function register(AuthRegisterRequest $request): JsonResponse
    {
        try {
            $user = User::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'password' => Hash::make($request->validated('password')),
                'phone' => $request->validated('phone'),
                'role' => $request->validated('role') ?? 'user',
            ]);

            $token = $user->createToken(
                'auth_token',
                ['*'],
                now()->addDays(30)
            )->plainTextToken;

            Log::channel('auth')->info('User Registered', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

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
            Log::error('Registration Failed', [
                'error' => $e->getMessage(),
                'email' => $request->validated('email'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل التسجيل',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if (!\Auth::attempt(['email' => $email, 'password' => $password])) {
            return response()->json([
                'success' => false,
                'message' => 'بيانات الاعتماد غير صحيحة',
            ], 401);
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

    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $request->user()->currentAccessToken()->delete();

            Log::channel('auth')->info('User Logged Out', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

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

    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $user->tokens()->delete();

            Log::channel('auth')->info('User logged out from all devices', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الخروج من جميع الأجهزة بنجاح',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل تسجيل الخروج',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function logoutOthers(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentTokenId = $request->user()->currentAccessToken()->id;

            $user->tokens()->where('id', '!=', $currentTokenId)->delete();

            Log::channel('auth')->info('User Logged Out From Other Devices', [
                'user_id' => $user->id,
                'kept_token_id' => $currentTokenId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'logged out from other devices successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'failed to logout from other devices',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ], [
            'name.required' => 'الاسم مطلوب',

        ]);

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'profile updated successfully',
            'data' => new UserResource($user),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'كلمة المرور الحالية مطلوبة',
            'new_password.required' => 'كلمة المرور الجديدة مطلوبة',
            'new_password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل',
            'new_password.confirmed' => 'تأكيد كلمة المرور غير مطابق',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'كلمة المرور الحالية غير صحيحة',
                'errors' => [
                    'current_password' => ['كلمة المرور الحالية غير صحيحة']
                ]
            ], 422);
        }

        try {
            $user->update([
                'password' => Hash::make($validated['new_password'])
            ]);

            $user->tokens()->delete();

            Log::channel('auth')->info('Password Changed', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تغيير كلمة المرور بنجاح. يرجى تسجيل الدخول مرة أخرى',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل تغيير كلمة المرور',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function devices(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $request->user()->currentAccessToken()->id;

        $tokens = $user->tokens->map(function ($token) use ($currentTokenId) {
            return [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->diffForHumans(),
                'created_at' => $token->created_at->format('Y-m-d H:i:s'),
                'is_current' => $token->id === $currentTokenId,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $tokens,
        ]);
    }

    public function revokeDevice(Request $request, int $tokenId): JsonResponse
    {
        $user = $request->user();
        $currentTokenId = $request->user()->currentAccessToken()->id;

        // منع حذف Token الحالي
        if ($tokenId === $currentTokenId) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن حذف الجهاز الحالي. استخدم logout بدلاً من ذلك',
            ], 422);
        }

        $deleted = $user->tokens()->where('id', $tokenId)->delete();

        if (!$deleted) {
            return response()->json([
                'success' => false,
                'message' => 'الجهاز غير موجود',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الجهاز بنجاح',
        ]);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentToken = $request->user()->currentAccessToken();

            $currentToken->delete();

            $newToken = $user->createToken(
                $currentToken->name,
                ['*'],
                now()->addDays(30)
            )->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث Token بنجاح',
                'data' => [
                    'token' => $newToken,
                    'token_type' => 'Bearer',
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل تحديث Token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
