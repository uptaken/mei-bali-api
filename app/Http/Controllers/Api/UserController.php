<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

use App\Mail\ChangePasswordNotification;
use App\Mail\RegistrationNotification;
use App\Mail\ChangeProfileNotification;

/** Manajemen pengguna (khusus Super Admin, diatur di routes/api.php). */
class UserController extends Controller
{
    /** GET /api/users — semua pengguna, urut nama. */
    public function index()
    {
        return User::query()->orderBy('nama')->get();
    }

    /**
     * POST /api/users — buat pengguna. Password tidak pernah dikirim lewat email: pengguna menerima
     * tautan "Buat Kata Sandi" (token reset). Bila password kosong, diisi acak yang tidak diketahui siapa pun.
     */
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['password'] = Hash::make($data['password'] ?? Str::random(32));
        unset($data['password_confirmation']);
        $user = User::create($data);

        $token = Password::createToken($user);
        $url = config('app.frontend_url').'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->email]);

        Mail::to($user->email)->send(new RegistrationNotification($user, $url, (int) config('auth.passwords.users.expire')));

        return response()->json($user, 201);
    }

    /** GET /api/users/{user} */
    public function show(User $user)
    {
        return $user;
    }

    /** PATCH /api/users/{user} — ubah data; email pemberitahuan tergantung password ikut diubah atau tidak. */
    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, true);
        if (isset($data['password'])) $data['password'] = Hash::make($data['password']);
        unset($data['password_confirmation']);
        $user->update($data);

        if (isset($data['password'])) {
            Mail::to($user->email)->send(new ChangePasswordNotification($user));
        } else {
            Mail::to($user->email)->send(new ChangeProfileNotification($user));
        }

        return $user;
    }

    /** DELETE /api/users/{user} — tidak boleh menghapus akun yang sedang dipakai. */
    public function destroy(User $user)
    {
        abort_if($user->is(request()->user()), 422, 'Pengguna yang sedang masuk tidak dapat dihapus.');
        $user->delete();
        return response()->json(status: 204);
    }

    /** Aturan input bersama store (semua wajib) dan update (`sometimes`). Password opsional, min 8, harus dikonfirmasi. */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'nama' => [$required, 'string'],
            'email' => [$required, 'email', 'unique:users,email,' . optional($request->route('user'))->id],
            'telepon' => ['nullable', ],
            'role' => [$required, 'in:' . implode(',', array_column(UserRole::cases(), 'value'))],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
        ]);
    }
}
