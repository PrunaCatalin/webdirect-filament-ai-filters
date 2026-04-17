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
    | Prompt Template Path
    |--------------------------------------------------------------------------
    |
    | Absolute path to a Markdown/text file used as the FilterAgent system
    | prompt template. When null, the plugin's built-in template is used.
    |
    | The template is rendered with the following placeholders:
    |   {{available}}      — JSON list of available filters and their fields
    |   {{current}}        — JSON of the current filter state
    |   {{searchable}}     — JSON array of searchable column names
    |   {{currentSearch}}  — current global search value
    |   {{extra}}          — rendered "instructions" appended (blank if none)
    |
    | Publish the default template with:
    |   php artisan vendor:publish --tag=ai-filters-prompt
    |
    */

    'prompt_path' => env('AI_FILTERS_PROMPT_PATH'),

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
