<?php

namespace App\Http\Controllers;

use App\Services\RentalAcceptanceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RentalAcceptanceController extends Controller
{
    public function show(string $token, RentalAcceptanceManager $manager): View
    {
        try {
            $acceptance = $manager->resolve($token);
        } catch (LogicException $exception) {
            abort(410, $exception->getMessage());
        }

        return view('rentals.acceptance', compact('acceptance', 'token'));
    }

    public function accept(Request $request, string $token, RentalAcceptanceManager $manager): RedirectResponse
    {
        $data = $request->validate([
            'accepted_name' => ['required', 'string', 'max:255'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'Vous devez confirmer avoir lu et accepté les documents.',
        ]);
        try {
            $acceptance = $manager->resolve($token);
            $manager->accept($acceptance, $data['accepted_name'], $request);
        } catch (LogicException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('rental-acceptance.show', ['token' => $token])->with('success', 'Votre acceptation a été enregistrée.');
    }

    public function document(string $token, int $document, RentalAcceptanceManager $manager): StreamedResponse
    {
        try {
            $acceptance = $manager->resolve($token);
            $rentalDocument = $manager->document($acceptance, $document);
        } catch (LogicException $exception) {
            abort(410, $exception->getMessage());
        }

        $private = $rentalDocument->privateDocument;
        abort_unless($private !== null && Storage::disk($private->disk)->exists($private->path), 404);

        return Storage::disk($private->disk)->download($private->path, $private->original_name, ['Content-Type' => 'application/pdf']);
    }
}
