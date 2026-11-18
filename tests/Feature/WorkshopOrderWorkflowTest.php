<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OrganizationRole;
use App\Enums\QuoteStatus;
use App\Enums\WorkshopOrderStatus;
use App\Filament\Resources\WorkshopOrders\Pages\ListWorkshopOrders;
use App\Filament\Widgets\WorkshopAttention;
use App\Models\Organization;
use App\Models\OrganizationLegalProfile;
use App\Models\Person;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkshopOrder;
use App\Services\InvoiceFromQuoteManager;
use App\Services\InvoiceWorkflowManager;
use App\Services\OrganizationModuleRegistry;
use App\Services\QuoteWorkflowManager;
use App\Services\WorkshopOrderQuoteManager;
use App\Services\WorkshopOrderWorkflowManager;
use App\Tenancy\OrganizationContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class WorkshopOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_workshop_desk_can_follow_one_order_from_receipt_to_an_invoice_draft(): void
    {
        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(11, 0));

        $organization = Organization::factory()->create();
        app(OrganizationModuleRegistry::class)->sync($organization, ['workshop', 'invoicing']);
        $operator = User::factory()->create();
        $operator->organizations()->attach($organization, ['role' => OrganizationRole::Owner->value]);
        $this->actingAs($operator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($organization);

        app(OrganizationContext::class)->run($organization, function (): void {
            OrganizationLegalProfile::query()->create([
                'display_name' => 'Atelier test', 'legal_name' => 'Atelier test', 'email' => 'bonjour@example.test',
                'address_line_1' => '1 rue du Test', 'postal_code' => '69000', 'city' => 'Lyon', 'country_code' => 'FR', 'registration_number' => '12345678900012',
            ]);
            $person = Person::query()->create(['display_name' => 'Camille Martin']);
            $person->contactMethods()->create(['type' => 'email', 'value' => 'camille@example.test']);
            $order = WorkshopOrder::query()->create(['title' => 'Reméchage', 'person_id' => $person->id]);
            $order->services()->create(['label_snapshot' => 'Reméchage', 'quantity' => 1, 'unit_amount' => 45, 'include_in_quote' => true]);
            $order->services()->create(['label_snapshot' => 'Nettoyage', 'quantity' => 1, 'unit_amount' => 15, 'include_in_quote' => true]);

            Livewire::test(WorkshopAttention::class)
                ->assertCanSeeTableRecords([$order])
                ->assertSee('Examiner l’instrument')
                ->callTableAction('examination_on', $order, data: ['date' => '2026-11-18'])
                ->assertHasNoErrors()
                ->assertSee('Aujourd’hui');

            Livewire::test(ListWorkshopOrders::class)
                ->callTableAction('prepareWorkshopQuote', $order, data: ['diagnosis' => 'Crin à remplacer'])
                ->assertHasNoErrors();
            $this->assertSame(WorkshopOrderStatus::Diagnosed, $order->fresh()->status);
            $quote = $order->quote()->firstOrFail();
            $this->assertSame(60.0, (float) $quote->total_amount);
            $quote->update(['discount_amount' => 5.01]);
            $quote->refreshAmounts();

            Livewire::test(WorkshopAttention::class)
                ->assertSee('Contacter le client au sujet du devis')
                ->callTableAction('quote_follow_up_on', $order, data: ['date' => '2026-11-19'])
                ->assertHasNoErrors()
                ->assertSee('Demain')
                ->callTableAction('sendWorkshopQuote', $order, data: ['sent_via' => 'email'])
                ->assertHasNoErrors()
                ->assertCanNotSeeTableRecords([$order]);

            Livewire::test(ListWorkshopOrders::class)
                ->callTableAction('acceptWorkshopQuote', $order)
                ->assertHasNoErrors()
                ->assertTableActionHidden('prepareWorkshopInvoice', $order);
            $this->assertSame(QuoteStatus::Accepted, $quote->fresh()->status);
            $this->assertSame(WorkshopOrderStatus::Approved, $order->fresh()->status);

            try {
                app(InvoiceFromQuoteManager::class)->createDraft($quote);
                $this->fail('La facture d’atelier a été préparée avant la fin du travail.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('travail réalisé', $exception->getMessage());
            }

            $this->travel(1)->days();
            Livewire::test(ListWorkshopOrders::class)
                ->callTableAction('work_due_on', $order, data: ['date' => '2026-11-20'])
                ->assertHasNoErrors()
                ->callTableAction('completeWorkshopWork', $order)
                ->assertHasNoErrors();
            $this->assertSame(WorkshopOrderStatus::Ready, $order->fresh()->status);
            $this->assertNull($order->fresh()->scheduled_at);
            $this->assertNull($order->fresh()->started_at);

            Livewire::test(WorkshopAttention::class)
                ->assertSee('À facturer')
                ->callTableAction('prepareWorkshopInvoice', $order)
                ->assertHasNoErrors()
                ->assertRedirect();
            $invoice = $quote->invoice()->firstOrFail();
            $this->assertSame(InvoiceStatus::Draft, $invoice->status);
            $this->assertSame('2026-11-19', $invoice->supply_on->toDateString());
            $this->assertSame('54.99', $invoice->subtotal_amount);
            $this->assertSame('5.01', number_format($invoice->lines()->sum('discount_amount'), 2, '.', ''));
            $this->assertTrue($invoice->is(app(InvoiceFromQuoteManager::class)->createDraft($quote)));
            $this->assertSame(InvoiceStatus::Issued, app(InvoiceWorkflowManager::class)->issue($invoice)->status);
            $this->assertSame(WorkshopOrderStatus::Ready, $order->fresh()->status);
        });
    }

    public function test_the_workshop_dashboard_stays_scoped_and_obeys_read_only_permissions(): void
    {
        $this->withoutVite();

        $organization = Organization::factory()->create();
        app(OrganizationModuleRegistry::class)->sync($organization, ['workshop', 'quotes']);
        $viewer = User::factory()->create();
        $viewer->organizations()->attach($organization, ['role' => OrganizationRole::Viewer->value]);
        $this->actingAs($viewer);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($organization);

        $context = app(OrganizationContext::class);
        $otherOrder = $context->run(Organization::factory()->create(), fn (): WorkshopOrder => WorkshopOrder::create(['title' => 'Dossier autre atelier']));
        $order = $context->run($organization, fn (): WorkshopOrder => WorkshopOrder::create(['title' => 'Dossier à examiner']));

        $this->get(Filament::getPanel('admin')->getUrl($organization))
            ->assertOk()
            ->assertSeeText('À faire à l’atelier')
            ->assertDontSeeText($otherOrder->title);

        $context->run($organization, function () use ($order, $otherOrder): void {
            Livewire::test(WorkshopAttention::class)
                ->assertCanSeeTableRecords([$order])
                ->assertCanNotSeeTableRecords([$otherOrder])
                ->assertTableActionHidden('prepareWorkshopQuote', $order)
                ->assertTableActionHidden('examination_on', $order);
        });
    }

    public function test_repair_workflow_requires_a_diagnosis_then_an_accepted_quote_before_work_starts(): void
    {
        $organization = Organization::factory()->create();

        app(OrganizationContext::class)->run($organization, function (): void {
            OrganizationLegalProfile::query()->create([
                'display_name' => 'Atelier test', 'legal_name' => 'Atelier test', 'email' => 'bonjour@example.test',
                'address_line_1' => '1 rue du Test', 'postal_code' => '69000', 'city' => 'Lyon', 'country_code' => 'FR', 'registration_number' => '12345678900012',
            ]);
            $order = WorkshopOrder::query()->create([
                'title' => 'Révision du violon',
                'diagnosis' => 'Âme à remplacer et cordes à renouveler.',
            ]);
            $workflow = app(WorkshopOrderWorkflowManager::class);

            $workflow->confirmDiagnosis($order);
            $this->assertSame(WorkshopOrderStatus::Diagnosed, $order->fresh()->status);

            $order->services()->create([
                'label_snapshot' => 'Remplacement d’âme',
                'quantity' => 1,
                'unit_amount' => 45,
                'include_in_quote' => true,
            ]);
            $quote = app(WorkshopOrderQuoteManager::class)->sync($order);
            $person = Person::query()->create(['display_name' => 'Camille Martin']);
            $person->contactMethods()->create(['type' => 'email', 'value' => 'camille@example.test']);
            $quote->update(['person_id' => $person->id]);
            $this->assertSame($person->id, $order->fresh()->person_id);
            app(QuoteWorkflowManager::class)->markSent($quote, 'email');

            $this->assertSame(WorkshopOrderStatus::AwaitingApproval, $order->fresh()->status);

            app(QuoteWorkflowManager::class)->markAccepted($quote);
            $this->assertSame(QuoteStatus::Accepted, $quote->fresh()->status);
            $this->assertSame(WorkshopOrderStatus::Approved, $order->fresh()->status);
            $approvedAt = $order->fresh()->authorized_at;
            $this->assertNotNull($approvedAt);
            $this->assertNull($order->fresh()->scheduled_at);
            $this->travel(1)->days();

            $workflow->schedule($order, now()->addWeek());
            $this->assertTrue($approvedAt->equalTo($order->fresh()->authorized_at));
            $workflow->start($order);
            $workflow->markReady($order);
            $workflow->markReturned($order);

            $order->refresh();
            $this->assertSame(WorkshopOrderStatus::Returned, $order->status);
            $this->assertNotNull($order->scheduled_at);
            $this->assertNotNull($order->started_at);
            $this->assertNotNull($order->ready_at);
            $this->assertNotNull($order->returned_at);
        });
    }

    public function test_quote_updates_preserve_existing_customer_and_advanced_workshop_status(): void
    {
        app(OrganizationContext::class)->run(Organization::factory()->create(), function (): void {
            $customer = Person::query()->create(['display_name' => 'Client atelier']);
            $recipient = Person::query()->create(['display_name' => 'Destinataire devis']);
            $order = WorkshopOrder::query()->create([
                'title' => 'Révision', 'person_id' => $customer->id,
                'status' => WorkshopOrderStatus::InProgress,
            ]);
            $quote = Quote::query()->create([
                'title' => 'Révision', 'workshop_order_id' => $order->id,
                'person_id' => $recipient->id, 'status' => QuoteStatus::Accepted,
            ]);
            $quote->update(['notes' => 'Modification après accord']);
            $this->assertSame($customer->id, $order->fresh()->person_id);
            $this->assertSame(WorkshopOrderStatus::InProgress, $order->fresh()->status);
        });
    }

    public function test_reconciliation_repairs_existing_orders_and_is_idempotent(): void
    {
        app(OrganizationContext::class)->run(Organization::factory()->create(), function (): void {
            $customer = Person::query()->create(['display_name' => 'Client devis']);
            $order = WorkshopOrder::query()->create([
                'title' => 'Révision', 'status' => WorkshopOrderStatus::AwaitingApproval,
            ]);
            $quote = Quote::withoutEvents(fn () => Quote::query()->forceCreate([
                'organization_id' => $order->organization_id,
                'public_id' => (string) Str::ulid(),
                'reference' => 'Q-LEGACY', 'title' => 'Révision',
                'workshop_order_id' => $order->id, 'person_id' => $customer->id,
                'status' => QuoteStatus::Accepted, 'accepted_at' => now()->subDay(),
            ]));
            $migration = require database_path('migrations/2026_09_18_000000_reconcile_workshop_orders_with_quotes.php');
            $migration->up();
            $migration->up();
            $this->assertSame($customer->id, $order->fresh()->person_id);
            $this->assertSame(WorkshopOrderStatus::Approved, $order->fresh()->status);
            $this->assertTrue($quote->accepted_at->equalTo($order->fresh()->authorized_at));
        });
    }
}
