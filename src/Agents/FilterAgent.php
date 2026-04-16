<?php

namespace Webdirect\AiFilters\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class FilterAgent implements Agent, HasStructuredOutput
{
    use Promptable;

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
        $available = json_encode($this->availableFilters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $current = json_encode($this->currentFilters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $searchable = json_encode($this->searchableColumns, JSON_UNESCAPED_SLASHES);
        $currentSearch = $this->currentSearch ?? '';

        $extra = filled($this->extraInstructions)
            ? "\n\nAdditional context from the application:\n{$this->extraInstructions}"
            : '';

        return <<<TXT
            You translate natural-language search requests into Filament table filter updates and an optional global search query.

            You receive:
              - The list of filters available on the table (with their type and the form field keys they accept)
              - The current filter state set by the user
              - The list of columns that support free-text search
              - The current global search value

            Available filters:
            {$available}

            Current filters:
            {$current}

            Searchable columns: {$searchable}
            Current global search: "{$currentSearch}"

            Return:
              - filters: a list of filter updates, each with:
                  - filter: the filter "name" exactly as listed in the available filters
                  - key: the form field key inside that filter. Read it from the "keys" array of the available filter:
                      * "value" for single-value SelectFilter and TernaryFilter
                      * "values" for SelectFilter where "multiple": true
                      * "isActive" for a simple toggle/checkbox Filter
                      * a custom form field name (e.g. "from", "until") for custom-schema filters
                  - value: a SINGLE string value (use this when key is "value", "isActive", or a custom field). Use null to clear.
                  - values: an ARRAY of string values (use this ONLY when the filter has "multiple": true and key is "values"). Each entry must match one of the listed options. Use null to clear.
                  Use exactly ONE of "value" or "values" per update, never both.
              - search: a string to type into the table's global search box, or null to leave it unchanged. Use this for free-text matches (names, emails, identifiers) that map to one of the searchable columns.

            Rules:
              - Only return filters that exist in the available list. Never invent a filter or a key.
              - For SelectFilter, every value must match one of the listed options exactly.
              - For multi-select filters, return the "values" array even when there is only one selection.
              - To clear a filter, set its value to null.
              - To clear the global search, set search to "" (empty string). Use null to leave it unchanged.
              - PREFER filters over global search whenever a matching filter exists for the user's intent. Examples:
                  * "search for amira" -> if a filter named "name" exists, use that filter (key "value", value "amira") instead of the global search.
                  * "users from gmail.com" -> if an "email_domain" filter exists, use it; otherwise fall back to global search on email.
                  * "Dr. ..." -> if a "name_prefix" filter has "Dr." as an option, use it.
              - Use the global search ONLY when no filter can express the user's intent but a searchable column can.
              - It is acceptable to return an empty filters list while still setting search, but only as a last resort.{$extra}
            TXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'filters' => $schema->array()
                ->items(
                    $schema->object(fn ($schema) => [
                        'filter' => $schema->string()->required(),
                        'key' => $schema->string()->required(),
                        'value' => $schema->string()->nullable(),
                        'values' => $schema->array()
                            ->items($schema->string())
                            ->nullable(),
                    ])
                )
                ->required(),
            'search' => $schema->string()->nullable(),
        ];
    }
}
