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

        $directory = storage_path('app/invoices');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = "invoice-{$invoice->invoice_number}.pdf";
        $path     = "invoices/{$filename}";
        $fullPath = storage_path("app/{$path}");

        // Use DomPDF if available, otherwise write a native simple PDF.
        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.pdf', compact('invoice'));
            $pdf->save($fullPath);
        } else {
            file_put_contents($fullPath, $this->buildFallbackPdf($invoice));
        }

        $invoice->update(['pdf_path' => $path]);

        return $path;
    }

    protected function buildFallbackPdf(Invoice $invoice): string
    {
        $lines = [
            ['INVOICE', 26, 50, 790],
            [$invoice->invoice_number, 12, 50, 765],
            ['Status: ' . strtoupper($invoice->status), 11, 420, 765],
            ['Bill To', 14, 50, 725],
            [$invoice->buyer?->name ?? 'Unknown buyer', 11, 50, 705],
            [$invoice->buyer?->email ?? '', 10, 50, 690],
            ['Sold By', 14, 320, 725],
            [$invoice->seller?->name ?? 'Unknown seller', 11, 320, 705],
            [$invoice->seller?->email ?? '', 10, 320, 690],
            ['Issued: ' . ($invoice->issued_at?->format('F j, Y') ?? 'Pending'), 10, 50, 650],
            ['Paid: ' . ($invoice->paid_at?->format('F j, Y') ?? 'Pending'), 10, 220, 650],
            ['Currency: ' . $invoice->currency, 10, 390, 650],
            ['Auction', 12, 50, 610],
            [$invoice->auction?->title ?? "Auction #{$invoice->auction_id}", 11, 50, 590],
            ['Auction #' . $invoice->auction_id . ' - Winning Bid', 10, 50, 575],
            ['Subtotal: ' . $this->formatMoney($invoice->subtotal, $invoice->currency), 11, 360, 590],
            ['Platform Fee (' . number_format((float) $invoice->commission_rate_percent, 2) . '%): ' . $this->formatMoney($invoice->platform_fee, $invoice->currency), 11, 50, 525],
            ['Seller Amount: ' . $this->formatMoney($invoice->seller_amount, $invoice->currency), 11, 50, 505],
            ['Total Paid: ' . $this->formatMoney($invoice->total, $invoice->currency), 16, 50, 470],
            ['Thank you for using Auction Platform.', 10, 50, 90],
        ];

        $stream = '';
        foreach ($lines as [$text, $size, $x, $y]) {
            $stream .= sprintf(
                "BT /F1 %d Tf 1 0 0 1 %d %d Tm (%s) Tj ET\n",
                $size,
                $x,
                $y,
                $this->escapePdfText((string) $text)
            );
        }

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }

    protected function formatMoney(mixed $amount, string $currency): string
    {
        return '$' . number_format((float) $amount, 2) . ' ' . $currency;
    }

    protected function escapePdfText(string $text): string
    {
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $text) ?? '';

        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $text);
    }
}
