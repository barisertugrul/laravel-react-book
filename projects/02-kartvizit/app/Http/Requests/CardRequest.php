<?php

namespace App\Http\Requests;

use App\Enums\CardTheme;
use App\Models\Card;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CardRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Yetki kontrolünü controller'da policy ile yapıyoruz.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Card|null $card */
        $card = $this->route('card');

        return [
            'full_name' => ['required', 'string', 'max:80'],
            'slug' => [
                'nullable', 'string', 'min:3', 'max:60', 'lowercase', 'alpha_dash:ascii',
                Rule::notIn(Card::RESERVED_SLUGS),
                Rule::unique('cards', 'slug')->ignore($card),
            ],
            'title' => ['nullable', 'string', 'max:80'],
            'company' => ['nullable', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:300'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +()\-]+$/'],
            'email' => ['nullable', 'email', 'max:120'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:160'],
            // Kullanıcı yalnızca planının izin verdiği temaları seçebilir.
            'theme' => ['required', Rule::enum(CardTheme::class)->only($this->user()->plan->themes())],
            'is_published' => ['boolean'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'remove_avatar' => ['boolean'],
            'links' => ['array', 'max:8'],
            'links.*.label' => ['required', 'string', 'max:30'],
            'links.*.url' => ['required', 'url', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'links.*.label' => 'bağlantı adı',
            'links.*.url' => 'bağlantı adresi',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.alpha_dash' => 'Adres yalnızca küçük harf, rakam ve tire içerebilir.',
            'slug.not_in' => 'Bu adres sistem tarafından kullanılıyor, başka bir adres seçin.',
            'theme.enum' => 'Seçtiğiniz tema planınızda yok.',
            'phone.regex' => 'Telefon yalnızca rakam, boşluk, +, ( ) ve - içerebilir.',
        ];
    }

    /** Kart tablosuna yazılacak alanlar. */
    public function cardData(): array
    {
        $data = $this->safe()->except(['avatar', 'remove_avatar', 'links']);

        if (blank($data['slug'] ?? null)) {
            $data['slug'] = Card::uniqueSlug($data['full_name'], $this->route('card')?->id);
        }

        return $data;
    }

    /** @return list<array{label: string, url: string, sort_order: int}> */
    public function links(): array
    {
        return collect($this->validated('links', []))
            ->values()
            ->map(fn (array $link, int $i) => [...$link, 'sort_order' => $i])
            ->all();
    }
}
