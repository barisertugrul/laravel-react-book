<?php

namespace App\Models;

use App\Casts\MoneyCast;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $price
 * @property int $stock
 * @property bool $is_active
 */
#[Fillable(['name', 'slug', 'description', 'price', 'stock', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class,
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Slug verilmezse addan üret: "Etiyopya Yirgacheffe 250 g" → "etiyopya-yirgacheffe-250-g"
        static::saving(function (Product $product) {
            $product->slug = $product->slug ?: Str::slug($product->name);
        });
    }

    /** Adres çubuğunda id yerine slug: /urun/etiyopya-yirgacheffe-250-g */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Vitrinde görünen ürünler */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
