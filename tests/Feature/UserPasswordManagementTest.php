<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserPasswordManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_platform_administrator_can_change_a_users_password_from_the_explicit_action(): void
    {
        $administrator = User::factory()->platformAdministrator()->create();
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
            'remember_token' => 'old-remember-token',
        ]);

        $this->actingAs($administrator);
        Filament::setCurrentPanel(Filament::getPanel('platform'));

        Livewire::test(EditUser::class, ['record' => $user->getKey()])
            ->callAction('changePassword', data: [
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertHasNoActionErrors();

        $user->refresh();

        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertNotSame('old-remember-token', $user->remember_token);
    }
}
