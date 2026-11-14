<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicStorageController extends Controller
{
    public function __invoke(string $path): StreamedResponse
    {
        abort_if(str_contains($path, '..') || str_starts_with($path, '/'), 404);

        $disk = Storage::disk('public');

        abort_unless($disk->fileExists($path), 404);

        return $disk->response($path, headers: [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
