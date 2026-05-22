<?php

namespace App\Services;

use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Notifications\MaintenanceWindowNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class MaintenanceWindowService
{
    private const CACHE_KEY = 'maintenance:upcoming';
    private const CACHE_TTL = 60;

    public function getUpcoming(): ?MaintenanceWindow
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => MaintenanceWindow::query()
            ->where('status', MaintenanceWindow::STATUS_SCHEDULED)
            ->where('scheduled_start', '<=', now()->addHours(24))
            ->orderBy('scheduled_start')
            ->first());
    }

    public function getActive(): ?MaintenanceWindow
    {
        return MaintenanceWindow::query()
            ->where('status', MaintenanceWindow::STATUS_ACTIVE)
            ->orderBy('scheduled_start')
            ->first();
    }

    public function forgetUpcomingCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function getBypassToken(): string
    {
        $configuredToken = config('app.maintenance_bypass_token');

        if (is_string($configuredToken) && $configuredToken !== '') {
            return $configuredToken;
        }

        return 'admin-bypass-' . md5((string) config('app.key'));
    }

    public function getBypassUrl(?string $root = null): string
    {
        if ($root) {
            return rtrim($root, '/') . '/' . ltrim($this->getBypassToken(), '/');
        }

        return url('/' . ltrim($this->getBypassToken(), '/'));
    }

    public function notifyUsers(MaintenanceWindow $window, string $event): void
    {
        User::query()
            ->whereNull('deleted_at')
            ->chunkById(100, function ($users) use ($window, $event): void {
                Notification::send($users, new MaintenanceWindowNotification($window, $event));
            });
    }

    public function activateDue(): void
    {
        $window = MaintenanceWindow::query()
            ->where('status', MaintenanceWindow::STATUS_SCHEDULED)
            ->where('scheduled_start', '<=', now())
            ->orderBy('scheduled_start')
            ->first();

        if (! $window) {
            return;
        }

        $this->activate($window);
    }

    public function activate(MaintenanceWindow $window): void
    {
        if (! in_array($window->status, [MaintenanceWindow::STATUS_SCHEDULED, MaintenanceWindow::STATUS_ACTIVE], true)) {
            return;
        }

        $window->update([
            'status' => MaintenanceWindow::STATUS_ACTIVE,
            'scheduled_start' => $window->scheduled_start?->isFuture() ? now() : $window->scheduled_start,
        ]);
        $this->forgetUpcomingCache();

        Artisan::call('down', [
            '--secret' => $this->getBypassToken(),
            '--render' => 'errors.maintenance',
            '--retry' => 60,
        ]);

        $this->notifyUsers($window->fresh(), 'active');
    }

    public function deactivateExpired(): void
    {
        $window = MaintenanceWindow::query()
            ->where('status', MaintenanceWindow::STATUS_ACTIVE)
            ->where('scheduled_end', '<=', now())
            ->orderBy('scheduled_end')
            ->first();

        if (! $window) {
            return;
        }

        $window->update(['status' => MaintenanceWindow::STATUS_COMPLETED]);
        $this->forgetUpcomingCache();

        Artisan::call('up');
    }

    public function cancel(MaintenanceWindow $window): void
    {
        $wasActive = $window->status === MaintenanceWindow::STATUS_ACTIVE;

        $window->update(['status' => MaintenanceWindow::STATUS_CANCELLED]);
        $this->forgetUpcomingCache();

        if ($wasActive) {
            Artisan::call('up');
        }
    }
}
