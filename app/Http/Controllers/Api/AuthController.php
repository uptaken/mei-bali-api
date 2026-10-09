<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

use App\Mail\ChangePasswordNotification;
use App\Mail\ChangeProfileNotification;

/**
 * Login/logout, profil sendiri, ganti password, dan lupa/reset password.
 * Setiap perubahan akun mengirim email pemberitahuan ke pemilik akun.
 */
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

    /** GET /api/me — data pengguna yang sedang login (dipakai frontend untuk memulihkan sesi). */
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /** PATCH /api/me — ubah nama, email, telepon sendiri; email harus unik. Mengirim email pemberitahuan perubahan profil. */
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

    /** PATCH /api/me/password — ganti password sendiri; wajib menyertakan password lama. Mengirim email pemberitahuan. */
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

    /** POST /api/forgot-password — kirim tautan reset ke email (dibatasi 5 permintaan/menit di route). */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Same answer whether or not the address is registered, so the form cannot be used to probe for accounts.
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'Jika email terdaftar, tautan reset password telah dikirim.']);
    }

    /** POST /api/reset-password — pakai token dari email untuk mengganti password; semua token login lama dicabut. */
    public function resetPassword(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($credentials, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();

            Mail::to($user->email)->send(new ChangePasswordNotification($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => ['Tautan reset tidak valid atau sudah kedaluwarsa.']]);
        }

        return response()->json(['message' => 'Password berhasil direset. Silakan masuk dengan password baru.']);
    }

    /** POST /api/logout — cabut token yang dipakai sekarang. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }
}
