<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\TaxDocument;
use App\Services\TaxDocumentService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TaxDocumentController extends Controller
{
    public function __construct(protected TaxDocumentService $service) {}

    public function index(Request $request)
    {
        $seller    = $request->user();
        $documents = TaxDocument::where('user_id', $seller->id)
            ->orderByDesc('period_start')
            ->paginate(20);

        // Build available years from closed auctions
        $availableYears = \App\Models\Auction::where('user_id', $seller->id)
            ->where('status', 'completed')
            ->whereNotNull('closed_at')
            ->selectRaw('EXTRACT(YEAR FROM closed_at)::int AS year')
            ->distinct()
            ->pluck('year')
            ->toArray();

        return view('seller.tax-documents.index', compact('documents', 'availableYears'));
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'period_type'  => ['required', 'in:annual,quarterly,monthly'],
            'year'         => ['required', 'integer', 'min:2020', 'max:' . now()->year],
            'quarter'      => ['nullable', 'integer', 'between:1,4'],
            'month'        => ['nullable', 'integer', 'between:1,12'],
        ]);

        $seller = $request->user();

        [$from, $to, $label] = $this->resolvePeriod($validated);

        // Upsert tax document record
        $doc = TaxDocument::firstOrNew([
            'user_id'      => $seller->id,
            'period_label' => $label,
            'period_type'  => $validated['period_type'],
        ]);

        $summary = $this->service->compileSummary($seller, $from, $to);
        $path    = $this->service->generatePdf($seller, $from, $to, $label);

        $doc->fill([
            'period_start'       => $from->toDateString(),
            'period_end'         => $to->toDateString(),
            'file_path'          => $path,
            'gross_sales'        => $summary['gross_sales'],
            'platform_fees_paid' => $summary['platform_fees'],
            'listing_fees_paid'  => $summary['listing_fees'],
            'net_revenue'        => $summary['net_revenue'],
            'refunds_issued'     => $summary['refunds_issued'],
        ])->save();

        return redirect()->route('seller.tax-documents.index')
            ->with('status', 'Tax document generated successfully.');
    }

    public function download(TaxDocument $document)
    {
        abort_unless($document->user_id === request()->user()->id, 403);
        $fullPath = storage_path("app/{$document->file_path}");
        abort_unless(file_exists($fullPath), 404);
        return response()->download($fullPath);
    }

    private function resolvePeriod(array $v): array
    {
        $year = (int) $v['year'];

        return match ($v['period_type']) {
            'annual' => [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
                (string) $year,
            ],
            'quarterly' => [
                Carbon::create($year, ($v['quarter'] - 1) * 3 + 1, 1)->startOfDay(),
                Carbon::create($year, $v['quarter'] * 3, 1)->endOfMonth()->endOfDay(),
                "{$year}-Q{$v['quarter']}",
            ],
            'monthly' => [
                Carbon::create($year, $v['month'], 1)->startOfDay(),
                Carbon::create($year, $v['month'], 1)->endOfMonth()->endOfDay(),
                sprintf('%d-%02d', $year, $v['month']),
            ],
        };
    }
}
