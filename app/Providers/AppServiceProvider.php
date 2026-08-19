<?php

namespace App\Providers;

use App\Contracts\CorrespondenceTransport;
use App\Models\Appointment;
use App\Models\Company;
use App\Models\ContactMethod;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationUserState;
use App\Models\IncomingRequest;
use App\Models\MessageAttachment;
use App\Models\MessageParticipant;
use App\Models\MessageReference;
use App\Models\MessageThreadCandidate;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\OrganizationMembership;
use App\Models\OrganizationModule;
use App\Models\Person;
use App\Models\User;
use App\Services\FakeCorrespondenceTransport;
use App\Tenancy\OrganizationContext;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(OrganizationContext::class, fn (): OrganizationContext => new OrganizationContext);
        $this->app->bind(CorrespondenceTransport::class, FakeCorrespondenceTransport::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::enforceMorphMap([
            'appointment' => Appointment::class,
            'person' => Person::class,
            'company' => Company::class,
            'conversation' => Conversation::class,
            'conversation_message' => ConversationMessage::class,
            'conversation_user_state' => ConversationUserState::class,
            'contact_method' => ContactMethod::class,
            'incoming_request' => IncomingRequest::class,
            'organization' => Organization::class,
            'organization_integration' => OrganizationIntegration::class,
            'organization_membership' => OrganizationMembership::class,
            'organization_module' => OrganizationModule::class,
            'message_attachment' => MessageAttachment::class,
            'message_participant' => MessageParticipant::class,
            'message_reference' => MessageReference::class,
            'message_thread_candidate' => MessageThreadCandidate::class,
            'user' => User::class,
        ]);

        if (! $this->app->environment('testing')) {
            return;
        }

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if (str_ends_with($database, '_testing')) {
            return;
        }

        throw new RuntimeException(
            "Tests blocked: database [{$database}] is not a dedicated MySQL testing database."
        );
    }
}
