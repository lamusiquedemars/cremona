<?php

namespace Tests\Feature;

use Tests\TestCase;

class PwaTest extends TestCase
{
    public function test_manifest_exposes_the_private_dashboard_as_the_start_url(): void
    {
        $response = $this->get(route('pwa.manifest'));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json');

        $manifest = $response->json();

        $this->assertSame('/dashboard', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('Cremona', $manifest['name']);
        $this->assertCount(2, $manifest['icons']);
    }

    public function test_service_worker_and_install_icons_are_publicly_available(): void
    {
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('pwa/icons/cremona-192.png'));
        $this->assertFileExists(public_path('pwa/icons/cremona-512.png'));
    }
}
