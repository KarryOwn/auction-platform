<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\Category;
use App\Models\Dispute;
use App\Models\User;

class SupportChatContextBuilder
{
    public function build(?User $user): string
    {
        $sections = [
            'Access scope: ' . ($user ? 'logged-in user private records plus public platform records.' : 'guest public platform records only.'),
            $this->publicPlatformContext(),
        ];

        if ($user) {
            $sections[] = $this->userContext($user);
        }

        return implode("\n\n", array_filter($sections));
    }

    private function publicPlatformContext(): string
    {
        $activeAuctionCount = Auction::query()->active()->count();

        $featuredAuctions = Auction::query()
            ->active()
            ->where('is_featured', true)
            ->orderBy('featured_position')
            ->orderBy('end_time')
            ->limit(3)
            ->get(['id', 'title', 'current_price', 'currency', 'end_time'])
            ->map(fn (Auction $auction) => $this->auctionLine($auction))
            ->implode('; ');

        $endingSoonAuctions = Auction::query()
            ->active()
            ->where('end_time', '<=', now()->addHours(6))
            ->orderBy('end_time')
            ->limit(3)
            ->get(['id', 'title', 'current_price', 'currency', 'end_time'])
            ->map(fn (Auction $auction) => $this->auctionLine($auction))
            ->implode('; ');

        $categories = Category::query()
            ->active()
            ->root()
            ->ordered()
            ->limit(6)
            ->pluck('name')
            ->implode(', ');

        return implode("\n", array_filter([
            'Public platform context:',
            "- Active auctions: {$activeAuctionCount}.",
            '- Featured auctions: ' . ($featuredAuctions !== '' ? $featuredAuctions : 'none currently listed.'),
            '- Ending soon: ' . ($endingSoonAuctions !== '' ? $endingSoonAuctions : 'none within the next 6 hours.'),
            '- Root categories: ' . ($categories !== '' ? $categories : 'none configured.'),
            '- Useful pages: browse auctions at ' . $this->safeRoute('auctions.index') . ', wallet at ' . $this->safeRoute('user.wallet') . ', bid history at ' . $this->safeRoute('user.bids') . ', invoices at ' . $this->safeRoute('user.invoices') . '.',
        ]));
    }

    private function userContext(User $user): string
    {
        $user->loadMissing('sellerApplication');

        $walletTransactions = $user->walletTransactions()
            ->latest()
            ->limit(3)
            ->get(['type', 'amount', 'balance_after', 'description', 'created_at'])
            ->map(fn ($transaction) => sprintf(
                '%s %s, balance after %s%s',
                str_replace('_', ' ', (string) $transaction->type),
                $this->money($transaction->amount),
                $this->money($transaction->balance_after),
                $transaction->description ? " ({$transaction->description})" : ''
            ))
            ->implode('; ');

        $recentBids = $user->bids()
            ->with('auction:id,title,status,end_time,current_price,currency')
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn ($bid) => sprintf(
                '%s on %s (%s)',
                $this->money($bid->amount, $bid->auction?->currency ?? 'USD'),
                $bid->auction?->title ?? 'unknown auction',
                $bid->auction?->status ?? 'unknown status'
            ))
            ->implode('; ');

        $buyerInvoices = $user->invoicesAsBuyer()
            ->with('auction:id,title')
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn ($invoice) => sprintf(
                '%s %s for %s (%s)',
                $invoice->invoice_number,
                $this->money($invoice->total, $invoice->currency),
                $invoice->auction?->title ?? 'unknown auction',
                $invoice->status
            ))
            ->implode('; ');

        $wonAuctions = $user->wonAuctions()
            ->latest('closed_at')
            ->limit(3)
            ->get(['title', 'winning_bid_amount', 'currency', 'payment_status', 'closed_at'])
            ->map(fn (Auction $auction) => sprintf(
                '%s won for %s, payment %s',
                $auction->title,
                $this->money($auction->winning_bid_amount, $auction->currency),
                $auction->payment_status ?? 'not recorded'
            ))
            ->implode('; ');

        $disputes = Dispute::query()
            ->with('auction:id,title')
            ->where(function ($query) use ($user) {
                $query->where('claimant_id', $user->id)
                    ->orWhere('respondent_id', $user->id);
            })
            ->latest()
            ->limit(3)
            ->get()
            ->map(fn (Dispute $dispute) => sprintf(
                '%s dispute on %s (%s)',
                str_replace('_', ' ', (string) $dispute->type),
                $dispute->auction?->title ?? 'unknown auction',
                $dispute->status_label
            ))
            ->implode('; ');

        $sellerStatus = $user->seller_application_status
            ? "seller application {$user->seller_application_status}"
            : 'no seller application status';

        if ($user->sellerApplication) {
            $sellerStatus .= ", latest application {$user->sellerApplication->status}";
        }

        return implode("\n", [
            'Logged-in user context:',
            "- Account: {$user->name}, role {$user->role}, " . ($user->is_banned ? 'banned' : 'active') . ", {$sellerStatus}.",
            '- Seller verification: ' . ($user->isVerifiedSeller() ? 'verified seller.' : 'not a verified seller.'),
            '- Wallet: available ' . $this->money($user->wallet_balance) . ', held ' . $this->money($user->held_balance) . ', pending payout ' . $this->money($user->pending_payout_balance) . '.',
            '- Recent wallet transactions: ' . ($walletTransactions !== '' ? $walletTransactions : 'none found.'),
            '- Recent bids: ' . ($recentBids !== '' ? $recentBids : 'none found.'),
            '- Recent buyer invoices: ' . ($buyerInvoices !== '' ? $buyerInvoices : 'none found.'),
            '- Recently won auctions: ' . ($wonAuctions !== '' ? $wonAuctions : 'none found.'),
            '- Related disputes: ' . ($disputes !== '' ? $disputes : 'none found.'),
        ]);
    }

    private function auctionLine(Auction $auction): string
    {
        return sprintf(
            '%s at %s ending %s',
            $auction->title,
            $this->money($auction->current_price, $auction->currency),
            $auction->end_time?->diffForHumans() ?? 'unknown'
        );
    }

    private function money(mixed $amount, string $currency = 'USD'): string
    {
        return strtoupper($currency) . ' ' . number_format((float) $amount, 2);
    }

    private function safeRoute(string $name): string
    {
        try {
            return route($name);
        } catch (\Throwable) {
            return $name;
        }
    }
}
