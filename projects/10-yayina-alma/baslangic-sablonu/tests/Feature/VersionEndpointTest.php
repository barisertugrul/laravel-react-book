<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Dağıtım betiği /surum ile yeni sürümün gerçekten yanıt verdiğini doğrular */
class VersionEndpointTest extends TestCase
{
    public function test_it_reports_local_when_there_is_no_version_file(): void
    {
        $this->assertFileDoesNotExist(base_path('VERSION'));

        $this->get('/surum')->assertOk()->assertSeeText('yerel')
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    public function test_it_is_never_cached(): void
    {
        $this->assertStringContainsString('no-store', $this->get('/surum')->headers->get('Cache-Control'));
    }
}
