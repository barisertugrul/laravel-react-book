<?php

namespace App\Tenancy;

use App\Models\Agency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Bir ajansa ait modeller (Site, SiteCheck) bu trait'i kullanır:
 *  - okurken: yalnızca etkin ajansın kayıtları (AgencyScope)
 *  - yazarken: agency_id etkin ajanstan doldurulur; formdan gelemez (fillable'da yok)
 *
 * @property int $agency_id
 * @property-read Agency $agency
 */
trait BelongsToAgency
{
    /** Laravel, "boot" + trait adı biçimindeki statik metodu model açılırken çağırır */
    public static function bootBelongsToAgency(): void
    {
        static::addGlobalScope(new AgencyScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('agency_id') === null) {
                $model->setAttribute('agency_id', app(CurrentAgency::class)->id()
                    ?? throw new LogicException(class_basename($model).' ajans bağlamı olmadan oluşturulamaz.'));
            }
        });
    }

    /** @return BelongsTo<Agency, $this> */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * Ajans filtresi OLMADAN sorgu: zamanlayıcı gibi tüm kiracıları gezen sistem kodu için.
     * Kod incelemesinde aranacak tek kelime: acrossAgencies.
     *
     * @return Builder<static>
     */
    public static function acrossAgencies(): Builder
    {
        return static::withoutGlobalScope(AgencyScope::class);
    }
}
