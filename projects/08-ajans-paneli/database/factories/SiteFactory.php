<?php

namespace Database\Factories;

use App\Models\Agency;
use App\Models\Site;
use App\Support\Signer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory'ler toplu atama korumasını devre dışı bırakır: agency_id burada doğrudan verilebilir.
 *
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    public function definition(): array
    {
        $host = fake()->unique()->domainWord().'.test';
        [, $agentPublic] = Signer::generateKeyPair();
        [$panelSecret] = Signer::generateKeyPair();

        return [
            'agency_id' => Agency::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'url' => "https://{$host}",
            'api_url' => "https://{$host}/wp-json/",
            'agent_public_key' => base64_encode($agentPublic),
            'panel_secret_key' => base64_encode($panelSecret),
        ];
    }

    public function forAgency(Agency $agency): static
    {
        return $this->state(['agency_id' => $agency->id]);
    }
}
