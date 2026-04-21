<?php

use App\Models\Auction;
use App\Models\Invoice;
use App\Models\TaxDocument;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\TaxDocumentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

test('tax service compiles correct annual summary', function () {
    $seller = User::factory()->create(['role' => 'seller']);
    $buyer = User::factory()->create();

    // 1. Gross Sale: 1000, Platform Fee: 50 (Net 950)
    $auction1 = Auction::factory()->completed()->create([
        'user_id' => $seller->id,
        'winner_id' => $buyer->id,
        'winning_bid_amount' => 1000.00,
        'closed_at' => Carbon::create(2025, 6, 1),
    ]);
    Invoice::factory()->create([
        'auction_id' => $auction1->id,
        'seller_id' => $seller->id,
        'platform_fee' => 50.00,
        'seller_amount' => 950.00,
        'created_at' => Carbon::create(2025, 6, 1),
        'paid_at' => Carbon::create(2025, 6, 1),
    ]);

    // 2. Listing Fee Paid: 10
    WalletTransaction::factory()->create([
        'user_id' => $seller->id,
        'type' => WalletTransaction::TYPE_WITHDRAWAL,
        'amount' => 10.00,
        'description' => 'Listing fee for auction: Test',
        'created_at' => Carbon::create(2025, 6, 1),
    ]);

    // 3. Refund Issued: 100
    WalletTransaction::factory()->create([
        'user_id' => $seller->id,
        'type' => WalletTransaction::TYPE_WITHDRAWAL,
        'amount' => 100.00,
        'description' => 'Payout reversed for refund',
        'created_at' => Carbon::create(2025, 8, 15),
    ]);

    // Outside period (2024)
    Auction::factory()->completed()->create([
        'user_id' => $seller->id,
        'winning_bid_amount' => 5000.00,
        'closed_at' => Carbon::create(2024, 1, 1),
    ]);

    $service = new TaxDocumentService();
    $from = Carbon::create(2025, 1, 1)->startOfDay();
    $to = Carbon::create(2025, 12, 31)->endOfDay();

    $summary = $service->compileSummary($seller, $from, $to);

    expect($summary['gross_sales'])->toBe(1000.0);
    expect($summary['platform_fees'])->toBe(50.0);
    expect($summary['listing_fees'])->toBe(10.0);
    expect($summary['refunds_issued'])->toBe(100.0);
    // Net: 1000 - 50 - 10 - 100 = 840
    expect($summary['net_revenue'])->toBe(840.0);
    expect($summary['line_items'])->toHaveCount(1);
    expect($summary['line_items'][0]['auction_id'])->toBe($auction1->id);
});

test('seller can generate and download tax document', function () {
    $seller = User::factory()->create(['role' => 'seller']);

    $response = $this->actingAs($seller)->post(route('seller.tax-documents.generate'), [
        'period_type' => 'annual',
        'year' => '2025',
    ]);

    $response->assertRedirect(route('seller.tax-documents.index'))
        ->assertSessionHas('status', 'Tax document generated successfully.');

    $doc = TaxDocument::where('user_id', $seller->id)
        ->where('period_label', '2025')
        ->first();

    expect($doc)->not->toBeNull();
    expect($doc->file_path)->not->toBeNull();

    // Verify download
    $downloadResponse = $this->actingAs($seller)->get(route('seller.tax-documents.download', $doc));
    
    $downloadResponse->assertOk();
    // DOMPDF fallback makes an HTML file in testing if pdf isn't strictly loaded
    expect(file_exists(storage_path('app/' . $doc->file_path)))->toBeTrue();
});
