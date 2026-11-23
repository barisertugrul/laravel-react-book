<?php

namespace App\Actions;

use App\Agent\AgentClient;
use App\Agent\AgentException;
use App\Agent\AgentProtocol;
use App\Agent\ConnectionKey;
use App\Models\Site;
use App\Support\Signer;
use App\Tenancy\CurrentAgency;
use Throwable;

/**
 * Bağlantı anahtarıyla siteyi panele bağlar.
 *
 * 1. Bu siteye özel yeni bir anahtar çifti üretilir (her site ayrı: biri sızarsa ötekiler etkilenmez).
 * 2. Eklentinin /pair ucuna jeton + panelin açık anahtarı gönderilir; istek panelin yeni gizli
 *    anahtarıyla imzalıdır, yani "bu açık anahtarın sahibi benim" diye de kanıtlar.
 * 3. Eklenti yanıtını KENDİ gizli anahtarıyla imzalar; bağlantı anahtarındaki açık anahtarla
 *    doğrularız. Böylece konuştuğumuz uç, anahtarı gösteren eklentinin ta kendisidir.
 */
class PairSite
{
    public function __construct(private AgentClient $agent, private CurrentAgency $current) {}

    /** @throws AgentException */
    public function handle(string $name, ConnectionKey $key): Site
    {
        [$secret, $public] = Signer::generateKeyPair();

        $site = Site::create([
            'name' => $name,
            'url' => $key->url,
            'api_url' => $key->apiUrl,
            'agent_public_key' => base64_encode($key->publicKey),
            'panel_secret_key' => base64_encode($secret),
        ]);

        try {
            $this->agent->send($site, 'POST', AgentProtocol::ROUTE_PAIR, [
                'token' => $key->token,
                'site_id' => $site->id,
                'panel_public_key' => base64_encode($public),
                'panel_url' => config('app.url'),
                'agency' => $this->current->get()?->name,
            ]);
        } catch (Throwable $e) {
            // El sıkışma olmadıysa veritabanında yarım kalmış bir site bırakmayalım
            $site->delete();

            throw $e;
        }

        return $site;
    }
}
