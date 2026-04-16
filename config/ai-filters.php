<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider
    |--------------------------------------------------------------------------
    |
    | The Laravel AI provider used by the AiFilterAction.
    | Must be one of the providers supported by laravel/ai (anthropic, openai, ...).
    |
    */

    'provider' => env('AI_FILTERS_PROVIDER', 'anthropic'),

    /*
    |--------------------------------------------------------------------------
    | Model
    |--------------------------------------------------------------------------
    |
    | The model id sent with each prompt. When null the provider default is used.
    |
    */

    'model' => env('AI_FILTERS_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Optional: when set, the plugin overrides the configured provider's
    | API key in `config/ai.php` at boot time. Leave null to rely on the
    | provider's own env var (ANTHROPIC_API_KEY, OPENAI_API_KEY, ...).
    |
    */

    'api_key' => env('AI_FILTERS_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | System Instructions
    |--------------------------------------------------------------------------
    |
    | Extra text appended to the FilterAgent system prompt. Use this to give
    | the model additional context about the table or business rules.
    |
    */

    'instructions' => null,

    /*
    |--------------------------------------------------------------------------
    | Action Defaults
    |--------------------------------------------------------------------------
    */

    'action' => [
        'label' => 'AI Filter',
        'icon' => 'heroicon-o-sparkles',
        'modal_heading' => 'Filter with AI',
        'modal_description' => 'Describe what you want to find. Active filters are sent as context.',
    ],

];
