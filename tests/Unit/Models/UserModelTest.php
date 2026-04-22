<?php

use App\Models\User;

test('is staff returns true for admin and moderator roles', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $moderator = User::factory()->create(['role' => User::ROLE_MODERATOR]);
    $normal = User::factory()->create(['role' => User::ROLE_USER]);

    expect($admin->isStaff())->toBeTrue()
        ->and($moderator->isStaff())->toBeTrue()
        ->and($normal->isStaff())->toBeFalse();
});

test('verified seller requires approved status and verification timestamp', function () {
    $seller = User::factory()->create([
        'role' => User::ROLE_SELLER,
        'seller_application_status' => 'approved',
        'seller_verified_at' => now(),
    ]);

    expect($seller->isVerifiedSeller())->toBeTrue();
});
