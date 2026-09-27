<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');
        Paginator::defaultSimpleView('partials.pagination');

        // NFR Keamanan: rate limit login 5 percobaan / menit
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower((string) $request->input('username')).'|'.$request->ip()));

        // Pengaturan toko tersedia di semua view
        View::composer('*', function ($view) {
            $view->with('appSettings', setting());
        });

        Blade::directive('rp', fn ($expr) => "<?php echo rupiah($expr); ?>");
    }
}
