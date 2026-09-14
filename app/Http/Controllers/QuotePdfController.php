<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationPermission;
use App\Models\Quote;
use App\Models\User;
use App\Services\QuotePdfRenderer;
use LogicException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QuotePdfController extends Controller
{
    public function __invoke(Request $request, string $publicId, QuotePdfRenderer $renderer): Response
    {
        /** @var User $user */
        $user = $request->user();
        $quote = Quote::withoutGlobalScopes()->with('organization')->where('public_id', $publicId)->firstOrFail();

        abort_unless($quote->organization->is_active && $user->hasOrganizationPermission(OrganizationPermission::ViewCrm, $quote->organization), 403);

        try {
            $content = $renderer->render($quote);
        } catch (LogicException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Devis-'.$quote->reference.'.pdf"',
        ]);
    }
}
