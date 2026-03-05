<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EscrowHold;
use App\Models\Invoice;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        // Recent payments
        $payments = WalletTransaction::where('type', WalletTransaction::TYPE_PAYMENT)
            ->with('user:id,name,email')
            ->latest()
            ->paginate(20, ['*'], 'payments_page');

        // Active escrow holds
        $activeHolds = EscrowHold::where('status', EscrowHold::STATUS_ACTIVE)
            ->with(['user:id,name,email', 'auction:id,title'])
            ->latest()
            ->paginate(20, ['*'], 'holds_page');

        // Recent invoices
        $invoices = Invoice::with(['buyer:id,name', 'seller:id,name', 'auction:id,title'])
            ->latest()
            ->paginate(20, ['*'], 'invoices_page');

        // Summary stats
        $stats = [
            'total_escrow'     => EscrowHold::where('status', EscrowHold::STATUS_ACTIVE)->sum('amount'),
            'total_captured'   => EscrowHold::where('status', EscrowHold::STATUS_CAPTURED)->sum('amount'),
            'total_refunded'   => WalletTransaction::where('type', WalletTransaction::TYPE_REFUND)->sum('amount'),
            'total_revenue'    => Invoice::where('status', Invoice::STATUS_PAID)->sum('platform_fee'),
            'pending_payments' => EscrowHold::where('status', EscrowHold::STATUS_ACTIVE)->count(),
        ];

        return view('admin.payments.index', compact('payments', 'activeHolds', 'invoices', 'stats'));
    }

    public function transactions(Request $request)
    {
        $query = WalletTransaction::with('user:id,name,email')->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $transactions = $query->paginate(50);

        return view('admin.payments.transactions', compact('transactions'));
    }
}
