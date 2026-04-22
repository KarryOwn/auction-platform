<?php

use App\Models\Auction;
use App\Models\User;

test('staff can feature and unfeature auction', function () {
    $staff = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $seller = createSeller();

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'is_featured' => false,
        'featured_until' => null,
    ]);

    $feature = $this->actingAs($staff)->postJson(route('admin.auctions.feature', $auction), [
        'duration_hours' => 24,
    ]);

    $feature->assertOk()->assertJson(['success' => true]);
    expect($auction->fresh()->is_featured)->toBeTrue();

    $unfeature = $this->actingAs($staff)->deleteJson(route('admin.auctions.unfeature', $auction));
    $unfeature->assertOk()->assertJson(['success' => true]);

    expect($auction->fresh()->is_featured)->toBeFalse();
});
