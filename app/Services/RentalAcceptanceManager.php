<?php

namespace App\Services;

use App\Enums\ContactMethodType;
use App\Enums\RentalAcceptanceStatus;
use App\Enums\RentalDocumentStatus;
use App\Enums\RentalDocumentType;
use App\Mail\RentalAcceptanceInvitation;
use App\Models\ContactMethod;
use App\Models\Rental;
use App\Models\RentalAcceptanceEvent;
use App\Models\RentalAcceptanceRequest;
use App\Models\RentalDocument;
use App\Models\User;
use App\Tenancy\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use LogicException;

class RentalAcceptanceManager
{
    public const CONSENT_TEXT = 'Je confirme avoir consulté les documents ci-dessus et j’accepte leurs clauses. Je comprends que cette acceptation est enregistrée dans Cremona et ne constitue pas une signature électronique qualifiée.';

    public function __construct(private readonly OrganizationContext $context) {}

    public function issue(Rental $rental, ?User $actor = null): RentalAcceptanceRequest
    {
        return $this->context->run($rental->organization, function () use ($rental, $actor): RentalAcceptanceRequest {
            $rental = Rental::query()->with(['organization', 'person'])->findOrFail($rental->id);
            if ($rental->person === null) {
                throw new LogicException('Sélectionnez le locataire avant d’envoyer une demande d’acceptation.');
            }
            $email = ContactMethod::query()
                ->where('contactable_type', $rental->person->getMorphClass())
                ->where('contactable_id', $rental->person_id)
                ->where('type', ContactMethodType::Email)
                ->orderByDesc('is_primary')
                ->value('value');
            if (blank($email)) {
                throw new LogicException('Ajoutez un e-mail au locataire avant d’envoyer une demande d’acceptation.');
            }

            $documents = RentalDocument::query()
                ->with('privateDocument')
                ->where('rental_id', $rental->id)
                ->where('status', RentalDocumentStatus::Generated)
                ->whereIn('type', $rental->insurance_plan_id === null
                    ? [RentalDocumentType::RentalContract]
                    : [RentalDocumentType::RentalContract, RentalDocumentType::InsuranceContract])
                ->latest('version_number')
                ->get()
                ->unique('type')
                ->values();
            if ($documents->isEmpty()) {
                throw new LogicException('Générez au moins un contrat avant d’envoyer une demande d’acceptation.');
            }
            $contract = $documents->firstWhere('type', RentalDocumentType::RentalContract);
            if ($contract === null || ! $this->matchesCurrentInsurance($contract, $rental)) {
                throw new LogicException('Générez une nouvelle version du contrat de location après avoir modifié l’assurance.');
            }
            if ($rental->insurance_plan_id !== null) {
                $insurance = $documents->firstWhere('type', RentalDocumentType::InsuranceContract);
                if ($insurance === null || ! $this->matchesCurrentInsurance($insurance, $rental)) {
                    throw new LogicException('Générez le contrat d’assurance correspondant à la formule sélectionnée avant l’envoi.');
                }
            }

            $token = Str::random(64);
            $request = DB::transaction(function () use ($rental, $actor, $documents, $token, $email): RentalAcceptanceRequest {
                $request = new RentalAcceptanceRequest([
                    'rental_id' => $rental->id,
                    'recipient_name' => $rental->person->display_name,
                    'recipient_email' => $email,
                    'token_hash' => hash('sha256', $token),
                    'status' => RentalAcceptanceStatus::Created,
                    'consent_text' => self::CONSENT_TEXT,
                    'expires_at' => now()->addDays(14),
                ]);
                $request->organization_id = $rental->organization_id;
                $request->save();
                $request->documents()->attach($documents->mapWithKeys(fn (RentalDocument $document): array => [$document->id => ['document_sha256' => $document->content_sha256]])->all());
                $this->event($request, 'created', actor: $actor);

                return $request;
            });

            $request->load('rental.organization');
            $url = route('rental-acceptance.show', ['token' => $token]);
            Mail::to($request->recipient_email)->send(new RentalAcceptanceInvitation($request, $url));
            $request->update(['status' => RentalAcceptanceStatus::Sent, 'sent_at' => now()]);
            $this->event($request, 'sent', actor: $actor);

            return $request;
        });
    }

    public function resolve(string $token): RentalAcceptanceRequest
    {
        $request = RentalAcceptanceRequest::withoutGlobalScopes()
            ->with(['rental.organization', 'documents.privateDocument'])
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();

        return $this->context->run($request->rental->organization, function () use ($request): RentalAcceptanceRequest {
            if (in_array($request->status, [RentalAcceptanceStatus::Cancelled, RentalAcceptanceStatus::Refused], true)) {
                throw new LogicException('Cette demande d’acceptation n’est plus disponible.');
            }
            if ($request->status !== RentalAcceptanceStatus::Accepted && $request->expires_at->isPast()) {
                $request->update(['status' => RentalAcceptanceStatus::Expired]);
                $this->event($request, 'expired');
                throw new LogicException('Ce lien d’acceptation a expiré.');
            }

            if ($request->status !== RentalAcceptanceStatus::Accepted && ! $request->events()->where('event', 'opened')->exists()) {
                $this->event($request, 'opened');
            }

            return $request;
        });
    }

    public function accept(RentalAcceptanceRequest $request, string $name, Request $httpRequest): RentalAcceptanceRequest
    {
        return $this->context->run($request->rental->organization, function () use ($request, $name, $httpRequest): RentalAcceptanceRequest {
            return DB::transaction(function () use ($request, $name, $httpRequest): RentalAcceptanceRequest {
                $request = RentalAcceptanceRequest::query()->lockForUpdate()->with('rental')->findOrFail($request->id);
                if (! in_array($request->status, [RentalAcceptanceStatus::Created, RentalAcceptanceStatus::Sent], true) || $request->expires_at->isPast()) {
                    throw new LogicException('Cette demande ne peut plus être acceptée.');
                }
                $name = trim($name);
                if ($name === '') {
                    throw new LogicException('Indiquez votre nom complet pour confirmer votre acceptation.');
                }
                $request->update([
                    'status' => RentalAcceptanceStatus::Accepted,
                    'accepted_at' => now(),
                    'accepted_name' => $name,
                    'accepted_ip_address' => $httpRequest->ip(),
                    'accepted_user_agent' => Str::limit((string) $httpRequest->userAgent(), 2000, ''),
                ]);
                $this->event($request, 'accepted', ['accepted_name' => $name], $httpRequest);

                return $request->fresh(['rental.organization', 'documents.privateDocument']);
            });
        });
    }

    public function cancel(RentalAcceptanceRequest $request, ?User $actor = null): RentalAcceptanceRequest
    {
        return $this->context->run($request->rental->organization, function () use ($request, $actor): RentalAcceptanceRequest {
            return DB::transaction(function () use ($request, $actor): RentalAcceptanceRequest {
                $request = RentalAcceptanceRequest::query()->lockForUpdate()->with('rental')->findOrFail($request->id);
                if (! in_array($request->status, [RentalAcceptanceStatus::Created, RentalAcceptanceStatus::Sent], true)) {
                    throw new LogicException('Seule une demande en attente peut être annulée.');
                }
                $request->update(['status' => RentalAcceptanceStatus::Cancelled, 'cancelled_at' => now()]);
                $this->event($request, 'cancelled', actor: $actor);

                return $request->fresh();
            });
        });
    }

    public function document(RentalAcceptanceRequest $request, int $rentalDocumentId): RentalDocument
    {
        if ($request->expires_at->isPast()) {
            throw new LogicException('Ce lien d’acceptation a expiré.');
        }
        $document = $request->documents()->whereKey($rentalDocumentId)->with('privateDocument')->firstOrFail();
        if ($document->privateDocument === null || $document->privateDocument->sha256 !== $document->pivot->document_sha256) {
            throw new LogicException('Le document demandé n’est plus disponible dans son état d’origine.');
        }

        return $document;
    }

    /** @param array<string, mixed> $metadata */
    private function event(RentalAcceptanceRequest $request, string $event, array $metadata = [], ?Request $httpRequest = null, ?User $actor = null): void
    {
        $proof = new RentalAcceptanceEvent([
            'request_id' => $request->id,
            'event' => $event,
            'metadata' => $metadata,
            'ip_address' => $httpRequest?->ip(),
            'user_agent' => $httpRequest === null ? null : Str::limit((string) $httpRequest->userAgent(), 2000, ''),
        ]);
        $proof->organization_id = $request->organization_id;
        $proof->save();

        app(AuditLogger::class)->record('rental.acceptance.'.$event, $request, $actor, ['rental_id' => $request->rental_id]);
    }

    private function matchesCurrentInsurance(RentalDocument $document, Rental $rental): bool
    {
        $snapshot = $document->snapshot['rental'] ?? [];

        return ($snapshot['insurance_plan_name'] ?? null) === $rental->insurance_plan_name
            && ($snapshot['insurance_monthly_amount'] ?? null) === number_format((float) $rental->insurance_monthly_amount, 2, ',', ' ');
    }
}
