<?php

use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Services\MaintenanceWindowService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

function makeAdminUser(): User
{
    return User::factory()->create([
        'role' => User::ROLE_ADMIN,
    ]);
}

test('admin can schedule update and cancel maintenance windows', function () {
    $admin = makeAdminUser();

    $createResponse = $this->actingAs($admin)->post(route('admin.maintenance.store'), [
        'scheduled_start' => now()->addHours(3)->format('Y-m-d H:i:s'),
        'scheduled_end' => now()->addHours(4)->format('Y-m-d H:i:s'),
        'message' => 'Database upgrades in progress.',
    ]);

    $createResponse->assertRedirect(route('admin.maintenance.index'));

    $window = MaintenanceWindow::query()->firstOrFail();

    expect($window->status)->toBe(MaintenanceWindow::STATUS_SCHEDULED);
    expect($window->created_by)->toBe($admin->id);

    $updateResponse = $this->actingAs($admin)->patch(route('admin.maintenance.update', $window), [
        'scheduled_start' => now()->addHours(5)->format('Y-m-d H:i:s'),
        'scheduled_end' => now()->addHours(6)->format('Y-m-d H:i:s'),
        'message' => 'Maintenance rescheduled.',
    ]);

    $updateResponse->assertRedirect(route('admin.maintenance.index'));

    expect($window->fresh()->message)->toBe('Maintenance rescheduled.');

    Artisan::spy();

    $cancelResponse = $this->actingAs($admin)->post(route('admin.maintenance.cancel', $window));

    $cancelResponse->assertRedirect(route('admin.maintenance.index'));
    expect($window->fresh()->status)->toBe(MaintenanceWindow::STATUS_CANCELLED);
    Artisan::shouldNotHaveReceived('call', ['up']);
});

test('maintenance service activates due windows and deactivates expired ones', function () {
    Cache::flush();
    Artisan::spy();

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

    $scheduled->update([
        'scheduled_end' => now()->subMinute(),
    ]);

    $service->deactivateExpired();

    expect($scheduled->fresh()->status)->toBe(MaintenanceWindow::STATUS_COMPLETED);
    Artisan::shouldHaveReceived('call')->with('up')->once();
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
