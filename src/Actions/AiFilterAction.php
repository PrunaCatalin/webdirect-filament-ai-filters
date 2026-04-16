<?php

namespace Webdirect\AiFilters\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Throwable;
use Webdirect\AiFilters\Agents\FilterAgent;

class AiFilterAction
{
    public static function make(string $name = 'aiFilter'): Action
    {
        $config = config('ai-filters.action');

        return Action::make($name)
            ->label($config['label'])
            ->icon($config['icon'])
            ->color('primary')
            ->modalHeading($config['modal_heading'])
            ->modalDescription($config['modal_description'])
            ->modalSubmitActionLabel('Apply')
            ->schema([
                Textarea::make('prompt')
                    ->hiddenLabel()
                    ->placeholder('e.g. users created last week whose email is verified')
                    ->required()
                    ->rows(4)
                    ->autosize(),
            ])
            ->action(function (array $data, $livewire, Table $table): void {
                self::handle($data['prompt'], $livewire, $table);
            });
    }

    protected static function handle(string $prompt, mixed $livewire, Table $table): void
    {
        $available = self::extractAvailableFilters($table);
        $current = $livewire->tableFilters ?? [];
        $searchableColumns = self::extractSearchableColumns($table);
        $currentSearch = (string) ($livewire->tableSearch ?? '');

        $agent = new FilterAgent(
            availableFilters: $available,
            currentFilters: $current,
            searchableColumns: $searchableColumns,
            currentSearch: $currentSearch,
            extraInstructions: config('ai-filters.instructions'),
        );

        try {
            $response = $agent->prompt(
                $prompt,
                provider: self::resolveProvider(),
                model: config('ai-filters.model'),
            );
        } catch (Throwable $e) {
            Notification::make()
                ->title('AI request failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $updates = $response['filters'] ?? [];
        $search = $response['search'] ?? null;

        Log::info('AiFilterAction', [
            'prompt' => $prompt,
            'available' => $available,
            'current' => $current,
            'searchable' => $searchableColumns,
            'currentSearch' => $currentSearch,
            'response' => method_exists($response, 'toArray') ? $response->toArray() : (array) $response,
        ]);

        if (empty($updates) && $search === null) {
            Notification::make()
                ->title('No matching filters')
                ->body('The AI could not translate your request into the available filters or search.')
                ->warning()
                ->send();

            return;
        }

        $newState = $current;

        foreach ($updates as $update) {
            $filterName = $update['filter'] ?? null;
            $key = $update['key'] ?? null;

            if (blank($filterName) || blank($key)) {
                continue;
            }

            // Prefer the "values" array when the AI returned one; this is what
            // multi-select filters expect on the livewire side.
            if (array_key_exists('values', $update) && is_array($update['values'])) {
                $newState[$filterName][$key] = array_values($update['values']);

                continue;
            }

            $value = $update['value'] ?? null;

            if (is_string($value) && in_array(strtolower($value), ['true', 'false'], true)) {
                $value = strtolower($value) === 'true';
            }

            $newState[$filterName][$key] = $value;
        }

        $livewire->tableFilters = $newState;

        if ($search !== null) {
            $livewire->tableSearch = $search;
        }

        $changes = count($updates) + ($search !== null ? 1 : 0);

        Notification::make()
            ->title('Filters applied')
            ->body($changes.' change(s) applied.')
            ->success()
            ->send();
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
                // skip columns that need a record to evaluate searchability
            }
        }

        return $cols;
    }

    protected static function resolveProvider(): ?Lab
    {
        $provider = config('ai-filters.provider');

        if (blank($provider)) {
            return null;
        }

        try {
            return Lab::from($provider);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function extractAvailableFilters(Table $table): array
    {
        $out = [];

        foreach ($table->getFilters() as $filter) {
            $entry = [
                'name' => $filter->getName(),
                'label' => (string) ($filter->getLabel() ?? $filter->getName()),
                'type' => class_basename($filter),
                'keys' => ['isActive'],
            ];

            if ($filter instanceof SelectFilter) {
                $isMultiple = method_exists($filter, 'isMultiple') && $filter->isMultiple();

                $entry['keys'] = [$isMultiple ? 'values' : 'value'];
                $entry['multiple'] = $isMultiple;

                try {
                    $entry['options'] = $filter->getOptions();
                } catch (Throwable) {
                    $entry['options'] = [];
                }
            } elseif ($filter instanceof TernaryFilter) {
                $entry['keys'] = ['value'];
                $entry['allowed'] = ['true', 'false', 'null'];
            }

            $out[] = $entry;
        }

        return $out;
    }
}
