<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\UserAccountNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
        Mail::assertSent(UserAccountNotification::class, fn (UserAccountNotification $mail) =>
            $mail->hasTo($user->email)
                && $mail->event === 'password_updated'
                && ! str_contains($mail->render(), 'new-password-123')
        );
    }

    public function test_creating_a_user_sends_an_account_notification(): void
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

        Mail::assertSent(UserAccountNotification::class, fn (UserAccountNotification $mail) =>
            $mail->hasTo('new-user@example.com')
                && $mail->event === 'created'
                && str_contains($mail->render(), 'Akun Mei Bali Ops Anda telah dibuat')
        );
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

        Mail::assertSent(UserAccountNotification::class, fn (UserAccountNotification $mail) =>
            $mail->hasTo($user->email) && $mail->event === 'password_updated'
        );
    }
}