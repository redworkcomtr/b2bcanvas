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
        return $this->matchingMappings($item, $mappings)
            ->first();
    }

    /**
     * @param  array<string, string|null>  $item
     * @param  Collection<int, ProductMapping>  $mappings
     * @return Collection<int, ProductMapping>
     */
    public function matchingMappings(array $item, Collection $mappings): Collection
    {
        return $mappings
            ->filter(fn (ProductMapping $mapping): bool => $this->mappingMatches($item, $mapping))
            ->sortByDesc(fn (ProductMapping $mapping): int => $this->score($item, $mapping))
            ->values();
    }

    /**
     * @param  array<string, string|null>  $item
     */
    public function score(array $item, ProductMapping $mapping): int
    {
        if (! $this->mappingMatches($item, $mapping)) {
            return 0;
        }

        return (int) $mapping->rules->sum(fn ($rule): int => (int) $rule->priority);
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
            if (! $this->ruleMatches($item, $rule->field, $rule->operator, $rule->value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, string|null>  $item
     */
    public function ruleMatches(array $item, string $field, string $operator, string $value): bool
    {
        $rawActual = (string) ($item[$field] ?? '');
        $actual = mb_strtolower($rawActual);
        $expected = mb_strtolower($value);

        return match ($operator) {
            'contains' => str_contains($actual, $expected),
            'starts_with' => str_starts_with($actual, $expected),
            'regex' => @preg_match($value, $rawActual) === 1,
            default => $actual === $expected,
        };
    }
}
