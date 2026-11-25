<?php

namespace App\Http\Requests;

use App\Agent\ConnectionKey;
use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

class RepairSiteRequest extends FormRequest
{
    private ?ConnectionKey $key = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['connection_key' => ['required', 'string', 'max:2000']];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->key = ConnectionKey::parse((string) $this->input('connection_key'));
            /** @var Site $site */
            $site = $this->route('site');

            if ($this->key === null) {
                $validator->errors()->add('connection_key', 'Bağlantı anahtarı geçersiz. Eklentinin ayarlar sayfasından yeni bir anahtar kopyalayın.');
            } elseif ($this->key->url !== $site->url) {
                // Başka bir sitenin anahtarı bu kayda bağlanamaz: geçmiş ve işlemler yanlış siteye geçerdi
                $validator->errors()->add('connection_key', "Bu anahtar {$this->key->url} adresine ait; bu kayıt {$site->url} için.");
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
        return ['connection_key' => 'bağlantı anahtarı'];
    }
}
