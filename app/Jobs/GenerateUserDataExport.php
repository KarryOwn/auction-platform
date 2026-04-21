<?php

namespace App\Jobs;

use App\Models\DataExportRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ZipArchive;

class GenerateUserDataExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes max

    public function __construct(public int $exportRequestId) {}

    public function handle(): void
    {
        $request = DataExportRequest::findOrFail($this->exportRequestId);
        $user    = User::findOrFail($request->user_id);

        $request->update(['status' => 'processing']);

        // Build data arrays
        $data = [
            'account'       => $this->exportAccount($user),
            'bids'          => $this->exportBids($user),
            'auctions'      => $this->exportAuctions($user),
            'messages'      => $this->exportMessages($user),
            'wallet'        => $this->exportWallet($user),
            'notifications' => $this->exportNotifications($user),
        ];

        // Create ZIP with CSV files
        $dir      = storage_path("app/private/exports/{$user->id}");
        @mkdir($dir, 0755, true);
        $zipPath  = "{$dir}/data-export-{$user->id}-" . now()->format('YmdHis') . ".zip";

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($data as $section => $rows) {
            if (empty($rows)) {
                continue;
            }
            $csvContent = $this->toCsv(array_keys($rows[0] ?? []), $rows);
            $zip->addFromString("{$section}.csv", $csvContent);
        }

        $zip->close();

        $relativePath = "private/exports/{$user->id}/" . basename($zipPath);
        $request->update([
            'status'    => 'ready',
            'file_path' => $relativePath,
            'ready_at'  => now(),
            'expires_at'=> now()->addDays(7),
        ]);
        
        // Optional: notify the user
        // $user->notify(new DataExportReadyNotification($request));
    }

    private function exportAccount(User $user): array
    {
        return [[
            'name'       => $user->name,
            'email'      => $user->email,
            'role'       => $user->role,
            'joined_at'  => $user->created_at->toIso8601String(),
            'seller_bio' => $user->seller_bio,
        ]];
    }

    private function exportBids(User $user): array
    {
        return $user->bids()
            ->with('auction:id,title')
            ->orderBy('created_at')
            ->get(['auction_id', 'amount', 'bid_type', 'created_at'])
            ->map(fn ($b) => [
                'auction_id'    => $b->auction_id,
                'auction_title' => $b->auction?->title,
                'amount'        => (float) $b->amount,
                'type'          => $b->bid_type,
                'placed_at'     => $b->created_at->toIso8601String(),
            ])
            ->all();
    }

    private function exportWallet(User $user): array
    {
        return $user->walletTransactions()
            ->orderBy('created_at')
            ->get(['type', 'amount', 'balance_after', 'description', 'created_at'])
            ->map(fn ($t) => [
                'type'          => $t->type,
                'amount'        => (float) $t->amount,
                'balance_after' => (float) $t->balance_after,
                'description'   => $t->description,
                'date'          => $t->created_at->toIso8601String(),
            ])
            ->all();
    }

    private function exportMessages(User $user): array
    {
        return $user->sentMessages()
            ->with('conversation.auction:id,title')
            ->orderBy('created_at')
            ->get(['body', 'created_at', 'conversation_id'])
            ->map(fn ($m) => [
                'conversation_id' => $m->conversation_id,
                'auction_title'   => $m->conversation?->auction?->title,
                'body'            => $m->body,
                'sent_at'         => $m->created_at->toIso8601String(),
            ])
            ->all();
    }

    private function exportAuctions(User $user): array
    {
        return $user->auctions()
            ->orderBy('created_at')
            ->get(['id', 'title', 'status', 'starting_price', 'current_price', 'created_at'])
            ->map(fn ($a) => [
                'id'             => $a->id,
                'title'          => $a->title,
                'status'         => $a->status,
                'starting_price' => (float) $a->starting_price,
                'final_price'    => (float) $a->current_price,
                'created_at'     => $a->created_at->toIso8601String(),
            ])
            ->all();
    }

    private function exportNotifications(User $user): array
    {
        return $user->notifications()
            ->orderBy('created_at')
            ->get(['type', 'data', 'read_at', 'created_at'])
            ->map(fn ($n) => [
                'type'       => class_basename($n->type),
                'message'    => $n->data['message'] ?? '',
                'read'       => $n->read_at ? 'yes' : 'no',
                'created_at' => $n->created_at->toIso8601String(),
            ])
            ->all();
    }

    private function toCsv(array $headers, array $rows): string
    {
        $output = fopen('php://temp', 'w');
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, array_values($row));
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        return $content;
    }
}
