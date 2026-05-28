<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use App\Services\AuthService;
use App\Services\BookService;
use App\Services\CommentService;
use Laravel\Sanctum\Sanctum;
use App\Models\Book;
use App\Models\PersonalAccessToken;
use App\Models\Comment;
use App\Observers\CommentObserver;
use App\Policies\BookPolicy;
use App\Policies\CommentPolicy;
use Illuminate\Http\Resources\Json\JsonResource;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AuthService::class, function ($app) {
            return new AuthService();
        });

        $this->app->singleton(BookService::class, function ($app) {
            return new BookService();
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
    }
}
