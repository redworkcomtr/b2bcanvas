<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenant
{
    public function scopeForTenant(Builder $query, Tenant|int $tenant): Builder
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $query->where($this->getTable().'.tenant_id', $tenantId);
    }
}
