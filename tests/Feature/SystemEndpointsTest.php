<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Náhrada SSH: cron přes URL a údržba přes token */
class SystemEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cron_requires_valid_token(): void
    {
        config(['pap.cron_token' => 'tajny-cron']);

        $this->get('/_cron/spatny')->assertNotFound();
        $this->get('/_cron/tajny-cron')->assertOk()->assertJsonPath('ok', true);
        $this->assertNotNull(Cache::get('pap:tick:last'));
    }

    public function test_cron_disabled_without_configured_token(): void
    {
        config(['pap.cron_token' => null]);
        $this->get('/_cron/')->assertNotFound();
        $this->get('/_cron/cokoliv')->assertNotFound();
    }

    public function test_maintenance_requires_token_header(): void
    {
        config(['pap.maintenance_token' => 'tajny-maint']);

        $this->postJson('/_maint/migrate-status')->assertNotFound();
        $this->postJson('/_maint/migrate-status', [], ['X-Maintenance-Token' => 'spatny'])->assertNotFound();
        $this->postJson('/_maint/neexistuje', [], ['X-Maintenance-Token' => 'tajny-maint'])->assertNotFound();
        $this->postJson('/_maint/setup', [], ['X-Maintenance-Token' => 'tajny-maint'])->assertOk()->assertJsonPath('ok', true);
        $this->postJson('/_maint/migrate', [], ['X-Maintenance-Token' => 'tajny-maint'])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_health(): void
    {
        $this->getJson('/_health')->assertOk()->assertJsonPath('database', true)->assertJsonPath('storage', true);
    }
}
