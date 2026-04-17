<?php

namespace Webdirect\AiFilters;

use Illuminate\Support\ServiceProvider;

class AiFiltersServiceProvider extends ServiceProvider
{
    private const CONFIG_PATH = __DIR__.'/../config/ai-filters.php';

    private const PROMPT_PATH = __DIR__.'/../resources/prompts/filter-agent.md';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'ai-filters');
    }

    public function boot(): void
    {
        $this->publishes(
            [self::CONFIG_PATH => config_path('ai-filters.php')],
            'ai-filters-config',
        );

        $this->publishes(
            [self::PROMPT_PATH => resource_path('prompts/ai-filters/filter-agent.md')],
            'ai-filters-prompt',
        );

        $this->overrideAiProviderKey();
    }

    /**
     * Copy the plugin's API key into the configured laravel/ai provider,
     * allowing users to override the provider key without touching config/ai.php.
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
