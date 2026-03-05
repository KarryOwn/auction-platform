<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        protected InvoiceService $invoiceService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $invoices = Invoice::where('buyer_id', $user->id)
            ->orWhere('seller_id', $user->id)
            ->with(['auction:id,title', 'buyer:id,name', 'seller:id,name'])
            ->latest()
            ->paginate(20);

        return view('user.invoices.index', compact('invoices', 'user'));
    }

    public function show(Request $request, Invoice $invoice)
    {
        $user = $request->user();
        $this->authorizeAccess($invoice, $user);

        $invoice->load(['auction', 'buyer', 'seller']);

        return view('user.invoices.show', compact('invoice', 'user'));
    }

    public function download(Request $request, Invoice $invoice)
    {
        $user = $request->user();
        $this->authorizeAccess($invoice, $user);

        // Generate PDF if not already generated
        if (! $invoice->pdf_path) {
            $this->invoiceService->generatePdf($invoice);
            $invoice->refresh();
        }

        $fullPath = storage_path("app/{$invoice->pdf_path}");

        if (! file_exists($fullPath)) {
            $this->invoiceService->generatePdf($invoice);
            $invoice->refresh();
            $fullPath = storage_path("app/{$invoice->pdf_path}");
        }

        return response()->download($fullPath, "invoice-{$invoice->invoice_number}.pdf");
    }

    protected function authorizeAccess(Invoice $invoice, $user): void
    {
        if ($invoice->buyer_id !== $user->id && $invoice->seller_id !== $user->id && ! $user->isStaff()) {
            abort(403, 'You do not have access to this invoice.');
        }
    }
}
