<?php

namespace Tests\Feature;

use Tests\TestCase;

class DefaultInterfaceLocaleTest extends TestCase
{
    public function test_the_default_interface_locale_is_french(): void
    {
        $this->assertSame('fr', config('app.locale'));
        $this->assertSame('fr', config('app.fallback_locale'));
    }

    public function test_filament_generic_actions_are_translated_in_french(): void
    {
        $this->assertSame('Voir', __('filament-actions::view.single.label'));
        $this->assertSame('Modifier', __('filament-actions::edit.single.label'));
    }
}
