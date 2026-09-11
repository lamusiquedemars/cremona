<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_logout_url_shows_a_secure_confirmation_instead_of_a_405(): void
    {
        $user = User::factory()->platformAdministrator()->create();

        $this->actingAs($user)
            ->get('/platform/logout')
            ->assertOk()
            ->assertSee('Se déconnecter ?')
            ->assertSee('method="post"', false);

        $this->assertAuthenticatedAs($user);
    }

    public function test_guest_is_sent_to_the_panel_login_instead_of_the_logout_confirmation(): void
    {
        $this->get('/platform/logout')->assertRedirect('/platform/login');
    }
}
