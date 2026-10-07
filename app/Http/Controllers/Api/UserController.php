<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use App\Mail\UserAccountNotification;
use App\Mail\RegistrationNotification;
use App\Mail\ChangeProfileNotification;

class UserController extends Controller
{
    public function index()
    {
        return User::query()->orderBy('nama')->get();
    }

    public function store(Request $request)
    {
				$password = $data['password'] ?? Str::random(5);
        $data = $this->validated($request);
        $data['password'] = Hash::make($password);
        unset($data['password_confirmation']);
        $user = User::create($data);

        Mail::to($user->email)->send(new RegistrationNotification($user, $password));

        return response()->json($user, 201);
    }

    public function show(User $user)
    {
        return $user;
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, true);
        if (isset($data['password'])) $data['password'] = Hash::make($data['password']);
        unset($data['password_confirmation']);
        $user->update($data);

        if (isset($data['password'])) {
            Mail::to($user->email)->send(new ChangeProfileNotification($user));
        }

        return $user;
    }

    public function destroy(User $user)
    {
        abort_if($user->is(request()->user()), 422, 'Pengguna yang sedang masuk tidak dapat dihapus.');
        $user->delete();
        return response()->json(status: 204);
    }

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
