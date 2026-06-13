<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->runningInConsole() && ! app()->has('tenant_id')) {
            return;
        }

        if (app()->has('tenant_id')) {
            $tenantId = app()->make('tenant_id');
            if ($tenantId) {
                $builder->where($model->getTable().'.tenant_id', $tenantId);
            }
        }
    }
}
