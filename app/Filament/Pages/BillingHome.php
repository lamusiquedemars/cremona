<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ElectronicReportingBatches\ElectronicReportingBatchResource;
use App\Filament\Resources\ElectronicReports\ElectronicReportResource;
use App\Filament\Resources\IncomingInvoices\IncomingInvoiceResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\ElectronicReport;
use App\Models\IncomingInvoice;
use App\Models\Invoice;
use App\Tenancy\OrganizationContext;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class BillingHome extends Page
{
    protected string $view = 'filament.pages.billing-home';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyEuro;

    protected static string|\UnitEnum|null $navigationGroup = 'Activité commerciale';

    protected static ?string $navigationLabel = 'Facturation';

    protected static ?string $title = 'Facturation';

    protected static ?int $navigationSort = 70;

    protected static ?string $slug = 'facturation';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function canAccess(): bool
    {
        return InvoiceResource::canAccess();
    }

    /** @return array<string, int|float|string> */
    public function overview(): array
    {
        $organization = app(OrganizationContext::class)->require();
        $invoices = Invoice::query()->where('organization_id', $organization->id);

        return [
            'drafts' => (clone $invoices)->where('status', 'draft')->count(),
            'to_collect' => (float) (clone $invoices)->whereIn('status', ['issued', 'partially_paid', 'overdue'])->sum('balance_amount'),
            'received' => IncomingInvoice::query()->where('organization_id', $organization->id)->count(),
            'reports_to_review' => ElectronicReport::query()->where('organization_id', $organization->id)->whereIn('status', ['pending_review', 'needs_completion'])->count(),
        ];
    }

    /** @return array<string, string> */
    public function destinations(): array
    {
        return [
            'create_invoice' => InvoiceResource::getUrl('create'),
            'sales' => InvoiceResource::getUrl('index'),
            'received' => IncomingInvoiceResource::getUrl('index'),
            'reports' => ElectronicReportResource::getUrl('index'),
            'receipts' => ElectronicReportingBatchResource::getUrl('index'),
        ];
    }
}
