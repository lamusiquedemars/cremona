<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Laravel\Passport\Client;
use Tests\TestCase;

class OAuthAuthorizationViewTest extends TestCase
{
    public function test_the_oauth_consent_screen_renders_without_a_livewire_component(): void
    {
        $client = new Client;
        $client->id = 'site-demo-client';
        $client->name = 'Maracuja Site Demo';

        $html = view('auth.oauth.authorize', [
            'client' => $client,
            'request' => Request::create('/oauth/authorize?state=state-for-test'),
            'authToken' => 'authorization-token-for-test',
        ])->render();

        $this->assertStringContainsString('Ouvrir la gestion depuis Maracuja Site Demo', $html);
        $this->assertStringContainsString('action="http://cremona.test/oauth/authorize"', $html);
        $this->assertStringContainsString('name="auth_token"', $html);
        $this->assertStringContainsString('Continuer', $html);
    }
}
