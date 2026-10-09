<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Mail\ForgotPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nama',
        'email',
        'password',
        'role',
        'telepon',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    /** Called by the password broker: the reset link opens the frontend's reset screen. */
    public function sendPasswordResetNotification($token): void
    {
        $url = config('app.frontend_url').'/reset-password?'.http_build_query(['token' => $token, 'email' => $this->email]);

        Mail::to($this->email)->send(new ForgotPasswordNotification($this, $url, (int) config('auth.passwords.users.expire')));
    }
}
