<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\TaxDocument;
use App\Models\User;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TaxDocumentService
{
    /**
     * Compile tax summary data for a seller over a date range.
     */
    public function compileSummary(User $seller, Carbon $from, Carbon $to): array
    {
        // Gross sales: sum of winning_bid_amount for completed auctions in period
        $grossSales = Auction::where('user_id', $seller->id)
            ->where('status', Auction::STATUS_COMPLETED)
            ->whereBetween('closed_at', [$from->startOfDay(), $to->endOfDay()])
            ->sum('winning_bid_amount');

        // Platform commissions: sum of platform_fee from invoices
        $platformFees = \App\Models\Invoice::where('seller_id', $seller->id)
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->sum('platform_fee');

        // Listing fees paid
        $listingFees = WalletTransaction::where('user_id', $seller->id)
            ->where('type', WalletTransaction::TYPE_WITHDRAWAL)
            ->where('description', 'like', 'Listing fee%')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->sum('amount');

        // Refunds issued (seller wallet debited for refund reversals)
        $refunds = WalletTransaction::where('user_id', $seller->id)
            ->where('type', WalletTransaction::TYPE_WITHDRAWAL)
            ->where('description', 'like', 'Payout reversed%')
            ->whereBetween('created_at', [$from->startOfDay(), $to->endOfDay()])
            ->sum('amount');

        // Net revenue = gross - platform fees - listing fees - refunds
        $netRevenue = round(
            (float) $grossSales - (float) $platformFees - (float) $listingFees - (float) $refunds,
            2,
        );

        // Line items: each completed auction
        $lineItems = Auction::where('user_id', $seller->id)
            ->where('status', Auction::STATUS_COMPLETED)
            ->whereBetween('closed_at', [$from, $to])
            ->with('invoice')
            ->orderBy('closed_at')
            ->get(['id', 'title', 'winning_bid_amount', 'closed_at'])
            ->map(fn ($a) => [
                'auction_id'          => $a->id,
                'title'               => $a->title,
                'gross'               => (float) $a->winning_bid_amount,
                'platform_fee'        => (float) ($a->invoice?->platform_fee ?? 0),
                'net'                 => (float) ($a->invoice?->seller_amount ?? 0),
                'date'                => $a->closed_at?->toDateString(),
                'invoice_number'      => $a->invoice?->invoice_number,
            ])
            ->all();

        return [
            'seller_name'       => $seller->name,
            'seller_email'      => $seller->email,
            'period_from'       => $from->toDateString(),
            'period_to'         => $to->toDateString(),
            'gross_sales'       => round((float) $grossSales, 2),
            'platform_fees'     => round((float) $platformFees, 2),
            'listing_fees'      => round((float) $listingFees, 2),
            'refunds_issued'    => round((float) $refunds, 2),
            'net_revenue'       => $netRevenue,
            'line_items'        => $lineItems,
            'generated_at'      => now()->toDateTimeString(),
        ];
    }

    /**
     * Generate and cache a PDF tax document.
     */
    public function generatePdf(User $seller, Carbon $from, Carbon $to, string $label): string
    {
        $data = $this->compileSummary($seller, $from, $to);

        $html = view('tax-documents.summary', $data)->render();

        $directory = storage_path('app/tax-documents/' . $seller->id);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = "tax-summary-{$label}.pdf";
        $path     = "tax-documents/{$seller->id}/{$filename}";
        $fullPath = storage_path("app/{$path}");

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('tax-documents.summary', $data);
            $pdf->save($fullPath);
        } else {
            // Fallback to saving HTML if DomPDF isn't available
            file_put_contents($fullPath . '.html', $html);
            $path .= '.html';
        }

        Log::info('TaxDocumentService: generated', [
            'seller_id' => $seller->id,
            'period'    => $label,
            'path'      => $path,
        ]);

        return $path;
    }
}
