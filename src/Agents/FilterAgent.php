<?php

namespace Webdirect\AiFilters\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use RuntimeException;

class FilterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public const DEFAULT_PROMPT_PATH = __DIR__.'/../../resources/prompts/filter-agent.md';

    /**
     * @param  array<int, array{name: string, label: string, type: string, keys: array<int, string>, options?: array<string, string>}>  $availableFilters
     * @param  array<string, array<string, mixed>>  $currentFilters
     * @param  array<int, string>  $searchableColumns
     */
    public function __construct(
        public array $availableFilters = [],
        public array $currentFilters = [],
        public array $searchableColumns = [],
        public ?string $currentSearch = null,
        public ?string $extraInstructions = null,
    ) {}

    public function instructions(): string
    {
        $template = $this->loadTemplate();

        $extra = filled($this->extraInstructions)
            ? "\n\nAdditional context from the application:\n{$this->extraInstructions}"
            : '';

        return strtr($template, [
            '{{available}}' => $this->encodePretty($this->availableFilters),
            '{{current}}' => $this->encodePretty($this->currentFilters),
            '{{searchable}}' => json_encode($this->searchableColumns, JSON_UNESCAPED_SLASHES),
            '{{currentSearch}}' => $this->currentSearch ?? '',
            '{{extra}}' => $extra,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'filters' => $schema->array()
                ->items($schema->object(fn ($schema) => [
                    'filter' => $schema->string()->required(),
                    'key' => $schema->string()->required(),
                    'value' => $schema->string()->nullable(),
                    'values' => $schema->array()->items($schema->string())->nullable(),
                ]))
                ->required(),
            'search' => $schema->string()->nullable(),
        ];
    }

    protected function loadTemplate(): string
    {
        $path = $this->resolveTemplatePath();

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("FilterAgent prompt template not found or not readable at [{$path}].");
        }

        return file_get_contents($path);
    }

    protected function resolveTemplatePath(): string
    {
        if (function_exists('app') && app()->bound('config')) {
            $configured = config('ai-filters.prompt_path');

            if (filled($configured)) {
                return $configured;
            }
        }

        return self::DEFAULT_PROMPT_PATH;
    }

    protected function encodePretty(mixed $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
