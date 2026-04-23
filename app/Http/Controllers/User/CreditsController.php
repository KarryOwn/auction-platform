<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CreditsController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user()->loadCount([
            'auctions as active_auction_count' => fn ($query) => $query->where('status', Auction::STATUS_ACTIVE),
        ]);

        $eligibleAuctions = $user->auctions()
            ->where('status', Auction::STATUS_ACTIVE)
            ->orderByDesc('is_featured')
            ->orderBy('end_time')
            ->get(['id', 'title', 'current_price', 'end_time', 'is_featured', 'featured_until']);

        $recentTransactions = $user->walletTransactions()
            ->latest()
            ->limit(5)
            ->get();

        return view('user.credits.index', [
            'user' => $user,
            'availableBalance' => $user->availableBalance(),
            'powerUps' => $this->powerUps(),
            'eligibleAuctions' => $eligibleAuctions,
            'recentTransactions' => $recentTransactions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $powerUps = $this->powerUps();

        $validated = $request->validate([
            'power_up' => ['required', Rule::in(array_keys($powerUps))],
            'auction_id' => [
                'required',
                Rule::exists('auctions', 'id')->where(fn ($query) => $query
                    ->where('user_id', $request->user()->id)
                    ->where('status', Auction::STATUS_ACTIVE)
                ),
            ],
        ]);

        $package = $powerUps[$validated['power_up']];
        $auction = Auction::query()
            ->where('user_id', $request->user()->id)
            ->where('status', Auction::STATUS_ACTIVE)
            ->findOrFail($validated['auction_id']);

        if (! $request->user()->canAfford($package['price'])) {
            throw ValidationException::withMessages([
                'power_up' => 'Your available credits balance is too low for this power-up.',
            ]);
        }

        DB::transaction(function () use ($request, $auction, $package): void {
            $this->walletService->withdraw(
                $request->user(),
                $package['price'],
                "Power-up purchase: {$package['name']} for {$auction->title}",
                $auction,
            );

            $this->applyPowerUp($auction, $package);
        });

        return redirect()
            ->route('user.credits.index')
            ->with('success', "{$package['name']} applied to {$auction->title}.");
    }

    public function stripeCheckout(Request $request): RedirectResponse
    {
        $powerUps = $this->powerUps();

        $validated = $request->validate([
            'power_up' => ['required', Rule::in(array_keys($powerUps))],
            'auction_id' => [
                'required',
                Rule::exists('auctions', 'id')->where(fn ($query) => $query
                    ->where('user_id', $request->user()->id)
                    ->where('status', Auction::STATUS_ACTIVE)
                ),
            ],
        ]);

        $package = $powerUps[$validated['power_up']];
        $auction = Auction::query()
            ->where('user_id', $request->user()->id)
            ->where('status', Auction::STATUS_ACTIVE)
            ->findOrFail($validated['auction_id']);

        $request->session()->put('pending_power_up_checkout', [
            'user_id' => $request->user()->id,
            'auction_id' => $auction->id,
            'power_up' => $validated['power_up'],
        ]);

        $session = $this->createStripeCheckoutSession($request, $auction, $package, $validated['power_up']);

        return redirect()->away($session->url);
    }

    public function stripeSuccess(Request $request): RedirectResponse
    {
        $pending = $request->session()->pull('pending_power_up_checkout');

        if (! $pending || (int) $pending['user_id'] !== (int) $request->user()->id) {
            return redirect()
                ->route('user.credits.index')
                ->with('error', 'Power-up checkout could not be verified.');
        }

        $powerUps = $this->powerUps();
        $package = $powerUps[$pending['power_up']] ?? null;
        $auction = Auction::query()
            ->where('user_id', $request->user()->id)
            ->where('status', Auction::STATUS_ACTIVE)
            ->find($pending['auction_id']);

        if (! $package || ! $auction) {
            return redirect()
                ->route('user.credits.index')
                ->with('error', 'Power-up target is no longer available.');
        }

        $this->applyPowerUp($auction, $package);

        return redirect()
            ->route('user.credits.index')
            ->with('success', "{$package['name']} applied to {$auction->title} after Stripe checkout.");
    }

    protected function createStripeCheckoutSession(Request $request, Auction $auction, array $package, string $key): object
    {
        if (app()->environment('testing')) {
            return (object) [
                'id' => 'cs_test_power_up',
                'url' => 'https://checkout.stripe.test/power-up',
            ];
        }

        return \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => (int) round($package['price'] * 100),
                    'product_data' => [
                        'name' => $package['name'],
                        'description' => "Power-up for {$auction->title}",
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'client_reference_id' => (string) $request->user()->id,
            'metadata' => [
                'type' => 'power_up',
                'power_up' => $key,
                'auction_id' => (string) $auction->id,
                'user_id' => (string) $request->user()->id,
            ],
            'success_url' => route('user.credits.stripe.success'),
            'cancel_url' => route('user.credits.index'),
        ]);
    }

    protected function applyPowerUp(Auction $auction, array $package): void
    {
        $currentUntil = $auction->featured_until?->isFuture()
            ? $auction->featured_until
            : now();

        $auction->forceFill([
            'is_featured' => true,
            'featured_until' => $currentUntil->copy()->addHours($package['duration_hours']),
            'featured_position' => $package['position'],
        ])->save();

        Cache::forget('featured_auctions');
    }

    /**
     * @return array<string, array{name: string, price: float, duration_hours: int, position: int, badge: string, description: string}>
     */
    protected function powerUps(): array
    {
        return [
            'spotlight' => [
                'name' => 'Spotlight Boost',
                'price' => 5.00,
                'duration_hours' => 24,
                'position' => 30,
                'badge' => '24 hour lift',
                'description' => 'Add a featured badge and lift one active auction into promoted placements for a day.',
            ],
            'homepage' => [
                'name' => 'Homepage Feature',
                'price' => 12.00,
                'duration_hours' => 72,
                'position' => 10,
                'badge' => '3 day run',
                'description' => 'Keep a priority listing eligible for homepage feature slots through the busiest browse window.',
            ],
            'launch_week' => [
                'name' => 'Launch Week Push',
                'price' => 25.00,
                'duration_hours' => 168,
                'position' => 1,
                'badge' => '7 day priority',
                'description' => 'Give a premium item a week of top-tier featured placement and stronger browse visibility.',
            ],
        ];
    }
}
