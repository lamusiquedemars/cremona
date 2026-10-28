<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationModuleAccess;
use App\Services\OrganizationModuleRegistry;
use App\Services\UserPasswordSetupLink;
use App\Tenancy\OrganizationContext;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class UserAccessProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_membership_without_module_customization_inherits_all_active_modules(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        app(OrganizationModuleRegistry::class)->sync($organization, ['crm', 'quotes']);

        $user->organizations()->attach($organization, ['role' => OrganizationRole::Collaborator->value]);

        app(OrganizationContext::class)->run($organization, function () use ($user, $organization): void {
            $this->assertTrue($user->canAccessOrganizationModule('crm', $organization));
            $this->assertTrue($user->canManageOrganizationModule('quotes', $organization));
            $this->assertTrue(app(OrganizationModuleAccess::class)->accessibleBy($user, 'crm'));
        });
    }

    public function test_a_custom_module_list_is_really_enforced(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        app(OrganizationModuleRegistry::class)->sync($organization, ['crm', 'quotes']);
        $user->organizations()->attach($organization, [
            'role' => OrganizationRole::Collaborator->value,
            'permissions' => ['modules' => ['crm']],
        ]);

        $this->assertTrue($user->canAccessOrganizationModule('crm', $organization));
        $this->assertFalse($user->canAccessOrganizationModule('quotes', $organization));
        $this->assertFalse($user->canManageOrganizationModule('quotes', $organization));
    }

    public function test_a_viewer_can_consult_but_not_modify_an_authorized_module(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $user->organizations()->attach($organization, [
            'role' => OrganizationRole::Viewer->value,
            'permissions' => ['modules' => ['crm']],
        ]);

        $this->assertTrue($user->canAccessOrganizationModule('crm', $organization));
        $this->assertFalse($user->canManageOrganizationModule('crm', $organization));
    }

    public function test_a_suspended_account_cannot_access_any_panel(): void
    {
        $user = User::factory()->create(['status' => 'suspended', 'is_platform_admin' => true]);

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('platform')));
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_a_platform_administrator_can_create_a_user_without_disclosing_a_password(): void
    {
        $administrator = User::factory()->platformAdministrator()->create();
        Filament::setCurrentPanel(Filament::getPanel('platform'));

        Livewire::actingAs($administrator)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'Camille Martin',
                'email' => 'camille@example.test',
                'status' => 'active',
                'send_password_setup_link' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'camille@example.test')->firstOrFail();
        $this->assertNotEmpty($user->password);
        $this->assertTrue($user->isActive());
    }

    public function test_a_platform_administrator_can_open_a_users_access_profile_for_editing(): void
    {
        $administrator = User::factory()->platformAdministrator()->create();
        $organization = Organization::factory()->create();
        $member = User::factory()->create(['status' => 'active']);
        $member->organizations()->attach($organization, [
            'role' => OrganizationRole::Collaborator->value,
            'permissions' => ['modules' => ['crm']],
        ]);
        Filament::setCurrentPanel(Filament::getPanel('platform'));

        Livewire::actingAs($administrator)
            ->test(EditUser::class, ['record' => $member->getKey()])
            ->assertFormSet([
                'name' => $member->name,
                'status' => 'active',
            ]);
    }

    public function test_a_platform_administrator_can_replace_a_users_password(): void
    {
        $administrator = User::factory()->platformAdministrator()->create();
        $member = User::factory()->create(['password' => 'old-password-that-must-not-work']);
        Filament::setCurrentPanel(Filament::getPanel('platform'));

        Livewire::actingAs($administrator)
            ->test(EditUser::class, ['record' => $member->getKey()])
            ->fillForm([
                'password' => 'new-password-that-must-work',
                'password_confirmation' => 'new-password-that-must-work',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('new-password-that-must-work', $member->fresh()->password));
        $this->assertFalse(Hash::check('old-password-that-must-not-work', $member->fresh()->password));
    }

    public function test_password_recovery_is_available_from_both_back_offices(): void
    {
        $this->get('/platform/password-reset/request')->assertOk();
        $this->get('/dashboard/password-reset/request')->assertOk();
    }

    public function test_password_setup_links_return_each_user_to_the_right_back_office(): void
    {
        Notification::fake();
        $member = User::factory()->create();
        $administrator = User::factory()->platformAdministrator()->create();

        app(UserPasswordSetupLink::class)->send($member);
        app(UserPasswordSetupLink::class)->send($administrator);

        Notification::assertSentTo($member, function (ResetPassword $notification): bool {
            return str_contains($notification->url, '/dashboard/password-reset/reset');
        });
        Notification::assertSentTo($administrator, function (ResetPassword $notification): bool {
            return str_contains($notification->url, '/platform/password-reset/reset');
        });
    }

}
