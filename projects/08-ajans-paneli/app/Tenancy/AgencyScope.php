<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope: modelin HER sorgusuna "where agency_id = <etkin ajans>" ekler.
 */
class AgencyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $agencyId = app(CurrentAgency::class)->id();

        // Bağlam yoksa hiçbir kayıt dönmez ("kapalı başarısızlık", fail-closed).
        // Tüm ajanslara bakması gereken kod bunu açıkça söyler: Site::acrossAgencies()
        if ($agencyId === null) {
            $builder->whereRaw('0 = 1');

            return;
        }

        $builder->where($model->qualifyColumn('agency_id'), $agencyId);
    }
}
