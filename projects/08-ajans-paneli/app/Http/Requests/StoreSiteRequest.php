<?php

namespace App\Http\Requests;

use App\Agent\ConnectionKey;
use App\Tenancy\CurrentAgency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use LogicException;
use Illuminate\Validation\Validator;

class StoreSiteRequest extends FormRequest
{
    private ?ConnectionKey $key = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'connection_key' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->key = ConnectionKey::parse((string) $this->input('connection_key'));

            if ($this->key === null) {
                $validator->errors()->add('connection_key', 'Bağlantı anahtarı geçersiz. Eklentinin ayarlar sayfasından yeni bir anahtar kopyalayın.');

                return;
            }

            // DİKKAT: Rule::unique() ve exists gibi doğrulama kuralları doğrudan sorgu oluşturucuyu
            // kullanır; Eloquent global scope'ları (ajans filtresi) burada ÇALIŞMAZ. Ajansı biz ekleriz.
            $exists = DB::table('sites')
                ->where('agency_id', app(CurrentAgency::class)->id())
                ->where('url', $this->key->url)
                ->exists();

            if ($exists) {
                $validator->errors()->add('connection_key', "{$this->key->url} zaten bu ajansa bağlı.");
            }
        }];
    }

    public function connectionKey(): ConnectionKey
    {
        return $this->key ?? throw new LogicException('Önce doğrulama çalışmalı.');
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'site adı', 'connection_key' => 'bağlantı anahtarı'];
    }
}
