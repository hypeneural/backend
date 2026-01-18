<?php

namespace Tests\Feature;

use App\Models\OtpRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_send_otp_to_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/send', [
            'phone' => '11999999999',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['message', 'expires_at'],
                'meta' => ['success'],
                'errors',
            ])
            ->assertJsonPath('meta.success', true);

        $this->assertDatabaseHas('otp_requests', [
            'phone' => '+5511999999999',
        ]);
    }

    public function test_otp_send_requires_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/send', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_can_verify_otp_and_create_user(): void
    {
        $phone = '+5511999999999';
        $code = '123456';

        // Create OTP request
        OtpRequest::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'last_sent_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '11999999999',
            'code' => $code,
            'name' => 'Test User',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'phone', 'name'],
                    'tokens' => ['access_token', 'refresh_token', 'expires_in'],
                    'is_new_user',
                ],
            ])
            ->assertJsonPath('data.is_new_user', true);

        $this->assertDatabaseHas('users', [
            'phone' => $phone,
            'name' => 'Test User',
        ]);
    }

    public function test_can_verify_otp_for_existing_user(): void
    {
        $phone = '+5511999999999';
        $code = '123456';

        // Create existing user
        User::factory()->create([
            'phone' => $phone,
            'name' => 'Existing User',
        ]);

        // Create OTP request
        OtpRequest::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'last_sent_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '11999999999',
            'code' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_new_user', false)
            ->assertJsonPath('data.user.name', 'Existing User');
    }

    public function test_invalid_otp_fails(): void
    {
        $phone = '+5511999999999';

        OtpRequest::create([
            'phone' => $phone,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'last_sent_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '11999999999',
            'code' => '999999',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('meta.success', false);
    }

    public function test_expired_otp_fails(): void
    {
        $phone = '+5511999999999';
        $code = '123456';

        OtpRequest::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->subMinutes(1),
            'attempts' => 0,
            'last_sent_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '11999999999',
            'code' => $code,
        ]);

        $response->assertStatus(400);
    }

    public function test_can_refresh_tokens(): void
    {
        $user = User::factory()->create();

        // Login first to get tokens
        $phone = $user->phone;
        $code = '123456';

        OtpRequest::create([
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'last_sent_at' => now(),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => str_replace('+55', '', $phone),
            'code' => $code,
        ]);

        $refreshToken = $loginResponse->json('data.tokens.refresh_token');

        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'tokens' => ['access_token', 'refresh_token'],
                ],
            ]);
    }

    public function test_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('data.message', 'Logged out successfully.');
    }

    public function test_can_get_me(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
        ]);

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Test User');
    }
}
