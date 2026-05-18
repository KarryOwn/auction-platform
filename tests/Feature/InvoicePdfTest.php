<?php

use App\Models\Invoice;
use App\Services\InvoiceService;

test('invoice pdf generation creates an openable pdf file without dompdf', function () {
    $invoice = Invoice::factory()->create([
        'invoice_number' => 'INV-PDF-OPENABLE',
    ]);

    $path = app(InvoiceService::class)->generatePdf($invoice);
    $fullPath = storage_path("app/{$path}");

    expect($path)->toBe('invoices/invoice-INV-PDF-OPENABLE.pdf');
    expect(file_exists($fullPath))->toBeTrue();
    expect(file_get_contents($fullPath, false, null, 0, 5))->toBe('%PDF-');

    @unlink($fullPath);
});

test('invoice download regenerates legacy html pdf artifacts', function () {
    $invoice = Invoice::factory()->create([
        'invoice_number' => 'INV-PDF-LEGACY',
        'pdf_path' => 'invoices/invoice-INV-PDF-LEGACY.pdf.html',
    ]);

    $legacyPath = storage_path('app/invoices/invoice-INV-PDF-LEGACY.pdf.html');
    if (! is_dir(dirname($legacyPath))) {
        mkdir(dirname($legacyPath), 0755, true);
    }

    file_put_contents($legacyPath, '<!DOCTYPE html><html><body>Legacy invoice</body></html>');

    $this->actingAs($invoice->buyer)
        ->get(route('user.invoices.download', $invoice))
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename=invoice-INV-PDF-LEGACY.pdf');

    $invoice->refresh();
    $newPath = storage_path("app/{$invoice->pdf_path}");

    expect($invoice->pdf_path)->toBe('invoices/invoice-INV-PDF-LEGACY.pdf');
    expect(file_get_contents($newPath, false, null, 0, 5))->toBe('%PDF-');

    @unlink($legacyPath);
    @unlink($newPath);
});
