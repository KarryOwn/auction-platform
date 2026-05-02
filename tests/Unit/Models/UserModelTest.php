<?php

use App\Models\User;

test('is staff returns true only for admin role', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $normal = User::factory()->create(['role' => User::ROLE_USER]);
    $seller = User::factory()->create(['role' => User::ROLE_SELLER]);

    expect($admin->isStaff())->toBeTrue()
        ->and($normal->isStaff())->toBeFalse()
        ->and($seller->isStaff())->toBeFalse();
});

test('verified seller requires approved status and verification timestamp', function () {
    $seller = User::factory()->create([
        'role' => User::ROLE_SELLER,
        'seller_application_status' => 'approved',
        'seller_verified_at' => now(),
    ]);

    expect($seller->isVerifiedSeller())->toBeTrue();
});
