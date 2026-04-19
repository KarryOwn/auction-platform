<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ImportController extends Controller
{
    public function create(): Response
    {
        return response()->view('seller.auctions.import');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $file = $request->file('file');
        if (! $file) {
            return back()->withErrors(['file' => 'No file uploaded.']);
        }

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return back()->withErrors(['file' => 'Unable to read the uploaded file.']);
        }

        $rawHeader = fgetcsv($handle);
        if (! is_array($rawHeader) || $rawHeader === []) {
            fclose($handle);

            return back()->withErrors(['file' => 'CSV file is empty or missing a header row.']);
        }

        $header = collect($rawHeader)
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->values()
            ->all();

        $requiredColumns = ['title', 'description', 'starting_price', 'end_time'];
        $missingColumns = array_values(array_diff($requiredColumns, $header));

        if ($missingColumns !== []) {
            fclose($handle);

            return back()->withErrors([
                'file' => 'Missing required column(s): '.implode(', ', $missingColumns).'. Required columns are: '.implode(', ', $requiredColumns).'.',
            ]);
        }

        $created = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $record = $this->mapRecord($header, $row);
            $validation = Validator::make($record, [
                'title' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string'],
                'starting_price' => ['required', 'numeric', 'gt:0'],
                'reserve_price' => ['nullable', 'numeric', 'gt:0'],
                'min_bid_increment' => ['nullable', 'numeric', 'gt:0'],
                'start_time' => ['nullable', 'date'],
                'end_time' => ['required', 'date'],
                'condition' => ['nullable', 'string', 'in:'.implode(',', array_keys(Auction::CONDITIONS))],
                'tags' => ['nullable', 'string'],
            ]);

            if ($validation->fails()) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => $validation->errors()->first(),
                ];
                continue;
            }

            try {
                $startingPrice = (float) $record['starting_price'];
                $reservePrice = $record['reserve_price'] !== null && $record['reserve_price'] !== ''
                    ? (float) $record['reserve_price']
                    : null;

                if ($reservePrice !== null && $reservePrice <= $startingPrice) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Reserve price must be greater than starting price.',
                    ];
                    continue;
                }

                $startTime = ! empty($record['start_time']) ? Carbon::parse((string) $record['start_time']) : now();
                $endTime = Carbon::parse((string) $record['end_time']);

                if ($endTime->lessThanOrEqualTo($startTime)) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'End time must be later than start time.',
                    ];
                    continue;
                }

                if ($startingPrice <= 0) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'message' => 'Starting price must be greater than zero.',
                    ];
                    continue;
                }

                $tagList = collect(explode(',', (string) ($record['tags'] ?? '')))
                    ->map(fn ($tag) => trim($tag))
                    ->filter()
                    ->unique()
                    ->take(10)
                    ->values();

                $auction = Auction::create([
                    'user_id' => $request->user()->id,
                    'title' => (string) $record['title'],
                    'description' => (string) $record['description'],
                    'starting_price' => $startingPrice,
                    'current_price' => $startingPrice,
                    'reserve_price' => $reservePrice,
                    'reserve_met' => false,
                    'min_bid_increment' => $record['min_bid_increment'] !== null && $record['min_bid_increment'] !== ''
                        ? (float) $record['min_bid_increment']
                        : (float) config('auction.min_bid_increment', 1.00),
                    'snipe_threshold_seconds' => (int) config('auction.snipe.threshold_seconds', 30),
                    'snipe_extension_seconds' => (int) config('auction.snipe.extension_seconds', 30),
                    'max_extensions' => (int) config('auction.snipe.max_extensions', 10),
                    'currency' => (string) config('auction.currency', 'USD'),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'status' => Auction::STATUS_DRAFT,
                    'condition' => $record['condition'] ?: null,
                ]);

                if ($tagList->isNotEmpty()) {
                    $tagIds = app(\App\Services\TagService::class)->findOrCreateMany($tagList->all());
                    $auction->tags()->sync($tagIds);
                }

                $created++;
            } catch (Throwable $exception) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Failed to import row: '.$exception->getMessage(),
                ];
            }
        }

        fclose($handle);

        return back()->with([
            'import_created' => $created,
            'import_errors' => $errors,
        ]);
    }

    public function template(): Response
    {
        $template = implode("\n", [
            'title,description,starting_price,reserve_price,min_bid_increment,start_time,end_time,condition,tags',
            'Vintage Camera,"Excellent condition film camera with original case",120.00,180.00,5.00,2026-05-01 10:00:00,2026-05-10 18:00:00,used_good,"camera,vintage,collectible"',
        ]);

        return response($template, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="auction-import-template.csv"',
        ]);
    }

    /**
     * @param array<int, string|null> $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, string> $header
     * @param array<int, string|null> $row
     * @return array<string, string|null>
     */
    private function mapRecord(array $header, array $row): array
    {
        $record = [];

        foreach ($header as $index => $column) {
            $record[$column] = array_key_exists($index, $row)
                ? trim((string) $row[$index])
                : null;
        }

        return $record;
    }
}
