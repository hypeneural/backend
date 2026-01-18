<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class HealthCheckCommand extends Command
{
    protected $signature = 'health:check 
        {--full : Run full diagnostic including database tables}
        {--fix : Attempt to fix common issues}';

    protected $description = 'Check API health and diagnose common issues';

    protected array $requiredTables = [
        'users',
        'cities',
        'categories',
        'places',
        'experiences',
        'families',
        'family_users',
        'dependents',
        'favorites',
        'favorite_lists',
        'plans',
        'notifications',
        'collections',
        'collection_items',
        'roles',
        'permissions',
    ];

    public function handle(): int
    {
        $this->info('🏥 API Health Check');
        $this->line('==================');

        $errors = [];

        // 1. Check database connection
        $this->info("\n📊 Database Connection:");
        try {
            DB::connection()->getPdo();
            $this->line("  ✓ Database connected");
        } catch (\Exception $e) {
            $this->error("  ✗ Database connection failed: " . $e->getMessage());
            $errors[] = 'database_connection';
        }

        // 2. Check required tables
        if ($this->option('full')) {
            $this->info("\n📋 Required Tables:");
            foreach ($this->requiredTables as $table) {
                if (Schema::hasTable($table)) {
                    $count = DB::table($table)->count();
                    $this->line("  ✓ {$table} ({$count} records)");
                } else {
                    $this->error("  ✗ {$table} - MISSING");
                    $errors[] = "table_{$table}";
                }
            }
        }

        // 3. Check cache driver
        $this->info("\n💾 Cache Configuration:");
        $driver = config('cache.default');
        $this->line("  Driver: {$driver}");

        if ($driver === 'file' || $driver === 'array') {
            $this->warn("  ⚠ Cache tags NOT supported (need redis/memcached)");
            $this->line("  → CacheHelper will use fallback mode");
        } else {
            $this->line("  ✓ Cache tags supported");
        }

        // 4. Check Redis (if used)
        $this->info("\n🔴 Redis Status:");
        try {
            $redis = config('database.redis.default');
            if ($redis) {
                \Illuminate\Support\Facades\Redis::ping();
                $this->line("  ✓ Redis connected");
            } else {
                $this->line("  ⊘ Redis not configured");
            }
        } catch (\Exception $e) {
            $this->warn("  ⚠ Redis not available: " . $e->getMessage());
            $this->line("  → CacheHelper will use graceful fallback");
        }

        // 5. Check JWT configuration
        $this->info("\n🔐 JWT Configuration:");
        $secret = config('jwt.secret');
        if (empty($secret)) {
            $this->error("  ✗ JWT_SECRET not set!");
            $errors[] = 'jwt_secret';
            $this->line("  Run: php artisan jwt:secret");
        } else {
            $this->line("  ✓ JWT secret configured");
        }

        // 6. Check WeatherAPI configuration
        $this->info("\n🌤️ WeatherAPI Configuration:");
        $weatherKey = config('services.weatherapi.key');
        if (empty($weatherKey)) {
            $this->warn("  ⚠ WEATHERAPI_KEY not set (weather endpoints will fail)");
        } else {
            $this->line("  ✓ WeatherAPI key configured");
        }

        // 7. Check pending migrations
        $this->info("\n📦 Pending Migrations:");
        try {
            $pendingCount = count(app('migrator')->pendingMigrations(
                app('migrator')->getMigrationFiles(database_path('migrations'))
            ));
            if ($pendingCount > 0) {
                $this->warn("  ⚠ {$pendingCount} pending migrations");
                $this->line("  Run: php artisan migrate");
                $errors[] = 'pending_migrations';
            } else {
                $this->line("  ✓ All migrations run");
            }
        } catch (\Exception $e) {
            $this->warn("  Could not check migrations: " . $e->getMessage());
        }

        // Summary
        $this->info("\n==================");
        if (empty($errors)) {
            $this->info("✅ All checks passed!");
            return Command::SUCCESS;
        } else {
            $this->error("❌ Issues found: " . implode(', ', $errors));

            if ($this->option('fix')) {
                $this->info("\n🔧 Attempting fixes...");
                // Auto-fix logic here if needed
            }

            return Command::FAILURE;
        }
    }
}
