<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\ChangePasswordNotification;
use App\Mail\ForgotPasswordNotification;
use App\Mail\RegistrationNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_authenticated_user_can_update_their_own_profile_without_changing_their_role(): void
    {
        $user = User::factory()->create(['role' => UserRole::Operator->value]);

        $response = $this->actingAs($user, 'sanctum')->patchJson('/api/me', [
            'nama' => 'Updated Name',
            'email' => 'updated@example.com',
            'telepon' => '081234567890',
        ]);

        $response->assertOk()->assertJsonPath('nama', 'Updated Name')->assertJsonPath('role', UserRole::Operator->value);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'nama' => 'Updated Name',
            'email' => 'updated@example.com',
            'telepon' => '081234567890',
            'role' => UserRole::Operator->value,
        ]);
    }

    public function test_user_must_provide_the_current_password_to_change_it(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => 'current-password']);
        $this->actingAs($user, 'sanctum');

        $this->patchJson('/api/me/password', [
            'current_password' => 'incorrect-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable();

        $this->patchJson('/api/me/password', [
            'current_password' => 'current-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        Mail::assertSent(ChangePasswordNotification::class, fn (ChangePasswordNotification $mail) =>
            $mail->hasTo($user->email) && ! str_contains($mail->render(), 'new-password-123')
        );
    }

    public function test_creating_a_user_emails_a_set_password_link_not_the_password(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin->value]);
        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/users', [
            'nama' => 'New User',
            'email' => 'new-user@example.com',
            'role' => UserRole::Operator->value,
            'password' => 'initial-password',
            'password_confirmation' => 'initial-password',
        ])->assertCreated();

        // The email never carries the password: it links to the frontend's reset page with a valid token.
        Mail::assertSent(RegistrationNotification::class, function (RegistrationNotification $mail) {
            $html = $mail->render();

            return $mail->hasTo('new-user@example.com')
                && str_contains($html, 'Selamat datang, New User')
                && ! str_contains($html, 'initial-password')
                && str_contains($html, config('app.frontend_url').'/reset-password?token=')
                && str_contains($html, config('app.frontend_url').'/login');
        });
    }

    public function test_admin_password_update_sends_a_password_notification(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => UserRole::SuperAdmin->value]);
        $user = User::factory()->create();
        $this->actingAs($admin, 'sanctum');

        $this->patchJson('/api/users/' . $user->id, [
            'password' => 'updated-password',
            'password_confirmation' => 'updated-password',
        ])->assertOk();

        Mail::assertSent(ChangePasswordNotification::class, fn (ChangePasswordNotification $mail) => $mail->hasTo($user->email));
    }

    public function test_forgot_password_emails_a_reset_link_without_revealing_whether_the_address_exists(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();
        $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com'])->assertOk();

        Mail::assertSent(ForgotPasswordNotification::class, 1);
        Mail::assertSent(ForgotPasswordNotification::class, function (ForgotPasswordNotification $mail) use ($user) {
            $html = $mail->render();

            return $mail->hasTo($user->email)
                && str_starts_with($mail->resetUrl, config('app.frontend_url').'/reset-password?token=')
                && str_contains($mail->resetUrl, 'email='.urlencode($user->email))
                && str_contains($html, 'Reset Password')
                && str_contains($html, '60 menit');
        });
    }

    public function test_a_reset_token_sets_a_new_password_once_and_signs_the_user_out_everywhere(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => 'old-password']);
        $user->createToken('device');
        $token = Password::createToken($user);
        $payload = ['token' => $token, 'email' => $user->email, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'];

        $this->postJson('/api/reset-password', $payload)->assertOk();

        $this->assertTrue(Hash::check('brand-new-pass', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        Mail::assertSent(ChangePasswordNotification::class, fn (ChangePasswordNotification $mail) => $mail->hasTo($user->email));
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'brand-new-pass'])->assertOk();

        // the token is single-use
        $this->postJson('/api/reset-password', $payload)->assertUnprocessable();
    }

    public function test_a_wrong_reset_token_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);

        $this->postJson('/api/reset-password', [
            'token' => 'not-a-real-token', 'email' => $user->email, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_the_emails_use_the_shared_brand_layout(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin->value]);

        foreach ([
            new RegistrationNotification($user, 'https://app.test/reset-password?token=x', 60),
            new ChangePasswordNotification($user),
            new \App\Mail\ChangeProfileNotification($user),
            new ForgotPasswordNotification($user, 'https://app.test/reset-password?token=x', 60),
        ] as $mail) {
            $html = $mail->render();

            $this->assertStringContainsString('Mei', $html);
            $this->assertStringContainsString('linear-gradient(135deg,#fb919a,#ee727e)', $html);
            $this->assertStringContainsString('&copy; '.date('Y'), $html);
            $this->assertStringNotContainsString('<script', $html);
        }
    }
}