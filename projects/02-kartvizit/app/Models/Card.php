<?php

namespace App\Models;

use App\Enums\CardTheme;
use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property string $full_name
 * @property string|null $title
 * @property string|null $company
 * @property string|null $bio
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property string|null $address
 * @property string|null $avatar_path
 * @property CardTheme $theme
 * @property bool $is_published
 * @property int $view_count
 */
#[Fillable(['slug', 'full_name', 'title', 'company', 'bio', 'phone', 'email', 'website', 'address', 'theme', 'is_published', 'avatar_path'])]
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;

    /** Kullanıcıların kart adresi olarak alamayacağı kelimeler. */
    public const RESERVED_SLUGS = ['admin', 'api', 'login', 'register', 'dashboard', 'settings', 'cards', 'plan'];

    protected $appends = ['avatar_url', 'public_url'];

    protected function casts(): array
    {
        return [
            'theme' => CardTheme::class,
            'is_published' => 'boolean',
            'view_count' => 'integer',
        ];
    }

    /**
     * Adres değiştiğinde eskisini geçmişe yaz (bkz. "Güvenlik incelemesi").
     * Kart eski adresine geri dönerse o adres geçmişten silinir.
     */
    protected static function booted(): void
    {
        static::updating(function (Card $card) {
            if (! $card->isDirty('slug')) {
                return;
            }

            $card->slugHistories()->updateOrCreate(['slug' => $card->getOriginal('slug')]);
            $card->slugHistories()->where('slug', $card->slug)->delete();
        });
    }

    /** @return HasMany<CardSlugHistory, $this> */
    public function slugHistories(): HasMany
    {
        return $this->hasMany(CardSlugHistory::class);
    }

    /** Bu adres başka bir kartın eski adresi mi? */
    public static function slugBelongsToAnotherCardsHistory(string $slug, ?int $cardId = null): bool
    {
        return CardSlugHistory::where('slug', $slug)
            ->when($cardId, fn ($q) => $q->where('card_id', '!=', $cardId))
            ->exists();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CardLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(CardLink::class)->orderBy('sort_order');
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : null);
    }

    protected function publicUrl(): Attribute
    {
        return Attribute::get(fn (): string => route('cards.public', $this->slug));
    }

    /**
     * Ad soyaddan benzersiz bir adres üretir: "Barış Ertuğrul" → "baris-ertugrul",
     * alınmışsa "baris-ertugrul-2", "baris-ertugrul-3"...
     */
    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'kart';
        $slug = $base;
        $i = 2;

        while (in_array($slug, self::RESERVED_SLUGS, true)
            || static::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()
            || self::slugBelongsToAnotherCardsHistory($slug, $ignoreId)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
