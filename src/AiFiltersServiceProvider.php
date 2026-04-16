<?php

namespace Webdirect\AiFilters;

use Illuminate\Support\ServiceProvider;

class AiFiltersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-filters.php', 'ai-filters');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/ai-filters.php' => config_path('ai-filters.php'),
        ], 'ai-filters-config');

        $this->overrideAiProviderKey();
    }

    /**
     * Override the configured laravel/ai provider key with the plugin's
     * own key when the user has set `ai-filters.api_key`.
     */
    protected function overrideAiProviderKey(): void
    {
        $key = config('ai-filters.api_key');

        if (blank($key)) {
            return;
        }

        $provider = config('ai-filters.provider', 'anthropic');

        config(["ai.providers.{$provider}.key" => $key]);
    }
}
