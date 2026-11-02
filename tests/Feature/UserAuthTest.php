<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receive_a_jwt(): void
    {
        $password = 'correct-horse-123';

        $response = $this->postJson('/api/auth/register', [
            'name' => '测试用户',
            'email' => $this->uniqueEmail(),
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'user' => ['id', 'name', 'email'],
            ])
            ->assertJsonPath('user.name', '测试用户');

        $user = User::where('email', $response->json('user.email'))->firstOrFail();

        $this->assertTrue(Hash::check($password, $user->password));
    }

    public function test_registration_rejects_duplicate_email_and_mismatched_password_confirmation(): void
    {
        $email = $this->uniqueEmail();

        User::create([
            'name' => '已有用户',
            'email' => $email,
            'password' => 'correct-horse-123',
        ]);

        $this->postJson('/api/auth/register', [
            'name' => '重复邮箱用户',
            'email' => $email,
            'password' => 'correct-horse-123',
            'password_confirmation' => 'correct-horse-123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->postJson('/api/auth/register', [
            'name' => '确认密码不一致',
            'email' => $this->uniqueEmail(),
            'password' => 'correct-horse-123',
            'password_confirmation' => 'different-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_user_can_login_and_wrong_password_is_rejected(): void
    {
        $password = 'correct-horse-123';
        $user = $this->createUser($password);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ])
            ->assertOk()
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'user' => ['id', 'name', 'email'],
            ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_guest_cannot_access_user_profile_or_logout(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();

        $this->patchJson('/api/auth/me', [
            'name' => '新名称',
        ])->assertUnauthorized();

        $this->patchJson('/api/auth/me/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnauthorized();

        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }

    public function test_authenticated_user_can_view_and_update_profile(): void
    {
        [$user, $token] = $this->createUserAndToken();

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', $user->name);

        $this->withToken($token)
            ->patchJson('/api/auth/me', [
                'name' => '修改后的名字',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', '修改后的名字');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => '修改后的名字',
        ]);
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $currentPassword = 'current-password-123';
        $newPassword = 'new-password-456';
        [$user, $token] = $this->createUserAndToken($currentPassword);

        $this->withToken($token)
            ->patchJson('/api/auth/me/password', [
                'current_password' => $currentPassword,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ])
            ->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check($newPassword, $user->password));

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $newPassword,
        ])->assertOk();
    }

    public function test_authenticated_user_can_logout_and_token_becomes_invalid(): void
    {
        [, $token] = $this->createUserAndToken();

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    private function createUserAndToken(string $password = 'correct-horse-123'): array
    {
        $user = $this->createUser($password);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertOk();

        return [$user, $response->json('access_token')];
    }

    private function createUser(string $password = 'correct-horse-123'): User
    {
        return User::create([
            'name' => '测试用户',
            'email' => $this->uniqueEmail(),
            'password' => $password,
        ]);
    }

    private function uniqueEmail(): string
    {
        return 'user-'.bin2hex(random_bytes(6)).'@example.test';
    }
}
