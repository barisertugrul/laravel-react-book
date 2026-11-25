<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $summary
 * @property string|null $description
 * @property bool $is_active
 */
#[Fillable(['name', 'slug', 'summary', 'description', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<Plan, $this> */
    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class)->orderBy('sort');
    }

    /**
     * Ürünün planları üzerinden satılmış lisanslar (Bölüm 10: slug kilidi için)
     *
     * @return HasManyThrough<License, Plan, $this>
     */
    public function licenses(): HasManyThrough
    {
        return $this->hasManyThrough(License::class, Plan::class);
    }
}
