<?php

namespace App\Agent;

use App\Support\Signer;

/**
 * Eklentinin ayarlar sayfasında gösterdiği bağlantı anahtarı:
 *   AA1.<base64url(JSON)>   JSON = {"u": site adresi, "a": REST kökü, "t": tek kullanımlık jeton, "k": açık anahtar}
 *
 * Anahtarda gizli bir şey yok, jeton dışında: o da tek kullanımlık ve 15 dakikalık.
 */
final readonly class ConnectionKey
{
    public function __construct(
        public string $url,
        public string $apiUrl,
        public string $token,
        public string $publicKey, // ham 32 bayt
    ) {}

    /** Geçersizse null; hatayı kullanıcıya doğrulama mesajı olarak gösteririz */
    public static function parse(string $text): ?self
    {
        $text = trim($text);

        if (! str_starts_with($text, AgentProtocol::VERSION.'.')) {
            return null;
        }

        $json = Signer::base64UrlDecode(substr($text, strlen(AgentProtocol::VERSION) + 1));
        $data = $json === null ? null : json_decode($json, true);

        if (! is_array($data) || ! is_string($data['u'] ?? null) || ! is_string($data['a'] ?? null)
            || ! is_string($data['t'] ?? null) || ! is_string($data['k'] ?? null)) {
            return null;
        }

        $publicKey = base64_decode($data['k'], true);

        if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || ! self::isHttpUrl($data['u']) || ! self::isHttpUrl($data['a']) || strlen($data['t']) < 32) {
            return null;
        }

        return new self(rtrim($data['u'], '/'), $data['a'], $data['t'], $publicKey);
    }

    /** Panel bu adrese istek atacak: yalnızca http(s), başka şema (file://, gopher://) yok */
    private static function isHttpUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }
}
