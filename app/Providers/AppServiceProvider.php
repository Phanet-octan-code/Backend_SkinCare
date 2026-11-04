<?php

namespace App\Providers;

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
        if ($this->app->environment('production') || request()->header('x-forwarded-proto') === 'https') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        \Illuminate\Pagination\Paginator::useBootstrapFive();

        \Illuminate\Support\Facades\Blade::directive('currency', function ($expression) {
            return "<?php echo \\App\\Models\\Setting::formatMoney($expression); ?>";
        });
    }
}
