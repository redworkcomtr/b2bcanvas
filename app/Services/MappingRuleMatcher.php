<?php

namespace App\Services;

use App\Models\ProductMapping;
use Illuminate\Support\Collection;

class MappingRuleMatcher
{
    /**
     * @param  array<string, string|null>  $item
     * @param  Collection<int, ProductMapping>  $mappings
     */
    public function bestMatch(array $item, Collection $mappings): ?ProductMapping
    {
        return $mappings
            ->filter(fn (ProductMapping $mapping): bool => $this->mappingMatches($item, $mapping))
            ->sortByDesc(fn (ProductMapping $mapping): int => (int) $mapping->rules->max('priority'))
            ->first();
    }

    /**
     * @param  array<string, string|null>  $item
     */
    public function mappingMatches(array $item, ProductMapping $mapping): bool
    {
        if ($mapping->rules->isEmpty()) {
            return false;
        }

        foreach ($mapping->rules as $rule) {
            $actual = mb_strtolower((string) ($item[$rule->field] ?? ''));
            $expected = mb_strtolower((string) $rule->value);

            $matches = match ($rule->operator) {
                'contains' => str_contains($actual, $expected),
                'starts_with' => str_starts_with($actual, $expected),
                'regex' => @preg_match($rule->value, (string) ($item[$rule->field] ?? '')) === 1,
                default => $actual === $expected,
            };

            if (! $matches) {
                return false;
            }
        }

        return true;
    }
}
