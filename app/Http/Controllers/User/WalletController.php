<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService,
    ) {}

    public function show(Request $request)
    {
        $user = $request->user();

        $query = $user->walletTransactions()->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        $transactions = $query->paginate(20)->withQueryString();

        $activeHolds = $user->escrowHolds()->active()->with('auction:id,title')->get();

        return view('user.wallet', compact('user', 'transactions', 'activeHolds'));
    }

    public function topUp(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:50000',
        ]);

        $user   = $request->user();
        $amount = (float) $validated['amount'];

        // directly credit the wallet 
        $this->walletService->deposit($user, $amount, 'Wallet top-up');

        return redirect()->route('user.wallet')
            ->with('success', 'Wallet topped up by $' . number_format($amount, 2));
    }

    public function withdraw(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:50000',
        ]);

        $user   = $request->user();
        $amount = (float) $validated['amount'];

        if (! $user->canAfford($amount)) {
            return back()->withErrors([
                'amount' => 'Insufficient available balance. You have $'
                    . number_format($user->availableBalance(), 2)
                    . ' available (excluding held funds).',
            ]);
        }

        $this->walletService->withdraw($user, $amount, 'Wallet withdrawal');

        return redirect()->route('user.wallet')
            ->with('success', 'Withdrew $' . number_format($amount, 2) . ' from wallet.');
    }

    public function exportTransactions(Request $request): StreamedResponse
    {
        $user = $request->user();
        $transactions = $user->walletTransactions()->latest()->get();

        return response()->streamDownload(function () use ($transactions) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Date', 'Type', 'Description', 'Amount', 'Balance After']);

            foreach ($transactions as $tx) {
                fputcsv($handle, [
                    $tx->created_at->format('Y-m-d H:i:s'),
                    ucfirst(str_replace('_', ' ', $tx->type)),
                    $tx->description ?? '-',
                    ($tx->isCredit() ? '+' : '-') . number_format(abs((float) $tx->amount), 2),
                    number_format((float) $tx->balance_after, 2),
                ]);
            }

            fclose($handle);
        }, 'wallet-transactions-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
