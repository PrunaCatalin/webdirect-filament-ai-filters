# Webdirect AI Filters for Filament

A Filament v4 panel plugin that lets users filter any table with natural language.
Click the **AI Filter** button on a table, type what you want (`"active platinum
customers from Germany signed up last year"`), and the plugin sends your prompt
plus the table's available filters to an AI model. The model responds with a
structured set of filter values that the plugin applies to the table.

Powered by the official [`laravel/ai`](https://laravel.com/docs/13.x/ai-sdk)
package, so any provider it supports (Anthropic, OpenAI, Gemini, ...) can be
used.

> **Credits**: the idea for this plugin came from
> [this video](https://www.youtube.com/watch?v=82ntd5LopoI) on the
> [Filament Daily](https://www.youtube.com/@FilamentDaily) channel by Povilas
> Korop. Huge thank-you — go subscribe.

## Requirements

| Package          | Version |
| ---------------- | ------- |
| PHP              | ^8.3    |
| Laravel          | ^11 / ^12 / ^13 |
| filament/filament | ^4.0   |
| laravel/ai       | ^0.6    |

## Installation

### 1. Require the package

```bash
composer require webdirect/ai-filters
```

`laravel/ai` is pulled in automatically. The plugin's service provider is
auto-discovered, no manual registration needed.

### 2. Publish the config

```bash
php artisan vendor:publish --tag=ai-filters-config
```

This creates `config/ai-filters.php`.

### 3. Configure your AI provider

The plugin uses whatever provider you configure in `config/ai.php`. The fastest
path for Anthropic:

```dotenv
ANTHROPIC_API_KEY=sk-ant-...
AI_FILTERS_PROVIDER=anthropic
AI_FILTERS_MODEL=claude-haiku-4-5-20251001
```

For OpenAI:

```dotenv
OPENAI_API_KEY=sk-...
AI_FILTERS_PROVIDER=openai
AI_FILTERS_MODEL=gpt-4o-mini
```

If you want to override the provider's API key from the plugin's own config,
set `AI_FILTERS_API_KEY` instead — the plugin will rewrite
`ai.providers.<provider>.key` at boot.

### 4. Register the plugin on a panel

```php
use Webdirect\AiFilters\AiFiltersPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(AiFiltersPlugin::make());
}
```

> Registering the plugin is optional today — the action works on its own — but
> doing so future-proofs you for panel-level configuration helpers.

## Usage

Add `AiFilterAction::make()` to any Filament table's `headerActions()`:

```php
use Webdirect\AiFilters\Actions\AiFilterAction;

public static function configure(Table $table): Table
{
    return $table
        ->columns([/* ... */])
        ->filters([
            SelectFilter::make('status')
                ->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                ])
                ->multiple(),
            // ...
        ])
        ->headerActions([
            AiFilterAction::make(),
        ]);
}
```

That's it. Open the table, click **AI Filter**, write what you want, hit
**Apply**.

### Supported filter types

The plugin is fully dynamic: it introspects each filter's form schema and
sends the AI a rich description of every form field — its name, type, label,
whether it's `required`, its Laravel validation rules, and (for Select/Radio)
the accepted options.

This means **any** Filament filter works out of the box, including your own
custom-schema filters. The built-in handling for the common ones:

| Filter                                    | Fields exposed to AI                              |
| ----------------------------------------- | ------------------------------------------------- |
| `SelectFilter` (single)                   | `value` + option keys                             |
| `SelectFilter` with `->multiple()`        | `values` (array) + option keys                    |
| `TernaryFilter`                           | `value` + `booleanLike: true`                     |
| `Filter` (toggle / no schema)             | `isActive` (Checkbox / Toggle)                    |
| `Filter` with custom schema               | every form field: name, type, required, rules, options, inputType (email/number/url), inputFormat (date/datetime/time), placeholder |

For custom-schema filters, the AI sees the field names you defined, so use
descriptive keys like `from`, `until`, `min_revenue`, `email_domain`.

### Global search

If the table has searchable columns and the user's request maps to free text
(e.g. "find Amira"), the AI will fall back to setting the table's global
search. Filters are preferred whenever a matching one exists.

### Customising the action

`AiFilterAction::make()` returns a regular Filament `Action`, so you can chain
on top of it:

```php
AiFilterAction::make('aiFilter')
    ->label('Ask AI')
    ->icon('heroicon-o-bolt')
    ->color('warning')
    ->visible(fn () => auth()->user()->can('use-ai-filters'));
```

### Customising the system prompt

The full system prompt that steers the AI lives in a Markdown file, not in
PHP code. You can override it with your own file without touching the package.

**1. Publish the default template** so you have a copy to work from:

```bash
php artisan vendor:publish --tag=ai-filters-prompt
```

This copies the built-in template to
`resources/prompts/ai-filters/filter-agent.md`.

**2. Point the plugin at your file** either via `.env`:

```dotenv
AI_FILTERS_PROMPT_PATH="${PWD}/resources/prompts/ai-filters/filter-agent.md"
```

or directly in `config/ai-filters.php`:

```php
'prompt_path' => resource_path('prompts/ai-filters/filter-agent.md'),
```

When `prompt_path` is `null`, the built-in template is used.

**3. Placeholders** — the template is rendered with `strtr()` against:

| Placeholder          | Replaced with                                                       |
| -------------------- | ------------------------------------------------------------------- |
| `{{available}}`      | pretty-printed JSON list of filters + fields + rules + options      |
| `{{current}}`        | JSON of the current `tableFilters` state                            |
| `{{searchable}}`     | JSON array of searchable column names                               |
| `{{currentSearch}}`  | current global search value                                         |
| `{{extra}}`          | rendered `ai-filters.instructions` text (empty when not set)        |

Any placeholder you omit is simply not rewritten — keep only what you need.

## Configuration reference

`config/ai-filters.php`:

```php
return [
    'provider' => env('AI_FILTERS_PROVIDER', 'anthropic'),
    'model'    => env('AI_FILTERS_MODEL'),
    'api_key'  => env('AI_FILTERS_API_KEY'),

    'instructions' => null, // extra system-prompt text appended to the agent

    'prompt_path'  => env('AI_FILTERS_PROMPT_PATH'), // override path to MD template, null = built-in

    'action' => [
        'label'             => 'AI Filter',
        'icon'              => 'heroicon-o-sparkles',
        'modal_heading'     => 'Filter with AI',
        'modal_description' => 'Describe what you want to find. Active filters are sent as context.',
    ],
];
```

| Key             | Purpose                                                                 |
| --------------- | ----------------------------------------------------------------------- |
| `provider`      | Name of the `laravel/ai` provider to use (`anthropic`, `openai`, ...). |
| `model`         | Specific model id. `null` = provider default.                           |
| `api_key`       | Optional. Overrides the provider's configured API key at boot.          |
| `instructions`  | Free text appended to the agent's system prompt. Use for table-specific business rules. |
| `prompt_path`   | Absolute path to a Markdown prompt template. `null` = built-in template.|
| `action.*`      | Visual defaults for the action button and modal.                        |

## How it works

1. The user clicks **AI Filter** and types a prompt.
2. The plugin reads the table's filters via `$table->getFilters()` and extracts
   each filter's name, type, accepted form-field keys, and (for `SelectFilter`)
   its options.
3. It also reads the current `$livewire->tableFilters` and `$livewire->tableSearch`.
4. All of the above is passed to a `FilterAgent` (a `laravel/ai` Agent with
   `HasStructuredOutput`), which returns:
   ```json
   {
     "filters": [
       { "filter": "status", "key": "values", "values": ["active"] },
       { "filter": "tier",   "key": "value",  "value": "platinum" }
     ],
     "search": null
   }
   ```
5. The plugin merges those updates back into `$livewire->tableFilters` (and
   sets `$livewire->tableSearch` if the AI returned a search query).
6. Filament re-renders the table with the new state.

## Troubleshooting

### `AI provider [anthropic] has insufficient credits or quota`

Anthropic free credit grants are scoped to the Workbench / Claude Code, not
the API. Add real billing credit at
[console.anthropic.com → Billing](https://console.anthropic.com/settings/billing)
and create a fresh API key afterwards.

### `model: claude-3-5-haiku-20241022` not found

Some legacy models are not enabled on every tier. Switch to a current model:

```dotenv
AI_FILTERS_MODEL=claude-haiku-4-5-20251001
```

### "No matching filters" warning

The AI couldn't map the request to any available filter. Either:

- Add more filters to the table that cover the request, or
- Add hints in `ai-filters.instructions` (e.g. mappings between user terminology
  and filter names), or
- Make the request more specific.

### Verify config is loaded after `.env` changes

```bash
php artisan config:clear
```

### Inspecting the agent's raw response

A `Log::info('AiFilterAction', [...])` entry is written on every run. Check
`storage/logs/laravel.log` to see the prompt, the filter list sent to the AI,
and the structured response it returned.

## Architecture

```
packages/webdirect/ai-filters/
├── composer.json
├── config/
│   └── ai-filters.php
├── resources/
│   └── prompts/
│       └── filter-agent.md         # default system prompt template
└── src/
    ├── AiFiltersPlugin.php         # Filament Plugin contract
    ├── AiFiltersServiceProvider.php # config + prompt publish, provider key override
    ├── Actions/
    │   └── AiFilterAction.php      # the table header action
    └── Agents/
        └── FilterAgent.php         # laravel/ai agent w/ structured output
```

## Credits

- Original idea: [this video](https://www.youtube.com/watch?v=82ntd5LopoI) on
  the [Filament Daily](https://www.youtube.com/@FilamentDaily) channel by
  Povilas Korop. Go subscribe.
- Built on top of [`laravel/ai`](https://laravel.com/docs/13.x/ai-sdk) and
  [Filament v4](https://filamentphp.com).

## License

MIT
