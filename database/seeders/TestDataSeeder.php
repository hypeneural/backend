<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Comprehensive Test Data Seeder
 * 
 * Populates all tables with realistic test data for development.
 * Respects relationships and foreign key constraints.
 * 
 * Run: php artisan db:seed --class=TestDataSeeder
 */
class TestDataSeeder extends Seeder
{
    // Store IDs for relationship matching
    protected array $userIds = [];
    protected array $familyIds = [];
    protected array $cityIds = [];
    protected array $categoryIds = [];
    protected array $experienceIds = [];
    protected array $placeIds = [];
    protected array $listIds = [];
    protected array $planIds = [];

    public function run(): void
    {
        $this->command->info('🌱 Starting comprehensive test data seeding...');

        // Order matters - respect foreign key relationships
        $this->seedRolesAndPermissions();
        $this->seedCities();
        $this->seedCategories();
        $this->seedPlaces();
        $this->seedExperiences();
        $this->seedUsers();
        $this->seedFamilies();
        $this->seedFavoriteLists();
        $this->seedFavorites();
        $this->seedPlans();
        $this->seedNotifications();
        $this->seedGamification();
        $this->seedCollections();

        $this->command->info('✅ Test data seeding complete!');
    }

    /**
     * Seed roles and permissions (Spatie)
     */
    protected function seedRolesAndPermissions(): void
    {
        $this->command->info('  → Seeding roles and permissions...');

        // Roles
        $roles = [
            ['name' => 'super-admin', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'admin', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'moderator', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'editor', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'user', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'premium', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->insertOrIgnore($role);
        }

        // Permissions
        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            // Experiences
            'experiences.view',
            'experiences.create',
            'experiences.update',
            'experiences.delete',
            'experiences.approve',
            // Reviews
            'reviews.view',
            'reviews.moderate',
            'reviews.delete',
            // Reports
            'reports.view',
            'reports.resolve',
            // Places
            'places.view',
            'places.create',
            'places.update',
            'places.delete',
            // Categories
            'categories.manage',
            // Collections
            'collections.manage',
            // Analytics
            'analytics.view',
            // Settings
            'settings.manage',
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $permission,
                'guard_name' => 'api',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Role-Permission assignments
        $rolePermissions = [
            'super-admin' => $permissions, // All permissions
            'admin' => array_filter($permissions, fn($p) => !str_contains($p, 'settings')),
            'moderator' => ['reviews.view', 'reviews.moderate', 'reviews.delete', 'reports.view', 'reports.resolve'],
            'editor' => ['experiences.view', 'experiences.create', 'experiences.update', 'places.view', 'places.create', 'places.update', 'collections.manage'],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            $role = DB::table('roles')->where('name', $roleName)->first();
            if (!$role)
                continue;

            foreach ($perms as $permName) {
                $perm = DB::table('permissions')->where('name', $permName)->first();
                if (!$perm)
                    continue;

                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $perm->id,
                    'role_id' => $role->id,
                ]);
            }
        }

        $this->command->info('    ✓ Created ' . count($roles) . ' roles and ' . count($permissions) . ' permissions');
    }

    /**
     * Seed cities
     */
    protected function seedCities(): void
    {
        $this->command->info('  → Seeding cities...');

        $cities = [
            ['name' => 'São Paulo', 'slug' => 'sao-paulo', 'state' => 'SP', 'lat' => -23.5505, 'lng' => -46.6333],
            ['name' => 'Rio de Janeiro', 'slug' => 'rio-de-janeiro', 'state' => 'RJ', 'lat' => -22.9068, 'lng' => -43.1729],
            ['name' => 'Belo Horizonte', 'slug' => 'belo-horizonte', 'state' => 'MG', 'lat' => -19.9167, 'lng' => -43.9345],
            ['name' => 'Curitiba', 'slug' => 'curitiba', 'state' => 'PR', 'lat' => -25.4284, 'lng' => -49.2733],
            ['name' => 'Porto Alegre', 'slug' => 'porto-alegre', 'state' => 'RS', 'lat' => -30.0346, 'lng' => -51.2177],
            ['name' => 'Salvador', 'slug' => 'salvador', 'state' => 'BA', 'lat' => -12.9714, 'lng' => -38.5014],
            ['name' => 'Florianópolis', 'slug' => 'florianopolis', 'state' => 'SC', 'lat' => -27.5954, 'lng' => -48.5480],
            ['name' => 'Campinas', 'slug' => 'campinas', 'state' => 'SP', 'lat' => -22.9056, 'lng' => -47.0608],
        ];

        foreach ($cities as $city) {
            $id = Str::uuid()->toString();
            $this->cityIds[] = $id;

            DB::table('cities')->insertOrIgnore([
                'id' => $id,
                'name' => $city['name'],
                'slug' => $city['slug'],
                'state' => $city['state'],
                'country' => 'BR',
                'lat' => $city['lat'],
                'lng' => $city['lng'],
                'timezone' => 'America/Sao_Paulo',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('    ✓ Created ' . count($cities) . ' cities');
    }

    /**
     * Seed categories
     */
    protected function seedCategories(): void
    {
        $this->command->info('  → Seeding categories...');

        $categories = [
            ['name' => 'Parques', 'slug' => 'parques', 'emoji' => '🌳', 'color' => '#4CAF50'],
            ['name' => 'Museus', 'slug' => 'museus', 'emoji' => '🏛️', 'color' => '#9C27B0'],
            ['name' => 'Restaurantes', 'slug' => 'restaurantes', 'emoji' => '🍽️', 'color' => '#FF5722'],
            ['name' => 'Aventura', 'slug' => 'aventura', 'emoji' => '🎢', 'color' => '#2196F3'],
            ['name' => 'Cultura', 'slug' => 'cultura', 'emoji' => '🎭', 'color' => '#E91E63'],
            ['name' => 'Esportes', 'slug' => 'esportes', 'emoji' => '⚽', 'color' => '#00BCD4'],
            ['name' => 'Natureza', 'slug' => 'natureza', 'emoji' => '🏞️', 'color' => '#8BC34A'],
            ['name' => 'Compras', 'slug' => 'compras', 'emoji' => '🛍️', 'color' => '#FF9800'],
        ];

        foreach ($categories as $cat) {
            $id = Str::uuid()->toString();
            $this->categoryIds[$cat['slug']] = $id;

            DB::table('categories')->insertOrIgnore([
                'id' => $id,
                'name' => $cat['name'],
                'slug' => $cat['slug'],
                'emoji' => $cat['emoji'],
                'color' => $cat['color'],
                'is_active' => true,
                'order' => array_search($cat, $categories) + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('    ✓ Created ' . count($categories) . ' categories');
    }

    /**
     * Seed places
     */
    protected function seedPlaces(): void
    {
        $this->command->info('  → Seeding places...');

        if (empty($this->cityIds)) {
            $this->cityIds = DB::table('cities')->pluck('id')->toArray();
        }

        $places = [
            ['name' => 'Parque Ibirapuera', 'city_idx' => 0, 'lat' => -23.5874, 'lng' => -46.6576],
            ['name' => 'MASP', 'city_idx' => 0, 'lat' => -23.5614, 'lng' => -46.6559],
            ['name' => 'Cristo Redentor', 'city_idx' => 1, 'lat' => -22.9519, 'lng' => -43.2105],
            ['name' => 'Jardim Botânico RJ', 'city_idx' => 1, 'lat' => -22.9685, 'lng' => -43.2245],
            ['name' => 'Praça da Liberdade', 'city_idx' => 2, 'lat' => -19.9322, 'lng' => -43.9381],
            ['name' => 'Jardim Botânico Curitiba', 'city_idx' => 3, 'lat' => -25.4428, 'lng' => -49.2398],
        ];

        foreach ($places as $place) {
            $id = Str::uuid()->toString();
            $this->placeIds[] = $id;

            DB::table('places')->insertOrIgnore([
                'id' => $id,
                'city_id' => $this->cityIds[$place['city_idx']] ?? $this->cityIds[0],
                'name' => $place['name'],
                'slug' => Str::slug($place['name']),
                'address' => 'Endereço exemplo, 123',
                'lat' => $place['lat'],
                'lng' => $place['lng'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('    ✓ Created ' . count($places) . ' places');
    }

    /**
     * Seed experiences
     */
    protected function seedExperiences(): void
    {
        $this->command->info('  → Seeding experiences...');

        if (empty($this->placeIds)) {
            $this->placeIds = DB::table('places')->pluck('id')->toArray();
        }

        $categoryIds = array_values($this->categoryIds);
        if (empty($categoryIds)) {
            $categoryIds = DB::table('categories')->pluck('id')->toArray();
        }

        $experiences = [
            ['title' => 'Passeio no Ibirapuera', 'mission' => 'Explore o maior parque de SP', 'price' => 'free', 'duration' => 'half'],
            ['title' => 'Visita ao MASP', 'mission' => 'Descubra as obras de arte', 'price' => 'moderate', 'duration' => 'half'],
            ['title' => 'Trilha no Cristo', 'mission' => 'Suba até o Cristo Redentor', 'price' => 'moderate', 'duration' => 'full'],
            ['title' => 'Jardim Botânico RJ', 'mission' => 'Explore a flora brasileira', 'price' => 'low', 'duration' => 'half'],
            ['title' => 'Tour na Praça da Liberdade', 'mission' => 'Conheça a história de BH', 'price' => 'free', 'duration' => 'quick'],
            ['title' => 'Jardim Botânico Curitiba', 'mission' => 'Visite a estufa icônica', 'price' => 'free', 'duration' => 'half'],
        ];

        foreach ($experiences as $idx => $exp) {
            $id = Str::uuid()->toString();
            $this->experienceIds[] = $id;

            DB::table('experiences')->insertOrIgnore([
                'id' => $id,
                'place_id' => $this->placeIds[$idx] ?? $this->placeIds[0],
                'category_id' => $categoryIds[array_rand($categoryIds)],
                'title' => $exp['title'],
                'mission_title' => $exp['mission'],
                'summary' => 'Descrição detalhada da experiência ' . $exp['title'],
                'price_level' => $exp['price'],
                'duration_bucket' => $exp['duration'],
                'status' => 'published',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('    ✓ Created ' . count($experiences) . ' experiences');
    }

    /**
     * Seed users with roles
     */
    protected function seedUsers(): void
    {
        $this->command->info('  → Seeding users...');

        $users = [
            ['name' => 'Admin Master', 'phone' => '11999990001', 'role' => 'super-admin'],
            ['name' => 'Admin', 'phone' => '11999990002', 'role' => 'admin'],
            ['name' => 'Moderador', 'phone' => '11999990003', 'role' => 'moderator'],
            ['name' => 'Editor', 'phone' => '11999990004', 'role' => 'editor'],
            ['name' => 'João Silva', 'phone' => '11999990005', 'role' => 'user'],
            ['name' => 'Maria Santos', 'phone' => '11999990006', 'role' => 'user'],
            ['name' => 'Pedro Costa', 'phone' => '11999990007', 'role' => 'premium'],
            ['name' => 'Ana Oliveira', 'phone' => '11999990008', 'role' => 'user'],
            ['name' => 'Lucas Ferreira', 'phone' => '11999990009', 'role' => 'user'],
            ['name' => 'Julia Almeida', 'phone' => '11999990010', 'role' => 'premium'],
        ];

        foreach ($users as $user) {
            $id = Str::uuid()->toString();
            $this->userIds[] = $id;

            DB::table('users')->insertOrIgnore([
                'id' => $id,
                'name' => $user['name'],
                'phone' => $user['phone'],
                'email' => Str::slug($user['name']) . '@teste.com',
                'is_verified' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Assign role
            $role = DB::table('roles')->where('name', $user['role'])->first();
            if ($role) {
                DB::table('model_has_roles')->insertOrIgnore([
                    'role_id' => $role->id,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $id,
                ]);
            }
        }

        $this->command->info('    ✓ Created ' . count($users) . ' users with roles');
    }

    /**
     * Seed families
     */
    protected function seedFamilies(): void
    {
        $this->command->info('  → Seeding families...');

        if (empty($this->userIds)) {
            $this->userIds = DB::table('users')->pluck('id')->toArray();
        }

        $families = [
            ['name' => 'Família Silva', 'owner_idx' => 4, 'members' => [5], 'dependents' => ['Luca' => 'toddler', 'Helena' => 'kid']],
            ['name' => 'Família Costa', 'owner_idx' => 6, 'members' => [7], 'dependents' => ['Miguel' => 'baby']],
            ['name' => 'Família Ferreira', 'owner_idx' => 8, 'members' => [9], 'dependents' => ['Sofia' => 'kid', 'Arthur' => 'teen']],
        ];

        foreach ($families as $family) {
            $familyId = Str::uuid()->toString();
            $this->familyIds[] = $familyId;
            $ownerId = $this->userIds[$family['owner_idx']] ?? $this->userIds[0];

            // Create family
            DB::table('families')->insertOrIgnore([
                'id' => $familyId,
                'name' => $family['name'],
                'owner_id' => $ownerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Add owner to family
            DB::table('family_users')->insertOrIgnore([
                'id' => Str::uuid()->toString(),
                'family_id' => $familyId,
                'user_id' => $ownerId,
                'role' => 'admin',
                'joined_at' => now(),
            ]);

            // Add members
            foreach ($family['members'] as $memberIdx) {
                $memberId = $this->userIds[$memberIdx] ?? null;
                if ($memberId) {
                    DB::table('family_users')->insertOrIgnore([
                        'id' => Str::uuid()->toString(),
                        'family_id' => $familyId,
                        'user_id' => $memberId,
                        'role' => 'member',
                        'joined_at' => now(),
                    ]);
                }
            }

            // Add dependents
            foreach ($family['dependents'] as $name => $ageGroup) {
                DB::table('dependents')->insertOrIgnore([
                    'id' => Str::uuid()->toString(),
                    'family_id' => $familyId,
                    'name' => $name,
                    'age_group' => $ageGroup,
                    'birthdate' => Carbon::now()->subYears(match ($ageGroup) {
                        'baby' => 1,
                        'toddler' => 3,
                        'kid' => 8,
                        'teen' => 14,
                        default => 5,
                    }),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('    ✓ Created ' . count($families) . ' families with members and dependents');
    }

    /**
     * Seed favorite lists
     */
    protected function seedFavoriteLists(): void
    {
        $this->command->info('  → Seeding favorite lists...');

        if (empty($this->familyIds)) {
            $this->familyIds = DB::table('families')->pluck('id')->toArray();
        }

        $lists = [
            ['name' => 'Favoritos', 'emoji' => '❤️', 'is_default' => true],
            ['name' => 'Para o fds', 'emoji' => '🌞', 'is_default' => false],
            ['name' => 'Dia de chuva', 'emoji' => '🌧️', 'is_default' => false],
            ['name' => 'Com o bebê', 'emoji' => '👶', 'is_default' => false],
        ];

        foreach ($this->familyIds as $familyId) {
            foreach ($lists as $list) {
                $id = Str::uuid()->toString();
                $this->listIds[] = $id;

                DB::table('favorite_lists')->insertOrIgnore([
                    'id' => $id,
                    'family_id' => $familyId,
                    'user_id' => null,
                    'name' => $list['name'],
                    'emoji' => $list['emoji'],
                    'is_default' => $list['is_default'],
                    'created_at' => now(),
                ]);
            }
        }

        $this->command->info('    ✓ Created ' . count($this->listIds) . ' favorite lists');
    }

    /**
     * Seed favorites
     */
    protected function seedFavorites(): void
    {
        $this->command->info('  → Seeding favorites...');

        if (empty($this->experienceIds)) {
            $this->experienceIds = DB::table('experiences')->pluck('id')->toArray();
        }

        $count = 0;
        foreach ($this->userIds as $idx => $userId) {
            // Skip admin users
            if ($idx < 4)
                continue;

            $familyId = $this->familyIds[($idx - 4) % count($this->familyIds)] ?? null;
            $listId = $this->listIds[($idx % count($this->listIds))] ?? null;

            // Each user saves 2-4 random experiences
            $numFavorites = rand(2, 4);
            $expIds = array_rand(array_flip($this->experienceIds), min($numFavorites, count($this->experienceIds)));

            foreach ((array) $expIds as $expId) {
                DB::table('favorites')->insertOrIgnore([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $userId,
                    'family_id' => $familyId,
                    'experience_id' => $expId,
                    'list_id' => $listId,
                    'scope' => $familyId ? 'family' : 'user',
                    'saved_at' => now(),
                ]);
                $count++;
            }
        }

        $this->command->info("    ✓ Created {$count} favorites");
    }

    /**
     * Seed plans
     */
    protected function seedPlans(): void
    {
        $this->command->info('  → Seeding plans...');

        $planTemplates = [
            ['title' => 'Passeio de fim de semana', 'status' => 'planned'],
            ['title' => 'Dia no parque', 'status' => 'completed'],
            ['title' => 'Aventura familiar', 'status' => 'draft'],
        ];

        $count = 0;
        foreach ($this->userIds as $idx => $userId) {
            if ($idx < 4)
                continue; // Skip admins

            $familyId = $this->familyIds[($idx - 4) % count($this->familyIds)] ?? null;

            // Each user creates 1-2 plans
            $numPlans = rand(1, 2);
            for ($i = 0; $i < $numPlans; $i++) {
                $template = $planTemplates[array_rand($planTemplates)];
                $planId = Str::uuid()->toString();
                $this->planIds[] = $planId;

                DB::table('plans')->insertOrIgnore([
                    'id' => $planId,
                    'user_id' => $userId,
                    'family_id' => $familyId,
                    'title' => $template['title'],
                    'date' => Carbon::now()->addDays(rand(1, 30))->toDateString(),
                    'status' => $template['status'],
                    'visibility' => 'family',
                    'notes' => 'Notas do plano...',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Add plan collaborator (owner)
                DB::table('plan_collaborators')->insertOrIgnore([
                    'id' => Str::uuid()->toString(),
                    'plan_id' => $planId,
                    'user_id' => $userId,
                    'role' => 'owner',
                    'invited_at' => now(),
                    'accepted_at' => now(),
                    'invited_by' => $userId,
                ]);

                // Add 1-3 experiences to plan
                $numExps = rand(1, 3);
                $expIds = array_rand(array_flip($this->experienceIds), min($numExps, count($this->experienceIds)));
                $order = 1;

                foreach ((array) $expIds as $expId) {
                    DB::table('plan_experiences')->insertOrIgnore([
                        'plan_id' => $planId,
                        'experience_id' => $expId,
                        'order' => $order++,
                        'time_slot' => ['morning', 'afternoon', 'evening'][array_rand(['morning', 'afternoon', 'evening'])],
                        'notes' => null,
                    ]);
                }

                $count++;
            }
        }

        $this->command->info("    ✓ Created {$count} plans with experiences");
    }

    /**
     * Seed notifications
     */
    protected function seedNotifications(): void
    {
        $this->command->info('  → Seeding notifications...');

        $notificationTypes = [
            ['type' => 'experience_nearby', 'title' => '📍 Nova experiência perto de você!', 'body' => 'Descubra o Parque Ibirapuera a 2km de distância.'],
            ['type' => 'plan_reminder', 'title' => '📅 Lembrete de plano', 'body' => 'Seu passeio está marcado para amanhã!'],
            ['type' => 'trending', 'title' => '🔥 Experiência em alta', 'body' => 'O MASP está bombando esta semana!'],
            ['type' => 'family_invite', 'title' => '👨‍👩‍👧 Convite para família', 'body' => 'Maria te convidou para a Família Silva.'],
            ['type' => 'system', 'title' => '🎉 Bem-vindo!', 'body' => 'Explore as melhores experiências para sua família.'],
        ];

        $count = 0;
        foreach ($this->userIds as $idx => $userId) {
            // Create 2-5 notifications per user
            $numNotifs = rand(2, 5);
            for ($i = 0; $i < $numNotifs; $i++) {
                $notif = $notificationTypes[array_rand($notificationTypes)];

                DB::table('notifications')->insertOrIgnore([
                    'id' => Str::uuid()->toString(),
                    'user_id' => $userId,
                    'type' => $notif['type'],
                    'title' => $notif['title'],
                    'body' => $notif['body'],
                    'data' => json_encode(['experience_id' => $this->experienceIds[0] ?? null]),
                    'is_read' => rand(0, 1) === 1,
                    'created_at' => Carbon::now()->subDays(rand(0, 7)),
                ]);
                $count++;
            }

            // Create notification settings
            DB::table('notification_settings')->insertOrIgnore([
                'user_id' => $userId,
                'push_enabled' => true,
                'email_enabled' => false,
                'types' => json_encode([
                    'experience_nearby' => true,
                    'plan_reminder' => true,
                    'trending' => true,
                    'family_invite' => true,
                ]),
            ]);
        }

        $this->command->info("    ✓ Created {$count} notifications");
    }

    /**
     * Seed gamification data
     */
    protected function seedGamification(): void
    {
        $this->command->info('  → Seeding gamification...');

        $badges = ['explorer', 'reviewer', 'social', 'planner', 'streak_7', 'first_save'];

        foreach ($this->userIds as $idx => $userId) {
            if ($idx < 4)
                continue; // Skip admins

            // Create user stats
            DB::table('user_stats')->insertOrIgnore([
                'user_id' => $userId,
                'xp' => rand(100, 1500),
                'level' => rand(1, 5),
                'streak_days' => rand(0, 15),
                'last_action_at' => Carbon::now()->subHours(rand(1, 48)),
                'total_saves' => rand(3, 20),
                'total_reviews' => rand(0, 10),
                'total_plans' => rand(1, 5),
                'total_referrals' => rand(0, 3),
                'updated_at' => now(),
            ]);

            // Assign 1-3 badges
            $numBadges = rand(1, 3);
            $userBadges = array_rand(array_flip($badges), $numBadges);

            foreach ((array) $userBadges as $badge) {
                DB::table('user_badges')->insertOrIgnore([
                    'user_id' => $userId,
                    'badge_slug' => $badge,
                    'earned_at' => Carbon::now()->subDays(rand(1, 30)),
                ]);
            }
        }

        $this->command->info('    ✓ Created user stats and badges');
    }

    /**
     * Seed collections
     */
    protected function seedCollections(): void
    {
        $this->command->info('  → Seeding collections...');

        // First run CollectionSeeder if it exists
        if (class_exists(\Database\Seeders\CollectionSeeder::class)) {
            $this->call(CollectionSeeder::class);
        } else {
            $this->command->warn('    ⚠ CollectionSeeder not found, skipping...');
        }
    }
}
