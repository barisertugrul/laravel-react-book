<?php

namespace Tests\Unit;

use App\Agent\AgentProtocol;
use App\Support\Signer;
use PHPUnit\Framework\TestCase;

/**
 * Panel ve eklenti protokolü AYRI dosyalarda yazar. İkisi bir bayt bile ayrışırsa hiçbir imza
 * tutmaz; bu test eklentinin sınıfını yükleyip aynı girdilerden aynı metni ürettiklerini denetler.
 */
class ProtocolParityTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        defined('ABSPATH') || define('ABSPATH', '/tmp/');
        require_once dirname(__DIR__, 2).'/wp-plugin/ajans-asistani/includes/class-protocol.php';
    }

    public function test_request_messages_match(): void
    {
        $this->assertSame(
            AgentProtocol::requestMessage('get', 'ajans-asistani/v1/health', '1763900000', str_repeat('a', 32), ''),
            \Ajans_Asistani_Protocol::request_message('GET', '/ajans-asistani/v1/health', '1763900000', str_repeat('a', 32), ''),
        );
    }

    public function test_response_messages_match(): void
    {
        $this->assertSame(
            AgentProtocol::responseMessage('abc', 200, '1763900000', '{"ok":true}'),
            \Ajans_Asistani_Protocol::response_message('abc', 200, '1763900000', '{"ok":true}'),
        );
    }

    public function test_base64url_round_trip(): void
    {
        $bytes = random_bytes(64);

        $this->assertSame($bytes, \Ajans_Asistani_Protocol::base64url_decode(Signer::base64UrlEncode($bytes)));
    }
}
