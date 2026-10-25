<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Public order page: browsing and ordering are counted apart (a visitor
        // looking around must never be blocked from placing the order).
        RateLimiter::for('store-view', fn (Request $r) => Limit::perMinute(120)->by('store-view|'.$r->ip()));
        RateLimiter::for('store-order', fn (Request $r) => Limit::perMinute(6)->by('store-order|'.$r->ip())
            ->response(fn () => response()->json(['message' => 'طلبات كثيرة خلال وقت قصير، انتظر دقيقة ثم حاول مرة أخرى.'], 429)));
    }
}
