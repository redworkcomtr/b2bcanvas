<?php

namespace Tests\Unit;

use App\Models\MappingRule;
use App\Models\ProductMapping;
use App\Services\MappingRuleMatcher;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class MappingRuleMatcherTest extends TestCase
{
    public function test_it_selects_highest_priority_matching_mapping(): void
    {
        $low = new ProductMapping(['name' => 'Low match']);
        $low->setRelation('rules', new Collection([
            new MappingRule(['field' => 'sku', 'operator' => 'contains', 'value' => 'CANVAS', 'priority' => 10]),
        ]));

        $high = new ProductMapping(['name' => 'High match']);
        $high->setRelation('rules', new Collection([
            new MappingRule(['field' => 'sku', 'operator' => 'contains', 'value' => 'CANVAS', 'priority' => 90]),
        ]));

        $match = (new MappingRuleMatcher())->bestMatch(['sku' => 'CUSTOM-CANVAS-36X24'], new Collection([$low, $high]));

        $this->assertSame('High match', $match?->name);
    }

    public function test_it_rejects_non_matching_rules(): void
    {
        $mapping = new ProductMapping(['name' => 'Framed']);
        $mapping->setRelation('rules', new Collection([
            new MappingRule(['field' => 'name', 'operator' => 'contains', 'value' => 'Framed Art Print', 'priority' => 10]),
        ]));

        $this->assertFalse((new MappingRuleMatcher())->mappingMatches(['name' => 'Loose Canvas'], $mapping));
    }
}
