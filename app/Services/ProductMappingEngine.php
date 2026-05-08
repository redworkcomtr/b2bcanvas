<?php

namespace App\Services;

use App\Models\ProductMapping;
use Illuminate\Support\Collection;

class ProductMappingEngine
{
    public function __construct(private readonly MappingRuleMatcher $matcher) {}

    /**
     * @param array<string, string|null> $item
     * @return array<string, mixed>
     */
    public function simulate(int $tenantId, array $item, ?int $excludeMappingId = null): array
    {
        $mappings = ProductMapping::query()
            ->forTenant($tenantId)
            ->with(['variant.productType', 'rules'])
            ->when($excludeMappingId, fn ($query) => $query->whereKeyNot($excludeMappingId))
            ->get();

        $matches = $this->matcher->matchingMappings($item, $mappings)
            ->map(fn (ProductMapping $mapping): array => $this->candidatePayload($item, $mapping, true))
            ->values();

        return [
            'matched_mapping' => $matches->first()['mapping'] ?? null,
            'candidates' => $matches,
            'conflicts' => $matches->count() > 1 ? $matches : [],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     * @return Collection<int, ProductMapping>
     */
    public function duplicateRuleMappings(int $tenantId, array $rules, ?int $excludeMappingId = null): Collection
    {
        $signatures = collect($rules)->map(fn (array $rule): string => $this->ruleSignature($rule))->values();

        return ProductMapping::query()
            ->forTenant($tenantId)
            ->with('rules')
            ->when($excludeMappingId, fn ($query) => $query->whereKeyNot($excludeMappingId))
            ->get()
            ->filter(function (ProductMapping $mapping) use ($signatures): bool {
                $existing = $mapping->rules->map(fn ($rule): string => $this->ruleSignature([
                    'field' => $rule->field,
                    'operator' => $rule->operator,
                    'value' => $rule->value,
                ]));

                return $signatures->intersect($existing)->isNotEmpty();
            })
            ->values();
    }

    /**
     * @param array<int, array<string, mixed>> $rules
     * @return array<int, array<string, mixed>>
     */
    public function ruleConflicts(int $tenantId, array $rules, ?int $excludeMappingId = null): array
    {
        return $this->duplicateRuleMappings($tenantId, $rules, $excludeMappingId)
            ->map(fn (ProductMapping $mapping): array => [
                'id' => $mapping->id,
                'name' => $mapping->name,
                'rules' => $mapping->rules,
            ])
            ->all();
    }

    /**
     * @param array<string, string|null> $item
     * @return array<string, mixed>
     */
    private function candidatePayload(array $item, ProductMapping $mapping, bool $matched): array
    {
        return [
            'mapping' => $mapping,
            'matched' => $matched,
            'score' => $this->matcher->score($item, $mapping),
            'max_priority' => (int) $mapping->rules->max('priority'),
            'rule_count' => $mapping->rules->count(),
        ];
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function ruleSignature(array $rule): string
    {
        return implode('|', [
            mb_strtolower((string) ($rule['field'] ?? '')),
            mb_strtolower((string) ($rule['operator'] ?? '')),
            mb_strtolower(trim((string) ($rule['value'] ?? ''))),
        ]);
    }
}
