<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Services\AuthService;
use App\Services\BookService;
use App\Services\CommentService;
use App\Services\FileService;
use Laravel\Sanctum\Sanctum;
use App\Models\Book;
use App\Models\PersonalAccessToken;
use App\Models\Comment;
use App\Observers\CommentObserver;
use App\Policies\BookPolicy;
use App\Policies\CommentPolicy;
use Illuminate\Http\Resources\Json\JsonResource;

// 1. Add these Scramble imports
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FileService::class);

        $this->app->singleton(AuthService::class, function ($app) {
            return new AuthService($app->make(FileService::class));
        });

        $this->app->singleton(BookService::class, function ($app) {
            return new BookService($app->make(FileService::class));
        });

        $this->app->singleton(CommentService::class, function ($app) {
            return new CommentService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        JsonResource::withoutWrapping();
        Comment::observe(CommentObserver::class);
        Gate::policy(Book::class, BookPolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);

        // 2. Add the Scramble Security Configuration
        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer') // This tells Scramble to use a Bearer token
                );
            });
        Gate::define('viewApiDocs', function ($user = null) {
        // ⚠️ WARNING: Returning true allows ANYONE on the internet to see your docs.
        // This is perfect for public testing!
        return true; 
        
        // Secure Alternative for later:
        // return app()->environment('local') || ($user && $user->is_admin);
        });
    }
}