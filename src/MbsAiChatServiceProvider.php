<?php

namespace Mbs\LaravelAiChat;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Mbs\LaravelAiChat\Console\Commands\InspectMbsAiChatContextCommand;
use Mbs\LaravelAiChat\Console\Commands\InstallMbsAiChatCommand;
use Mbs\LaravelAiChat\Contracts\AiChatProvider;
use Mbs\LaravelAiChat\Support\Providers\OpenAiChatProvider;

class MbsAiChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mbs-ai-chat.php', 'mbs-ai-chat');

        $this->app->bind(AiChatProvider::class, OpenAiChatProvider::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mbs-ai-chat');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerRoutes();
        $this->registerBladeDirectives();
        $this->registerPublishing();
        $this->registerRateLimiter();
        $this->registerCommands();
    }

    private function registerRoutes(): void
    {
        Route::middleware(config('mbs-ai-chat.routes.middleware', ['web', 'throttle:mbs-ai-chat']))
            ->prefix((string) config('mbs-ai-chat.routes.prefix', 'mbs-ai-chat'))
            ->as((string) config('mbs-ai-chat.routes.name', 'mbs-ai-chat.'))
            ->group(__DIR__.'/../routes/web.php');
    }

    private function registerBladeDirectives(): void
    {
        Blade::componentNamespace('Mbs\\LaravelAiChat\\View\\Components', 'mbs-ai-chat');

        Blade::directive('mbsChatModal', fn (): string => "<?php echo view('mbs-ai-chat::components.modal')->render(); ?>");
        Blade::directive('mbsChatScripts', fn (): string => "<?php echo view('mbs-ai-chat::components.scripts')->render(); ?>");
        Blade::directive('mbsChatStyles', fn (): string => "<?php echo view('mbs-ai-chat::components.styles')->render(); ?>");
        Blade::directive('mbsChat', fn (): string => "<?php echo view('mbs-ai-chat::components.styles')->render(); echo view('mbs-ai-chat::components.modal')->render(); echo view('mbs-ai-chat::components.scripts')->render(); ?>");
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/mbs-ai-chat.php' => config_path('mbs-ai-chat.php'),
        ], 'mbs-ai-chat-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'mbs-ai-chat-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/mbs-ai-chat'),
        ], 'mbs-ai-chat-views');
    }

    private function registerRateLimiter(): void
    {
        RateLimiter::for('mbs-ai-chat', function (Request $request): Limit {
            return Limit::perMinute(12)->by($request->ip() ?? 'guest');
        });
    }

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallMbsAiChatCommand::class,
            InspectMbsAiChatContextCommand::class,
        ]);
    }
}
