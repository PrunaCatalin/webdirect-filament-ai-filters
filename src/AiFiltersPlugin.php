<?php

namespace Webdirect\AiFilters;

use Filament\Contracts\Plugin;
use Filament\Panel;

class AiFiltersPlugin implements Plugin
{
    public const ID = 'webdirect-ai-filters';

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(static::getPluginId());

        return $plugin;
    }

    public static function getPluginId(): string
    {
        return self::ID;
    }

    public function getId(): string
    {
        return static::getPluginId();
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}
}
