<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\HomeController;
use App\Http\Controllers\V1\SearchController;
use App\Http\Controllers\V1\MapController;
use App\Http\Controllers\V1\ExperienceController;
use App\Http\Controllers\V1\FamilyController;
use App\Http\Controllers\V1\FavoriteController;
use App\Http\Controllers\V1\FavoriteListController;
use App\Http\Controllers\V1\ShareLinkController;
use App\Http\Controllers\V1\PlanController;
use App\Http\Controllers\V1\ReviewController;
use App\Http\Controllers\V1\MemoryController;
use App\Http\Controllers\V1\CategoryController;
use App\Http\Controllers\V1\CityController;
use App\Http\Controllers\V1\OnboardingController;
use App\Http\Controllers\V1\UserController;
use App\Http\Controllers\V1\DependentController;
use App\Http\Controllers\V1\UploadController;
use App\Http\Controllers\V1\NotificationController;
use App\Http\Controllers\V1\ReportController;
use App\Http\Controllers\V1\DeepLinkController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| API Version: 1.0
| Base URL: /api/v1
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Public Routes
    |--------------------------------------------------------------------------
    */

    // Authentication
    Route::prefix('auth')->group(function () {
        Route::middleware('throttle:otp')->group(function () {
            Route::post('/otp/send', [AuthController::class, 'sendOtp']);
        });

        Route::middleware('throttle:otp_verify')->group(function () {
            Route::post('/otp/verify', [AuthController::class, 'verifyOtp']);
        });

        Route::post('/refresh', [AuthController::class, 'refresh']);
    });

    // Deep link resolver (public)
    Route::get('/resolve/{code}', [DeepLinkController::class, 'resolve']);

    // Categories (public, cached)
    Route::get('/categories', [CategoryController::class, 'index']);

    // Cities (public)
    Route::prefix('cities')->group(function () {
        Route::get('/', [CityController::class, 'index']);
        Route::get('/{id}', [CityController::class, 'show']);
    });

    /*
    |--------------------------------------------------------------------------
    | Protected Routes (Require JWT)
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:api')->group(function () {

        // Auth
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });

        // Onboarding
        Route::prefix('onboarding')->group(function () {
            Route::get('/status', [OnboardingController::class, 'status']);
            Route::post('/complete', [OnboardingController::class, 'complete']);
        });

        // User Profile
        Route::prefix('users/me')->group(function () {
            Route::put('/', [UserController::class, 'update']);
            Route::patch('/avatar', [UserController::class, 'updateAvatar']);
            Route::post('/location', [UserController::class, 'updateLocation']);
            Route::get('/stats', [UserController::class, 'stats']);
            Route::delete('/', [UserController::class, 'destroy']);
        });

        // Home
        Route::get('/home', [HomeController::class, 'index']);

        // Experiences
        Route::prefix('experiences')->group(function () {
            Route::get('/search', [SearchController::class, 'search']);
            Route::get('/{id}', [ExperienceController::class, 'show']);

            // Reviews for experience
            Route::get('/{experienceId}/reviews', [ReviewController::class, 'index']);
            Route::post('/{experienceId}/reviews', [ReviewController::class, 'store']);
        });

        // Map
        Route::get('/map/experiences', [MapController::class, 'experiences']);

        // Family
        Route::prefix('family')->group(function () {
            Route::get('/', [FamilyController::class, 'show']);
            Route::put('/', [FamilyController::class, 'update']);
            Route::post('/', [FamilyController::class, 'store']);
            Route::post('/invite', [FamilyController::class, 'invite']);
            Route::post('/join', [FamilyController::class, 'join']);
            Route::post('/leave', [FamilyController::class, 'leave']);
            Route::delete('/{familyId}/members/{userId}', [FamilyController::class, 'removeMember']);

            // Dependents
            Route::prefix('dependents')->group(function () {
                Route::get('/', [DependentController::class, 'index']);
                Route::post('/', [DependentController::class, 'store']);
                Route::put('/{id}', [DependentController::class, 'update']);
                Route::delete('/{id}', [DependentController::class, 'destroy']);
            });
        });

        // Favorites
        Route::prefix('favorites')->group(function () {
            Route::get('/', [FavoriteController::class, 'index']);
            Route::post('/', [FavoriteController::class, 'store']);
            Route::delete('/{experience_id}', [FavoriteController::class, 'destroy']);
        });

        // Favorite Lists
        Route::prefix('favorite-lists')->group(function () {
            Route::post('/', [FavoriteListController::class, 'store']);
            Route::put('/{id}', [FavoriteListController::class, 'update']);
            Route::delete('/{id}', [FavoriteListController::class, 'destroy']);
        });

        // Plans
        Route::prefix('plans')->group(function () {
            Route::get('/', [PlanController::class, 'index']);
            Route::post('/', [PlanController::class, 'store']);
            Route::get('/{id}', [PlanController::class, 'show']);
            Route::put('/{id}', [PlanController::class, 'update']);
            Route::delete('/{id}', [PlanController::class, 'destroy']);
            Route::post('/{id}/complete', [PlanController::class, 'complete']);
            Route::post('/{id}/duplicate', [PlanController::class, 'duplicate']);
            Route::post('/{id}/experiences', [PlanController::class, 'addExperience']);
            Route::put('/{id}/experiences/{expId}', [PlanController::class, 'updateExperience']);
            Route::delete('/{id}/experiences/{expId}', [PlanController::class, 'removeExperience']);
            Route::post('/{id}/collaborators', [PlanController::class, 'inviteCollaborator']);
            Route::delete('/{id}/collaborators/{userId}', [PlanController::class, 'removeCollaborator']);
        });

        // Reviews
        Route::prefix('reviews')->group(function () {
            Route::put('/{id}', [ReviewController::class, 'update']);
            Route::post('/{id}/helpful', [ReviewController::class, 'markHelpful']);
            Route::delete('/{id}', [ReviewController::class, 'destroy']);
        });

        // Memories
        Route::prefix('memories')->group(function () {
            Route::get('/', [MemoryController::class, 'index']);
            Route::post('/', [MemoryController::class, 'store']);
            Route::get('/{id}', [MemoryController::class, 'show']);
            Route::put('/{id}', [MemoryController::class, 'update']);
            Route::post('/{id}/reactions', [MemoryController::class, 'react']);
            Route::post('/{id}/comments', [MemoryController::class, 'addComment']);
            Route::delete('/{id}', [MemoryController::class, 'destroy']);
        });

        // Notifications
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::patch('/{id}/read', [NotificationController::class, 'markRead']);
            Route::post('/read-all', [NotificationController::class, 'markAllRead']);
            Route::delete('/{id}', [NotificationController::class, 'destroy']);
            Route::get('/settings', [NotificationController::class, 'getSettings']);
            Route::put('/settings', [NotificationController::class, 'updateSettings']);
        });

        // Uploads
        Route::prefix('uploads')->group(function () {
            Route::post('/presign', [UploadController::class, 'presign']);
            Route::post('/local', [UploadController::class, 'uploadLocal']);
        });

        // Reports
        Route::post('/reports', [ReportController::class, 'store']);

        // Share Links
        Route::post('/share-links', [ShareLinkController::class, 'store']);
    });

});
