<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PrivateDocument;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Throwable;

class InvoiceDocumentManager
{
    public function ensurePdf(Invoice $invoice): PrivateDocument
    {
        $invoice->loadMissing(['organization', 'pdfPrivateDocument']);
        if ($invoice->pdfPrivateDocument !== null) {
            return $invoice->pdfPrivateDocument;
        }
        if ($invoice->issued_at === null || $invoice->reference === null) {
            throw new LogicException('Émettez la facture avant de générer son PDF.');
        }

        $disk = (string) config('private-documents.disk', 'local');
        $content = app(InvoicePdfRenderer::class)->render($invoice);
        $filename = 'Facture-'.$invoice->reference.'.pdf';
        $path = 'documents/'.$invoice->organization_id.'/invoices/'.$invoice->public_id.'/'.$filename;

        try {
            Storage::disk($disk)->put($path, $content, ['visibility' => 'private']);
            $document = PrivateDocument::query()->create([
                'title' => 'Facture '.$invoice->reference,
                'category' => 'accounting_record',
                'disk' => $disk,
                'path' => $path,
                'original_name' => $filename,
                'declared_mime_type' => 'application/pdf',
                'detected_mime_type' => 'application/pdf',
                'size' => strlen($content),
                'sha256' => hash('sha256', $content),
            ]);
            $invoice->forceFill(['pdf_private_document_id' => $document->id])->saveQuietly();

            return $document;
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }
}
