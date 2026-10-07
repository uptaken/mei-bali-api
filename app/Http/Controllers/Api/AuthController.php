<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use App\Mail\ChangePasswordNotification;
use App\Mail\ChangeProfileNotification;
use App\Mail\UserAccountNotification;

class AuthController extends Controller
{
    /** POST /api/login — issues a Sanctum personal access token for API clients (mobile, Postman, etc). */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Auth::validate($data)) {
            throw ValidationException::withMessages(['email' => ['Email atau kata sandi salah.']]);
        }

        return response()->json([
            'user' => $user,
            'token' => $user->createToken($request->userAgent() ?? 'api')->plainTextToken,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'telepon' => ['nullable', 'string', 'max:40'],
        ]);

        $user->update($data);

				Mail::to($user->email)->send(new ChangeProfileNotification($user));

        return response()->json($user->fresh());
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed'],
        ]);

        $user = $request->user();
        $user->update(['password' => Hash::make($data['password'])]);

        Mail::to($user->email)->send(new ChangePasswordNotification($user));

        return response()->json(['message' => 'Kata sandi berhasil diperbarui.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }
}
