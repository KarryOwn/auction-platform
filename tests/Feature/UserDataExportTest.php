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
        ->assertSessionHas('status', "Data export requested. You'll be notified when it's ready.");

    $request = DataExportRequest::where('user_id', $user->id)->first();
    expect($request)->not->toBeNull();
    expect($request->status)->toBe('pending');

    Queue::assertPushed(GenerateUserDataExport::class, function ($job) use ($request) {
        return $job->exportRequestId === $request->id;
    });
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
    // In testing, Storage::exists expects the path relative to default disk root. The job writes to absolute path storage_path("app/...") but saves relative "private/exports...".
    // Using file_exists for absolute path checking is safer for this specific implementation.
    expect(file_exists(storage_path('app/' . $request->file_path)))->toBeTrue();
    
    // Clean up
    @unlink(storage_path('app/' . $request->file_path));
    @rmdir(storage_path("app/private/exports/{$user->id}"));
});
