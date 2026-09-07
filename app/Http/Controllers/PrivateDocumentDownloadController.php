<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationPermission;
use App\Models\PrivateDocument;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateDocumentDownloadController extends Controller
{
    public function __invoke(Request $request, string $publicId): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $document = PrivateDocument::withoutGlobalScopes()
            ->with('organization')
            ->where('public_id', $publicId)
            ->firstOrFail();

        abort_unless(
            $document->organization->is_active
            && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $document->organization),
            403,
        );
        abort_unless($document->disk === 'local' && Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_name,
            ['Content-Type' => $document->detected_mime_type ?: 'application/octet-stream'],
        );
    }
}
