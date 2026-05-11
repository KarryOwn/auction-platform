<?php

use App\Models\DataExportRequest;
use App\Models\User;
use App\Jobs\GenerateUserDataExport;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('user can request data export', function () {
    Queue::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('user.data-export.request'));

    $response->assertRedirect()
        ->assertSessionHas('status', "Data export requested. You'll be notified after an admin approves it.");

    $request = DataExportRequest::where('user_id', $user->id)->first();
    expect($request)->not->toBeNull();
    expect($request->status)->toBe('pending');

    Queue::assertNothingPushed();
});

test('user cannot request multiple exports in 24 hours', function () {
    $user = User::factory()->create();

    DataExportRequest::create([
        'user_id' => $user->id,
        'status' => 'expired', // Even if expired, they are rate limited
        'created_at' => now()->subHours(12),
    ]);

    $response = $this->actingAs($user)->post(route('user.data-export.request'));

    $response->assertRedirect()
        ->assertSessionHas('error', 'You can only request one data export every 24 hours.');
});

test('existing ready export redirects to download', function () {
    $user = User::factory()->create();

    $request = DataExportRequest::create([
        'user_id' => $user->id,
        'status' => 'ready',
        'file_path' => 'fake/path.zip',
    ]);

    $response = $this->actingAs($user)->post(route('user.data-export.request'));

    $response->assertRedirect(route('user.data-export.download', $request));
});

test('profile page shows export call to action', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('profile.edit'));

    $response->assertOk()
        ->assertSee('Export My Data')
        ->assertSee('Request Export');
});

test('admin can view and approve pending data export requests', function () {
    Queue::fake();

    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create(['name' => 'Export User']);
    $request = DataExportRequest::create([
        'user_id' => $user->id,
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.data-exports.index'))
        ->assertOk()
        ->assertSee('Data Export Requests')
        ->assertSee('Export User')
        ->assertSee('Approve');

    $this->actingAs($admin)
        ->post(route('admin.data-exports.approve', $request))
        ->assertRedirect()
        ->assertSessionHas('status', 'Data export request approved. The export is being generated.');

    expect($request->fresh()->status)->toBe('processing');

    Queue::assertPushed(GenerateUserDataExport::class, function ($job) use ($request) {
        return $job->exportRequestId === $request->id;
    });
});

test('admin navigation links to pending data export approvals', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create();
    DataExportRequest::create([
        'user_id' => $user->id,
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Data Exports')
        ->assertSee(route('admin.data-exports.index'), false);
});

test('job successfully creates zip file', function () {
    // Requires zlib extension
    if (!extension_loaded('zip')) {
        $this->markTestSkipped('The zip extension is not available.');
    }

    $user = User::factory()->create(['name' => 'John Doe']);
    
    // Create some fake data
    \App\Models\WalletTransaction::factory()->create([
        'user_id' => $user->id,
        'amount' => 10,
        'type' => 'deposit',
    ]);

    $request = DataExportRequest::create([
        'user_id' => $user->id,
        'status' => 'pending',
    ]);

    $job = new GenerateUserDataExport($request->id);
    $job->handle();

    $request->refresh();
    
    expect($request->status)->toBe('ready');
    expect($request->file_path)->not->toBeNull();
    expect($request->file_path)->toStartWith("exports/{$user->id}/");
    expect(Storage::exists($request->file_path))->toBeTrue();
    
    // Clean up
    Storage::delete($request->file_path);
    @rmdir(storage_path("app/private/exports/{$user->id}"));
});

test('user can download legacy ready export path saved with private prefix', function () {
    Storage::put('exports/legacy-user/data-export.zip', 'zip-content');

    $user = User::factory()->create();
    $request = DataExportRequest::create([
        'user_id' => $user->id,
        'status' => 'ready',
        'file_path' => 'private/exports/legacy-user/data-export.zip',
        'ready_at' => now(),
        'expires_at' => now()->addDays(2),
    ]);

    $this->actingAs($user)
        ->get(route('user.data-export.download', $request))
        ->assertOk();

    expect($request->fresh()->file_path)->toBe('exports/legacy-user/data-export.zip');

    Storage::delete('exports/legacy-user/data-export.zip');
});
