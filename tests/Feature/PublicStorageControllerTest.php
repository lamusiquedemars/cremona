<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageControllerTest extends TestCase
{
    public function test_public_storage_files_are_served_without_a_symbolic_link(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('instruments/violon.jpg', 'photo');

        $this->get('/media/instruments/violon.jpg')
            ->assertOk()
            ->assertHeader('cache-control', 'immutable, max-age=31536000, public');
    }
}
