<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceService
{
    /**
     * Generate an invoice for a completed and paid auction.
     */
    public function generateForAuction(Auction $auction, float $platformFee, float $sellerAmount, ?float $commissionRate = null): Invoice
    {
        return DB::transaction(function () use ($auction, $platformFee, $sellerAmount, $commissionRate) {
            $total = (float) $auction->winning_bid_amount;

            $invoice = Invoice::create([
                'invoice_number' => Invoice::generateNumber(),
                'auction_id'     => $auction->id,
                'buyer_id'       => $auction->winner_id,
                'seller_id'      => $auction->user_id,
                'subtotal'       => $total,
                'platform_fee'   => $platformFee,
                'seller_amount'  => $sellerAmount,
                'total'          => $total,
                'currency'       => $auction->currency ?? config('auction.currency', 'USD'),
                'status'         => Invoice::STATUS_PAID,
                'issued_at'      => now(),
                'paid_at'        => now(),
                'metadata'       => [
                    'auction_title'      => $auction->title,
                    'winning_bid_amount' => $total,
                    'bid_count'          => $auction->bid_count,
                    'commission_rate'    => $commissionRate,
                ],
            ]);

            Log::info('InvoiceService: invoice generated', [
                'invoice_id'     => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'auction_id'     => $auction->id,
                'total'          => $total,
            ]);

            return $invoice;
        });
    }

    /**
     * Generate a PDF for an invoice and store it.
     */
    public function generatePdf(Invoice $invoice): string
    {
        $invoice->load(['auction', 'buyer', 'seller']);

        $html = view('invoices.pdf', compact('invoice'))->render();

        $directory = storage_path('app/invoices');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = "invoice-{$invoice->invoice_number}.pdf";
        $path     = "invoices/{$filename}";
        $fullPath = storage_path("app/{$path}");

        // Use DomPDF if available, otherwise store HTML fallback
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', compact('invoice'));
            $pdf->save($fullPath);
        } else {
            // Fallback: save HTML version
            file_put_contents($fullPath . '.html', $html);
            $path .= '.html';
        }

        $invoice->update(['pdf_path' => $path]);

        return $path;
    }
}
