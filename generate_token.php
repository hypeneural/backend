<?php
/**
 * Token Generator Script for Frontend Team
 * 
 * Run: php generate_token.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Family;
use App\Models\FamilyUser;
use App\Models\RefreshToken;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

// Create or get test user
$phone = '+5511999999999';
$user = User::firstOrCreate(
    ['phone' => $phone],
    [
        'id' => Str::uuid(),
        'name' => 'Frontend Team',
        'is_verified' => true,
        'onboarding_completed' => true,
    ]
);

echo "User ID: " . $user->id . "\n";

// Create family if not exists
$existingFamily = $user->families()->first();
if (!$existingFamily) {
    $family = Family::create([
        'id' => Str::uuid(),
        'name' => 'Frontend Test Family',
        'type' => 'family'
    ]);

    FamilyUser::create([
        'family_id' => $family->id,
        'user_id' => $user->id,
        'role' => 'owner',
        'status' => 'active',
        'joined_at' => now()
    ]);
} else {
    $family = $existingFamily;
}

echo "Family ID: " . $family->id . "\n";

// Generate JWT token using JWTAuth facade
$token = JWTAuth::fromUser($user);
$ttl = config('jwt.ttl', 60);

// Generate refresh token
$refreshToken = Str::random(64);
RefreshToken::create([
    'id' => Str::uuid(),
    'user_id' => $user->id,
    'token_hash' => hash('sha256', $refreshToken),
    'device_name' => 'Frontend Team Device',
    'ip_address' => '127.0.0.1',
    'expires_at' => now()->addDays(14),
]);

echo "\n";
echo "===========================================\n";
echo "  FRONTEND TEAM CREDENTIALS\n";
echo "===========================================\n";
echo "\n";
echo "Phone: " . $phone . "\n";
echo "User ID: " . $user->id . "\n";
echo "Family ID: " . $family->id . "\n";
echo "\n";
echo "ACCESS TOKEN (expires in {$ttl} min):\n";
echo $token . "\n";
echo "\n";
echo "REFRESH TOKEN (expires in 14 days):\n";
echo $refreshToken . "\n";
echo "\n";
