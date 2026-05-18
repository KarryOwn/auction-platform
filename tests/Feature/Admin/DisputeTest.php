<?php

use App\Models\Auction;
use App\Models\Dispute;
use App\Models\User;
use App\Notifications\DisputeStatusUpdatedNotification;
use Illuminate\Support\Facades\Notification;

test('admin can update dispute resolution status', function () {
    Notification::fake();

    $staff = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $seller = createSeller();
    $buyer = User::factory()->create();

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'winner_id' => $buyer->id,
        'status' => Auction::STATUS_COMPLETED,
    ]);

    $dispute = Dispute::create([
        'auction_id' => $auction->id,
        'claimant_id' => $buyer->id,
        'respondent_id' => $seller->id,
        'type' => 'item_not_received',
        'description' => 'Buyer reported item not received.',
        'status' => Dispute::STATUS_OPEN,
    ]);

    $response = $this->actingAs($staff)->patch(route('admin.disputes.update', $dispute), [
        'status' => Dispute::STATUS_RESOLVED_BUYER,
        'resolution_notes' => 'Reviewed and approved buyer claim.',
    ]);

    $response->assertRedirect(route('admin.disputes.show', $dispute));

    expect($dispute->fresh()->status)->toBe(Dispute::STATUS_RESOLVED_BUYER)
        ->and($dispute->fresh()->resolved_by)->toBe($staff->id);

    Notification::assertSentTo($buyer, DisputeStatusUpdatedNotification::class, function (DisputeStatusUpdatedNotification $notification) use ($buyer) {
        return in_array('mail', $notification->via($buyer), true);
    });

    Notification::assertSentTo($seller, DisputeStatusUpdatedNotification::class, function (DisputeStatusUpdatedNotification $notification) use ($seller) {
        return in_array('mail', $notification->via($seller), true);
    });
});
