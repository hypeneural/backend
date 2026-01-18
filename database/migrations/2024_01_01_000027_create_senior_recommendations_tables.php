<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     * 
     * Creates tables for:
     * - Collections curadas (editorial)
     * - Place opening hours (horários de funcionamento)
     * - City weather cache (cache de clima)
     * - Events tracking (analytics)
     */
    public function up(): void
    {
        // Collections curadas (ex: "Dia de chuva", "Top 10 com bebê")
        if (!Schema::hasTable('collections')) {
            Schema::create('collections', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name', 100);
                $table->string('slug', 100)->unique();
                $table->string('emoji', 10)->nullable();
                $table->string('cover_image')->nullable();
                $table->text('description')->nullable();
                $table->uuid('city_id')->nullable()->comment('null = todas as cidades');
                $table->enum('type', ['editorial', 'seasonal', 'partner'])->default('editorial');
                $table->boolean('is_featured')->default(false)->comment('Destaque na home');
                $table->integer('order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamp('starts_at')->nullable()->comment('Para sazonais');
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();

                $table->foreign('city_id')->references('id')->on('cities')->onDelete('set null');
                $table->index(['is_active', 'is_featured', 'order']);
                $table->index(['city_id', 'is_active']);
            });
        }

        // Itens das collections
        if (!Schema::hasTable('collection_items')) {
            Schema::create('collection_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('collection_id');
                $table->uuid('experience_id');
                $table->integer('order')->default(0);
                $table->string('custom_title')->nullable()->comment('Override do título');
                $table->text('custom_description')->nullable();
                $table->timestamps();

                $table->foreign('collection_id')->references('id')->on('collections')->onDelete('cascade');
                $table->foreign('experience_id')->references('id')->on('experiences')->onDelete('cascade');
                $table->unique(['collection_id', 'experience_id']);
                $table->index(['collection_id', 'order']);
            });
        }

        // Horários de funcionamento dos lugares
        if (!Schema::hasTable('place_opening_hours')) {
            Schema::create('place_opening_hours', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('place_id');
                $table->tinyInteger('weekday')->unsigned()->comment('0=Dom, 1=Seg, ..., 6=Sab');
                $table->time('open_time')->nullable();
                $table->time('close_time')->nullable();
                $table->boolean('is_closed')->default(false)->comment('Fechado neste dia');
                $table->boolean('is_24h')->default(false);
                $table->timestamps();

                $table->foreign('place_id')->references('id')->on('places')->onDelete('cascade');
                $table->unique(['place_id', 'weekday']);
                $table->index(['place_id', 'weekday']);
            });
        }

        // Cache de clima por cidade (atualizado por job)
        if (!Schema::hasTable('city_weather_cache')) {
            Schema::create('city_weather_cache', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('city_id');
                $table->date('date');
                $table->enum('condition', ['sun', 'cloud', 'rain', 'storm', 'snow'])->default('sun');
                $table->decimal('temp_min', 4, 1)->nullable();
                $table->decimal('temp_max', 4, 1)->nullable();
                $table->tinyInteger('humidity')->unsigned()->nullable();
                $table->tinyInteger('rain_probability')->unsigned()->default(0);
                $table->json('hourly')->nullable()->comment('Previsão por hora');
                $table->timestamp('fetched_at')->useCurrent();
                $table->timestamps();

                $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');
                $table->unique(['city_id', 'date']);
                $table->index(['city_id', 'date', 'condition']);
            });
        }

        // Eventos para analytics (append-only)
        if (!Schema::hasTable('events_raw')) {
            Schema::create('events_raw', function (Blueprint $table) {
                $table->id();
                $table->uuid('user_id')->nullable();
                $table->string('event', 50)->comment('view, save, review, share, etc');
                $table->string('target_type', 30)->nullable()->comment('experience, plan, memory');
                $table->uuid('target_id')->nullable();
                $table->uuid('city_id')->nullable();
                $table->string('source', 30)->nullable()->comment('home, search, map, share');
                $table->json('meta')->nullable()->comment('Dados extras');
                $table->string('ip_hash', 64)->nullable();
                $table->string('ua_hash', 64)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['user_id', 'created_at']);
                $table->index(['event', 'created_at']);
                $table->index(['target_type', 'target_id']);
                $table->index(['city_id', 'event', 'created_at']);
            });
        }

        // Métricas agregadas por experiência (atualizado por job)
        if (!Schema::hasTable('experience_metrics_daily')) {
            Schema::create('experience_metrics_daily', function (Blueprint $table) {
                $table->id();
                $table->uuid('experience_id');
                $table->date('date');
                $table->integer('views')->default(0);
                $table->integer('saves')->default(0);
                $table->integer('unsaves')->default(0);
                $table->integer('shares')->default(0);
                $table->integer('plan_adds')->default(0);
                $table->integer('reviews')->default(0);
                $table->integer('clicks')->default(0);
                $table->timestamps();

                $table->foreign('experience_id')->references('id')->on('experiences')->onDelete('cascade');
                $table->unique(['experience_id', 'date']);
                $table->index(['date', 'views']);
            });
        }

        // Adicionar campos para ranking score na experience_search
        if (Schema::hasTable('experience_search') && !Schema::hasColumn('experience_search', 'kid_friendly_score')) {
            Schema::table('experience_search', function (Blueprint $table) {
                $table->decimal('kid_friendly_score', 5, 2)->default(50)->after('trending_score')
                    ->comment('0-100: Score de adequação para crianças');
                $table->decimal('mom_score', 5, 2)->default(50)->after('kid_friendly_score')
                    ->comment('0-100: Score baseado em reviews de mães');
                $table->boolean('is_open_now')->default(true)->after('mom_score');
                $table->boolean('has_coupon')->default(false)->after('is_open_now');
                $table->boolean('partner_verified')->default(false)->after('has_coupon');
                $table->decimal('price_min', 8, 2)->nullable()->after('partner_verified');
                $table->decimal('price_max', 8, 2)->nullable()->after('price_min');

                $table->index(['city_id', 'is_open_now', 'trending_score']);
                $table->index(['city_id', 'kid_friendly_score']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('experience_search', function (Blueprint $table) {
            $table->dropColumn([
                'kid_friendly_score',
                'mom_score',
                'is_open_now',
                'has_coupon',
                'partner_verified',
                'price_min',
                'price_max'
            ]);
        });

        Schema::dropIfExists('experience_metrics_daily');
        Schema::dropIfExists('events_raw');
        Schema::dropIfExists('city_weather_cache');
        Schema::dropIfExists('place_opening_hours');
        Schema::dropIfExists('collection_items');
        Schema::dropIfExists('collections');
    }
};
