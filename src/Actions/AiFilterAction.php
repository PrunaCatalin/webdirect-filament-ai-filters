<?php

namespace Webdirect\AiFilters\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Throwable;
use Webdirect\AiFilters\Agents\FilterAgent;

final class AiFilterAction
{
    private function __construct() {}

    public static function make(string $name = 'aiFilter'): Action
    {
        $config = config('ai-filters.action', []);

        return Action::make($name)
            ->label($config['label'] ?? __('ai-filters::ai-filters.action.label'))
            ->icon($config['icon'] ?? 'heroicon-o-sparkles')
            ->color('primary')
            ->modalHeading($config['modal_heading'] ?? __('ai-filters::ai-filters.action.modal_heading'))
            ->modalDescription($config['modal_description'] ?? __('ai-filters::ai-filters.action.modal_description'))
            ->modalSubmitActionLabel(__('ai-filters::ai-filters.action.modal_submit'))
            ->schema([
                Textarea::make('prompt')
                    ->hiddenLabel()
                    ->placeholder(__('ai-filters::ai-filters.action.placeholder'))
                    ->required()
                    ->rows(4)
                    ->autosize(),
            ])
            ->action(fn (array $data, $livewire, Table $table) => self::handle($data['prompt'], $livewire, $table));
    }

    protected static function handle(string $prompt, mixed $livewire, Table $table): void
    {
        $available = self::extractAvailableFilters($table);
        $searchable = self::extractSearchableColumns($table);
        $currentFilters = $livewire->tableFilters ?? [];
        $currentSearch = (string) ($livewire->tableSearch ?? '');

        $response = self::runAgent($prompt, $available, $currentFilters, $searchable, $currentSearch);

        if ($response === null) {
            return;
        }

        $updates = $response['filters'] ?? [];
        $search = $response['search'] ?? null;

        self::logInvocation($prompt, $available, $currentFilters, $searchable, $currentSearch, $response);

        if (empty($updates) && $search === null) {
            self::notify(
                __('ai-filters::ai-filters.notifications.no_match_title'),
                __('ai-filters::ai-filters.notifications.no_match_body'),
                'warning',
            );

            return;
        }

        $livewire->tableFilters = self::applyUpdates($currentFilters, $updates, $available);

        if ($search !== null) {
            $livewire->tableSearch = $search;
        }

        $changes = count($updates) + ($search !== null ? 1 : 0);

        self::notify(
            __('ai-filters::ai-filters.notifications.applied_title'),
            __('ai-filters::ai-filters.notifications.applied_body', ['count' => $changes]),
            'success',
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $available
     * @param  array<string, array<string, mixed>>  $current
     * @param  array<int, string>  $searchable
     */
    protected static function runAgent(
        string $prompt,
        array $available,
        array $current,
        array $searchable,
        string $currentSearch,
    ): mixed {
        $agent = new FilterAgent(
            availableFilters: $available,
            currentFilters: $current,
            searchableColumns: $searchable,
            currentSearch: $currentSearch,
            extraInstructions: config('ai-filters.instructions'),
        );

        try {
            return $agent->prompt(
                $prompt,
                provider: self::resolveProvider(),
                model: config('ai-filters.model'),
            );
        } catch (Throwable $e) {
            self::notify(
                __('ai-filters::ai-filters.notifications.error_title'),
                $e->getMessage(),
                'danger',
            );

            return null;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $current
     * @param  array<int, array<string, mixed>>  $updates
     * @param  array<int, array<string, mixed>>  $available
     * @return array<string, array<string, mixed>>
     */
    protected static function applyUpdates(array $current, array $updates, array $available): array
    {
        $filtersByName = [];
        foreach ($available as $entry) {
            $filtersByName[$entry['name']] = $entry;
        }

        foreach ($updates as $update) {
            $filterName = $update['filter'] ?? null;
            $key = $update['key'] ?? null;

            if (blank($filterName) || blank($key)) {
                continue;
            }

            $fieldMeta = self::findField($filtersByName[$filterName] ?? null, $key);

            if (isset($update['values']) && is_array($update['values'])) {
                $current[$filterName][$key] = array_values($update['values']);

                continue;
            }

            $current[$filterName][$key] = self::normalizeValue($update['value'] ?? null, $fieldMeta);
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>|null  $filter
     * @return array<string, mixed>|null
     */
    protected static function findField(?array $filter, string $key): ?array
    {
        foreach ($filter['fields'] ?? [] as $field) {
            if (($field['name'] ?? null) === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $fieldMeta
     */
    protected static function normalizeValue(mixed $value, ?array $fieldMeta = null): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $type = $fieldMeta['type'] ?? null;

        // Boolean-ish fields: Toggle, Checkbox, or TernaryFilter-style Select with boolean options
        if (in_array($type, ['Toggle', 'Checkbox'], true) || ($fieldMeta['booleanLike'] ?? false)) {
            return match (strtolower($value)) {
                'true', '1', 'yes' => true,
                'false', '0', 'no' => false,
                '', 'null' => null,
                default => $value,
            };
        }

        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            default => $value,
        };
    }

    protected static function notify(string $title, string $body, string $level): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->{$level}()
            ->send();
    }

    /**
     * @param  array<int, array<string, mixed>>  $available
     * @param  array<string, array<string, mixed>>  $current
     * @param  array<int, string>  $searchable
     */
    protected static function logInvocation(
        string $prompt,
        array $available,
        array $current,
        array $searchable,
        string $currentSearch,
        mixed $response,
    ): void {
        Log::info('AiFilterAction', [
            'prompt' => $prompt,
            'available' => $available,
            'current' => $current,
            'searchable' => $searchable,
            'currentSearch' => $currentSearch,
            'response' => method_exists($response, 'toArray') ? $response->toArray() : (array) $response,
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected static function extractSearchableColumns(Table $table): array
    {
        $cols = [];

        foreach ($table->getColumns() as $column) {
            try {
                if (method_exists($column, 'isSearchable') && $column->isSearchable()) {
                    $cols[] = $column->getName();
                }
            } catch (Throwable) {
                // skip columns that require a record to evaluate searchability
            }
        }

        return $cols;
    }

    protected static function resolveProvider(): ?Lab
    {
        $provider = config('ai-filters.provider');

        return blank($provider) ? null : Lab::tryFrom($provider);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function extractAvailableFilters(Table $table): array
    {
        return array_values(array_map(self::describeFilter(...), $table->getFilters()));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function describeFilter(BaseFilter $filter): array
    {
        $fields = [];

        try {
            foreach ($filter->getSchemaComponents() as $component) {
                if (! $component instanceof Component) {
                    continue;
                }

                $described = self::describeComponent($component);

                if ($described !== null) {
                    $fields[] = $described;
                }
            }
        } catch (Throwable) {
            // some filters evaluate their schema against a record and throw without one;
            // fall back to an empty field list so the filter is still exposed to the AI.
        }

        return [
            'name' => $filter->getName(),
            'label' => (string) ($filter->getLabel() ?? $filter->getName()),
            'type' => class_basename($filter),
            'keys' => array_column($fields, 'name'),
            'fields' => $fields,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function describeComponent(Component $component): ?array
    {
        if (! $component instanceof Field) {
            return null;
        }

        $data = [
            'name' => $component->getName(),
            'type' => class_basename($component),
            'label' => (string) ($component->getLabel() ?? $component->getName()),
            'required' => self::safeBool(fn () => $component->isRequired()),
        ];

        if ($component instanceof Select || $component instanceof Radio) {
            $options = self::safeCall(fn () => $component->getOptions()) ?? [];
            $data['options'] = self::normalizeOptions($options);

            if ($component instanceof Select) {
                $data['multiple'] = self::safeBool(fn () => $component->isMultiple());

                // TernaryFilter renders a Select with boolean-style options.
                $data['booleanLike'] = self::looksBoolean($data['options']);
            }
        }

        if ($component instanceof TextInput) {
            $data['inputType'] = self::safeCall(fn () => $component->getType()) ?? 'text';
        }

        // DatePicker and TimePicker extend DateTimePicker, so subclasses must be checked first.
        $format = match (true) {
            $component instanceof DatePicker => 'date',
            $component instanceof TimePicker => 'time',
            $component instanceof DateTimePicker => 'datetime',
            default => null,
        };

        if ($format !== null) {
            $data['inputFormat'] = $format;
        }

        $placeholder = self::safeCall(fn () => method_exists($component, 'getPlaceholder') ? $component->getPlaceholder() : null);
        if (filled($placeholder)) {
            $data['placeholder'] = (string) $placeholder;
        }

        $rules = self::normalizeRules(self::safeCall(fn () => $component->getValidationRules()) ?? []);
        if (! empty($rules)) {
            $data['rules'] = $rules;
        }

        return $data;
    }

    /**
     * @param  array<string|int, mixed>  $options
     * @return array<string|int, mixed>
     */
    protected static function normalizeOptions(array $options): array
    {
        $out = [];

        foreach ($options as $key => $label) {
            $out[$key] = is_scalar($label) ? (string) $label : $label;
        }

        return $out;
    }

    /**
     * @param  array<string|int, mixed>  $options
     */
    protected static function looksBoolean(array $options): bool
    {
        if (count($options) === 0 || count($options) > 3) {
            return false;
        }

        $keys = array_map(fn ($k) => is_string($k) ? strtolower($k) : $k, array_keys($options));

        $booleanKeySets = [
            [1, 0],
            ['1', '0'],
            [true, false],
            ['true', 'false'],
            ['yes', 'no'],
        ];

        foreach ($booleanKeySets as $set) {
            if (count(array_intersect($keys, $set)) >= 2) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>  $rules
     * @return array<int, string>
     */
    protected static function normalizeRules(array $rules): array
    {
        $out = [];

        foreach ($rules as $rule) {
            if (is_string($rule) && $rule !== '') {
                $out[] = $rule;

                continue;
            }

            if (is_object($rule) && method_exists($rule, '__toString')) {
                try {
                    $string = (string) $rule;

                    if ($string !== '') {
                        $out[] = $string;
                    }
                } catch (Throwable) {
                    // skip rules that need runtime context to stringify
                }
            }
        }

        return array_values(array_unique($out));
    }

    protected static function safeCall(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable) {
            return null;
        }
    }

    protected static function safeBool(callable $fn): bool
    {
        return (bool) self::safeCall($fn);
    }
}
