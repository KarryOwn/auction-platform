<?php

use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Notifications\MaintenanceWindowNotification;
use App\Services\MaintenanceWindowService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

function makeAdminUser(): User
{
    return User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);
}

test('admin can schedule update and cancel maintenance windows', function () {
    Notification::fake();
    $admin = makeAdminUser();
    $user = User::factory()->create();

    $createResponse = $this->actingAs($admin)->post(route('admin.maintenance.store'), [
        'scheduled_start' => now()->addHours(3)->format('Y-m-d H:i:s'),
        'scheduled_end' => now()->addHours(4)->format('Y-m-d H:i:s'),
        'message' => 'Database upgrades in progress.',
    ]);

    $createResponse->assertRedirect(route('admin.maintenance.index'));

    $window = MaintenanceWindow::query()->firstOrFail();

    expect($window->status)->toBe(MaintenanceWindow::STATUS_SCHEDULED);
    expect($window->created_by)->toBe($admin->id);
    Notification::assertSentTo([$admin, $user], MaintenanceWindowNotification::class, function (MaintenanceWindowNotification $notification) {
        return $notification->event === 'scheduled';
    });

    $updateResponse = $this->actingAs($admin)->patch(route('admin.maintenance.update', $window), [
        'scheduled_start' => now()->addHours(5)->format('Y-m-d H:i:s'),
        'scheduled_end' => now()->addHours(6)->format('Y-m-d H:i:s'),
        'message' => 'Maintenance rescheduled.',
    ]);

    $updateResponse->assertRedirect(route('admin.maintenance.index'));

    expect($window->fresh()->message)->toBe('Maintenance rescheduled.');
    Notification::assertSentTo([$admin, $user], MaintenanceWindowNotification::class, function (MaintenanceWindowNotification $notification) {
        return $notification->event === 'updated';
    });

    Artisan::spy();

    $cancelResponse = $this->actingAs($admin)->post(route('admin.maintenance.cancel', $window));

    $cancelResponse->assertRedirect(route('admin.maintenance.index'));
    expect($window->fresh()->status)->toBe(MaintenanceWindow::STATUS_CANCELLED);
    Artisan::shouldNotHaveReceived('call', ['up']);
    Notification::assertSentTo([$admin, $user], MaintenanceWindowNotification::class, function (MaintenanceWindowNotification $notification) {
        return $notification->event === 'cancelled';
    });
});

test('maintenance service activates due windows and deactivates expired ones', function () {
    Cache::flush();
    Artisan::spy();
    Notification::fake();

    $creator = makeAdminUser();

    $scheduled = MaintenanceWindow::create([
        'scheduled_start' => now()->subMinute(),
        'scheduled_end' => now()->addMinutes(30),
        'message' => 'Short maintenance window.',
        'status' => MaintenanceWindow::STATUS_SCHEDULED,
        'created_by' => $creator->id,
    ]);

    $service = app(MaintenanceWindowService::class);
    $service->activateDue();

    expect($scheduled->fresh()->status)->toBe(MaintenanceWindow::STATUS_ACTIVE);
    Artisan::shouldHaveReceived('call')->with('down', Mockery::on(function (array $args) use ($service) {
        return ($args['--secret'] ?? null) === $service->getBypassToken()
            && ($args['--render'] ?? null) === 'errors.maintenance'
            && ($args['--retry'] ?? null) === 60;
    }))->once();
    Notification::assertSentTo($creator, MaintenanceWindowNotification::class, function (MaintenanceWindowNotification $notification) {
        return $notification->event === 'active';
    });

    $scheduled->update([
        'scheduled_end' => now()->subMinute(),
    ]);

    $service->deactivateExpired();

    expect($scheduled->fresh()->status)->toBe(MaintenanceWindow::STATUS_COMPLETED);
    Artisan::shouldHaveReceived('call')->with('up')->once();
});

test('admin can start a scheduled maintenance window immediately for manual testing', function () {
    Cache::flush();
    Artisan::spy();
    Notification::fake();

    $admin = makeAdminUser();
    $window = MaintenanceWindow::create([
        'scheduled_start' => now()->addHour(),
        'scheduled_end' => now()->addHours(2),
        'message' => 'Manual maintenance smoke test.',
        'status' => MaintenanceWindow::STATUS_SCHEDULED,
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.maintenance.activate', $window));

    $response->assertRedirect(app(MaintenanceWindowService::class)->getBypassUrl('http://localhost'));
    expect($window->fresh()->status)->toBe(MaintenanceWindow::STATUS_ACTIVE);
    Artisan::shouldHaveReceived('call')->with('down', Mockery::type('array'))->once();
    Notification::assertSentTo($admin, MaintenanceWindowNotification::class, function (MaintenanceWindowNotification $notification) {
        return $notification->event === 'active';
    });
});

test('welcome page shows maintenance banner only within two hours', function () {
    MaintenanceWindow::create([
        'scheduled_start' => now()->addMinutes(90),
        'scheduled_end' => now()->addHours(3),
        'message' => 'Save your work.',
        'status' => MaintenanceWindow::STATUS_SCHEDULED,
        'created_by' => makeAdminUser()->id,
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSeeText('Scheduled maintenance on');
    $response->assertSeeText('Please save your work.');

    MaintenanceWindow::query()->delete();
    Cache::flush();

    MaintenanceWindow::create([
        'scheduled_start' => now()->addHours(5),
        'scheduled_end' => now()->addHours(6),
        'message' => 'Later maintenance window.',
        'status' => MaintenanceWindow::STATUS_SCHEDULED,
        'created_by' => makeAdminUser()->id,
    ]);

    $laterResponse = $this->get('/');

    $laterResponse->assertOk();
    $laterResponse->assertDontSeeText('Scheduled maintenance on');
});

test('welcome page includes csrf token for support widget requests', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('name="csrf-token"', false);
});

test('maintenance index is restricted to admins', function () {
    $user = User::factory()->create([
        'role' => User::ROLE_USER,
    ]);

    $response = $this->actingAs($user)->get(route('admin.maintenance.index'));

    $response->assertForbidden();
});

test('maintenance index shows a bypass url and reusable bypass path', function () {
    $admin = makeAdminUser();
    config(['app.url' => 'http://localhost']);

    $response = $this
        ->actingAs($admin)
        ->get('/admin/maintenance', ['Host' => '127.0.0.1:8000']);

    $token = app(MaintenanceWindowService::class)->getBypassToken();

    $response->assertOk();
    $response->assertSee('http://localhost/'.$token);
    $response->assertSee('/'.$token);
    expect(app(MaintenanceWindowService::class)->getBypassUrl('http://127.0.0.1:8000'))
        ->toBe('http://127.0.0.1:8000/'.$token);
});
