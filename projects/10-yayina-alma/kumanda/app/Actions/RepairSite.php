<?php

namespace App\Actions;

use App\Agent\AgentClient;
use App\Agent\AgentException;
use App\Agent\AgentProtocol;
use App\Agent\ConnectionKey;
use App\Models\Site;
use App\Support\Signer;
use App\Tenancy\CurrentAgency;

/**
 * Var olan bir siteyi yeni bir bağlantı anahtarıyla YENİDEN bağlar (anahtar yenileme).
 *
 * Ne zaman gerekir? Eklentinin anahtar çifti değiştiğinde (site yeniden kuruldu, eklenti silinip
 * kuruldu, Playground yeniden başladı) ya da eklentinin bağlantısı kesildiğinde. Siteyi silip
 * yeniden eklemek kontrol geçmişini, işlem kayıtlarını ve site numarasını kaybettirirdi.
 *
 * Eşleştirmeden (PairSite) farkı: kayıt zaten var. Yeni anahtarlar önce KAYDEDİLMEMİŞ bir kopyada
 * denenir; eklenti el sıkışmayı onaylarsa kayda yazılır. Başarısız olursa eski anahtarlar yerinde
 * kalır: yarım kalmış bir yenileme siteyi büsbütün bağlantısız bırakmaz.
 */
class RepairSite
{
    public function __construct(private AgentClient $agent, private CurrentAgency $current) {}

    /** @throws AgentException */
    public function handle(Site $site, ConnectionKey $key): Site
    {
        // Anahtar yenilemede panelin anahtar çifti de yenilenir: eskisi bir yere sızdıysa artık işe yaramaz
        [$secret, $public] = Signer::generateKeyPair();

        $candidate = $site->replicate()->forceFill([
            'api_url' => $key->apiUrl,
            'agent_public_key' => base64_encode($key->publicKey),
            'panel_secret_key' => base64_encode($secret),
        ]);
        $candidate->id = $site->id; // eklentiye AYNI site numarası bildirilir

        $this->agent->send($candidate, 'POST', AgentProtocol::ROUTE_PAIR, [
            'token' => $key->token,
            'site_id' => $site->id,
            'panel_public_key' => base64_encode($public),
            'panel_url' => config('app.url'),
            'agency' => $this->current->get()?->name,
        ]);

        $site->forceFill([
            'api_url' => $candidate->api_url,
            'agent_public_key' => $candidate->agent_public_key,
            'panel_secret_key' => $candidate->panel_secret_key,
        ])->save();

        return $site;
    }
}
