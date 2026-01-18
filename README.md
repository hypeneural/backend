# Bora Dia Família - API Backend

Laravel 12 REST API backend for a mobile-first PWA React app focused on family experiences.

## Stack

- **Framework**: Laravel 12
- **Database**: MariaDB 10.11+
- **Cache/Queue**: Redis (tags, rate limiting, counters)
- **Auth**: JWT (php-open-source-saver/jwt-auth) with refresh token rotation
- **Queue Worker**: Laravel Horizon (Linux production only)

## Requirements

- PHP 8.2+
- Composer
- MariaDB 10.11+
- Redis 6+
- Node.js 18+ (for frontend assets)

## Installation

```bash
# Clone and install dependencies
git clone <repository>
cd backend
composer install

# Configure environment
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# Create database
mysql -e "CREATE DATABASE boradiafamilia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"

# Run migrations and seeders
php artisan migrate
php artisan db:seed

# Start development server
php artisan serve
```

## API Endpoints

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/otp/send` | Send OTP to phone |
| POST | `/api/v1/auth/otp/verify` | Verify OTP and get tokens |
| POST | `/api/v1/auth/refresh` | Refresh access token |
| POST | `/api/v1/auth/logout` | Logout (revoke tokens) |
| GET | `/api/v1/auth/me` | Get current user |

### Content
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/home` | Unified home endpoint |
| GET | `/api/v1/experiences/search` | Search with facets |
| GET | `/api/v1/experiences/{id}` | Experience details |
| GET | `/api/v1/map/experiences` | Map view with bbox |

### User Actions
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/favorites` | List favorites |
| POST | `/api/v1/favorites` | Save experience |
| DELETE | `/api/v1/favorites/{id}` | Remove favorite |
| GET | `/api/v1/family` | Get family info |
| POST | `/api/v1/family/invite` | Generate invite code |
| POST | `/api/v1/family/join` | Join with invite code |
| POST | `/api/v1/share-links` | Generate share link |

## Architecture

```
app/
├── Actions/Auth/          # Auth actions (SendOtp, VerifyOtp, etc.)
├── Http/
│   ├── Controllers/V1/    # Versioned API controllers
│   └── Requests/          # Form request validation
├── Jobs/                  # Queue jobs
│   ├── UpdateExperienceSearchJob.php
│   ├── AggregateMetricsJob.php
│   ├── CalculateTrendingJob.php
│   └── QualifyReferralJob.php
├── Models/                # Eloquent models (37 total)
├── Policies/              # Authorization policies
├── Services/              # Business logic services
└── Support/               # Helpers (CursorPaginator)
```

## Key Features

### Read Model (experience_search)
Denormalized table for ultra-fast search queries with bitmasks for filtering:
- `age_tags_mask`: baby=1, toddler=2, kid=4, teen=8, all=16
- `weather_mask`: sun=1, rain=2, any=4
- `practical_mask`: parking=1, bathroom=2, food=4, stroller=8, etc.

### Cursor Pagination
All list endpoints use cursor-based pagination:
```json
{
  "meta": {
    "next_cursor": "eyJzY29yZSI6ODUuMiwiaWQiOiI1NjcifQ==",
    "has_more": true
  }
}
```

### Cache Strategy
- Redis cache with tags for invalidation
- City data cached (2 min)
- Experience details cached (10 min) with ETag
- Facets cached per city+filter hash

### Rate Limiting
- OTP send: 1/min per phone, 5/min per IP
- OTP verify: 5 attempts/5 min
- API general: 60/min
- Search: 30/min
- Writes: 20/min

## Jobs & Scheduler

| Job | Schedule | Description |
|-----|----------|-------------|
| `AggregateMetricsJob` | Every minute | Flush Redis counters to DB |
| `UpdateExperienceSearchJob` | Every minute | Sync dirty experiences to read model |
| `CalculateTrendingJob` | Every 5 minutes | Calculate trending per city |

Run scheduler:
```bash
php artisan schedule:work
```

## Testing

```bash
# Run all tests
php artisan test

# Run specific tests
php artisan test --filter=AuthTest
```

## Production Deployment

1. Set `APP_ENV=production` and `APP_DEBUG=false`
2. Configure Redis for cache, queue, and session
3. Run migrations: `php artisan migrate --force`
4. Cache config: `php artisan config:cache && php artisan route:cache`
5. Start Horizon: `php artisan horizon`
6. Start scheduler: `* * * * * php artisan schedule:run`

## Response Format

All endpoints return consistent JSON:
```json
{
  "data": { ... },
  "meta": { "success": true, ... },
  "errors": null
}
```

## License

Proprietary - Bora Dia Família
