<?php

use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\User;
use App\Notifications\AuthCertStatusNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

function verifiedSeller(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'role' => User::ROLE_SELLER,
        'seller_verified_at' => now(),
        'seller_application_status' => 'approved',
    ], $attributes));
}

function fakePdfUpload(string $name = 'certificate.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF"
    );
}

test('seller can upload and delete an authenticity certificate', function () {
    Storage::fake('local');

    $seller = verifiedSeller();
    $auction = Auction::factory()->draft()->create([
        'user_id' => $seller->id,
    ]);

    $uploadResponse = $this->actingAs($seller)->post(
        route('seller.auctions.auth-cert.upload', $auction),
        ['file' => fakePdfUpload()]
    );

    $uploadResponse->assertOk()
        ->assertJson([
            'success' => true,
            'status' => 'uploaded',
        ]);

    $auction->refresh();

    expect($auction->has_authenticity_cert)->toBeTrue();
    expect($auction->authenticity_cert_status)->toBe('uploaded');
    expect($auction->getMedia('authenticity_cert'))->toHaveCount(1);
    expect(AuditLog::where('action', 'auction.auth_cert.uploaded')->where('target_id', $auction->id)->exists())->toBeTrue();

    $deleteResponse = $this->actingAs($seller)->delete(route('seller.auctions.auth-cert.delete', $auction));

    $deleteResponse->assertOk()->assertJson(['success' => true]);

    $auction->refresh();

    expect($auction->has_authenticity_cert)->toBeFalse();
    expect($auction->authenticity_cert_status)->toBe('none');
    expect($auction->getMedia('authenticity_cert'))->toHaveCount(0);
    expect(AuditLog::where('action', 'auction.auth_cert.deleted')->where('target_id', $auction->id)->exists())->toBeTrue();
});

test('authenticity certificate download requires authentication and an eligible auction status', function () {
    Storage::fake('local');

    $seller = verifiedSeller();
    $viewer = User::factory()->create();

    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addHour(),
    ]);

    $auction->addMedia(fakePdfUpload())
        ->toMediaCollection('authenticity_cert');

    $auction->update([
        'has_authenticity_cert' => true,
        'authenticity_cert_status' => 'verified',
    ]);

    $this->get(route('auctions.auth-cert.download', $auction))
        ->assertRedirect(route('login'));

    $this->actingAs($viewer)
        ->get(route('auctions.auth-cert.download', $auction))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $draftAuction = Auction::factory()->draft()->create([
        'user_id' => $seller->id,
    ]);

    $draftAuction->addMedia(fakePdfUpload('draft-certificate.pdf'))
        ->toMediaCollection('authenticity_cert');

    $draftAuction->update([
        'has_authenticity_cert' => true,
        'authenticity_cert_status' => 'uploaded',
    ]);

    $this->actingAs($viewer)
        ->get(route('auctions.auth-cert.download', $draftAuction))
        ->assertForbidden();

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)
        ->get(route('auctions.auth-cert.download', $draftAuction))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('admin can verify an authenticity certificate and notify the seller', function () {
    Storage::fake('local');
    Notification::fake();

    $seller = verifiedSeller();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $auction = Auction::factory()->create([
        'user_id' => $seller->id,
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
    ]);

    $auction->addMedia(fakePdfUpload())
        ->toMediaCollection('authenticity_cert');

    $auction->update([
        'has_authenticity_cert' => true,
        'authenticity_cert_status' => 'uploaded',
    ]);

    $response = $this->actingAs($admin)->post(route('admin.auctions.auth-cert.verify', $auction), [
        'status' => 'verified',
        'notes' => 'Serial number matched supporting documents.',
    ]);

    $response->assertOk()
        ->assertJson([
            'status' => 'verified',
        ]);

    $auction->refresh();

    expect($auction->authenticity_cert_status)->toBe('verified');
    expect($auction->authenticity_cert_verified_by)->toBe($admin->id);
    expect($auction->authenticity_cert_verified_at)->not->toBeNull();
    expect($auction->authenticity_cert_notes)->toBe('Serial number matched supporting documents.');
    expect(AuditLog::where('action', 'auction.auth_cert.verified')->where('target_id', $auction->id)->exists())->toBeTrue();

    Notification::assertSentTo(
        [$seller],
        AuthCertStatusNotification::class,
        fn (AuthCertStatusNotification $notification) => $notification->auction->is($auction)
    );
});

test('admin certificate approvals queue lists uploaded certificates', function () {
    Storage::fake('local');

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $seller = verifiedSeller();

    $pendingAuction = Auction::factory()->create([
        'user_id' => $seller->id,
        'title' => 'Pending Certificate Watch',
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
    ]);
    $pendingAuction->addMedia(fakePdfUpload('pending-certificate.pdf'))
        ->toMediaCollection('authenticity_cert');
    $pendingAuction->update([
        'has_authenticity_cert' => true,
        'authenticity_cert_status' => 'uploaded',
    ]);

    $verifiedAuction = Auction::factory()->create([
        'user_id' => $seller->id,
        'title' => 'Verified Certificate Watch',
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
        'has_authenticity_cert' => true,
        'authenticity_cert_status' => 'verified',
    ]);

    $response = $this->actingAs($admin)->get(route('admin.auctions.index', [
        'auth_cert' => 'uploaded',
    ]));

    $response->assertOk()
        ->assertSee('Certificate Approvals (1)')
        ->assertSee('Pending Certificate Watch')
        ->assertSee('Needs review')
        ->assertSee(route('admin.auctions.show', $pendingAuction), false)
        ->assertDontSee('Verified Certificate Watch');
});

test('auction search can be filtered to verified authenticated items only', function () {
    $viewer = User::factory()->create();
    $seller = verifiedSeller();

    $verifiedAuction = Auction::factory()->create([
        'user_id' => $seller->id,
        'title' => 'Verified Watch',
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
        'has_authenticity_cert' => true,
        'authenticity_cert_status' => 'verified',
    ]);

    $pendingAuction = Auction::factory()->create([
        'user_id' => $seller->id,
        'title' => 'Pending Watch',
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
        'has_authenticity_cert' => true,
        'authenticity_cert_status' => 'uploaded',
    ]);

    $plainAuction = Auction::factory()->create([
        'user_id' => $seller->id,
        'title' => 'No Certificate Watch',
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
        'has_authenticity_cert' => false,
        'authenticity_cert_status' => 'none',
    ]);

    $response = $this->actingAs($viewer)->get(route('auctions.index', [
        'authenticated_only' => 1,
    ]));

    $response->assertOk()
        ->assertSee($verifiedAuction->title)
        ->assertDontSee($pendingAuction->title)
        ->assertDontSee($plainAuction->title);
});

test('category browse can be filtered to verified authenticated items only', function () {
    $category = Category::create([
        'name' => 'Luxury Watches',
        'slug' => 'luxury-watches',
        'is_active' => true,
    ]);

    $seller = verifiedSeller();

    $verifiedAuction = Auction::factory()->create([
        'user_id' => $seller->id,
        'title' => 'Verified Category Watch',
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
        'has_authenticity_cert' => true,
        'authenticity_cert_status' => 'verified',
    ]);
    $verifiedAuction->categories()->sync([$category->id => ['is_primary' => true]]);

    $pendingAuction = Auction::factory()->create([
        'user_id' => $seller->id,
        'title' => 'Pending Category Watch',
        'status' => Auction::STATUS_ACTIVE,
        'start_time' => now()->subHour(),
        'end_time' => now()->addDay(),
        'has_authenticity_cert' => true,
        'authenticity_cert_status' => 'uploaded',
    ]);
    $pendingAuction->categories()->sync([$category->id => ['is_primary' => true]]);

    $response = $this->get(route('categories.show', [
        'category' => $category,
        'authenticated_only' => 1,
    ]));

    $response->assertOk()
        ->assertSee($verifiedAuction->title)
        ->assertDontSee($pendingAuction->title);
});
