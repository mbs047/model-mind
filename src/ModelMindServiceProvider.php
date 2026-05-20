<?php

namespace Mbs\ModelMind;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Mbs\ModelMind\Console\Commands\InspectModelMindContextCommand;
use Mbs\ModelMind\Console\Commands\InstallModelMindCommand;
use Mbs\ModelMind\Console\Commands\LearnModelMindKnowledgeCommand;
use Mbs\ModelMind\Contracts\ModelMindProvider;
use Mbs\ModelMind\Support\Providers\OpenAiModelMindProvider;
use Mbs\ModelMind\Support\Views\ModelMindViewRenderer;

class ModelMindServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/model-mind.php', 'model-mind');

        $this->app->bind(ModelMindProvider::class, OpenAiModelMindProvider::class);
        $this->app->singleton('model-mind.views', ModelMindViewRenderer::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'model-mind');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerRoutes();
        $this->registerBladeDirectives();
        $this->registerPublishing();
        $this->registerRateLimiter();
        $this->registerCommands();
    }

    private function registerRoutes(): void
    {
        Route::middleware(config('model-mind.routes.middleware', ['web', 'throttle:model-mind']))
            ->prefix((string) config('model-mind.routes.prefix', 'model-mind'))
            ->as((string) config('model-mind.routes.name', 'model-mind.'))
            ->group(__DIR__.'/../routes/web.php');
    }

    private function registerBladeDirectives(): void
    {
        Blade::componentNamespace('Mbs\\ModelMind\\View\\Components', 'model-mind');

        Blade::directive('modelMindModal', fn (string $expression): string => $this->bladeViewCall('modal', $expression));
        Blade::directive('modelMindScripts', fn (string $expression): string => $this->bladeViewCall('scripts', $expression));
        Blade::directive('modelMindStyles', fn (string $expression): string => $this->bladeViewCall('styles', $expression));
        Blade::directive('modelMind', fn (string $expression): string => $this->bladeViewCall('all', $expression));
    }

    private function bladeViewCall(string $method, string $expression): string
    {
        $arguments = trim($expression);

        if ($arguments === '') {
            return "<?php echo app('model-mind.views')->{$method}(); ?>";
        }

        return "<?php echo app('model-mind.views')->{$method}({$arguments}); ?>";
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/model-mind.php' => config_path('model-mind.php'),
        ], 'model-mind-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'model-mind-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/model-mind'),
        ], 'model-mind-views');
    }

    private function registerRateLimiter(): void
    {
        RateLimiter::for('model-mind', function (Request $request): Limit {
            return Limit::perMinute(12)->by($request->ip() ?? 'guest');
        });
    }

    private function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallModelMindCommand::class,
            InspectModelMindContextCommand::class,
            LearnModelMindKnowledgeCommand::class,
        ]);
    }
}
