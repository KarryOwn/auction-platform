<?php

namespace App\Services;

use App\Models\Auction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuctionCloneService
{
    /**
     * Clone an auction as a new draft belonging to $requester.
     */
    public function clone(Auction $source, User $requester): Auction
    {
        // Authorization: only owner or admin may clone
        if ($source->user_id !== $requester->id && ! $requester->isStaff()) {
            throw new \DomainException('You do not have permission to re-list this auction.');
        }

        return DB::transaction(function () use ($source, $requester) {
            $clone = Auction::create([
                'user_id'                  => $requester->id,
                'title'                    => $source->title,
                'description'              => $source->description,
                'starting_price'           => $source->starting_price,
                'current_price'            => $source->starting_price,
                'reserve_price'            => $source->reserve_price,
                'reserve_price_visible'    => $source->reserve_price_visible,
                'reserve_met'              => false,
                'min_bid_increment'        => $source->min_bid_increment,
                'snipe_threshold_seconds'  => $source->snipe_threshold_seconds,
                'snipe_extension_seconds'  => $source->snipe_extension_seconds,
                'max_extensions'           => $source->max_extensions,
                'currency'                 => $source->currency,
                'condition'                => $source->condition,
                'brand_id'                 => $source->brand_id,
                'sku'                      => $source->sku,
                'serial_number'            => $source->serial_number,
                'video_url'                => $source->video_url,
                'buy_it_now_price'         => $source->buy_it_now_price,
                'buy_it_now_enabled'       => false, // seller must re-enable after review
                'status'                   => Auction::STATUS_DRAFT,
                'cloned_from_auction_id'   => $source->id,
                // end_time intentionally null — seller must set new dates
            ]);

            // Clone categories
            $catSync = [];
            foreach ($source->categories as $cat) {
                $catSync[$cat->id] = ['is_primary' => (bool) $cat->pivot->is_primary];
            }
            $clone->categories()->sync($catSync);

            // Clone tags
            $clone->tags()->sync($source->tags->pluck('id')->all());

            // Clone attribute values
            foreach ($source->attributeValues as $av) {
                $clone->attributeValues()->create([
                    'attribute_id' => $av->attribute_id,
                    'value'        => $av->value,
                ]);
            }

            // Clone media — copy files via Spatie
            foreach ($source->getMedia('images') as $media) {
                try {
                    $clone->addMedia($media->getPath())
                          ->preservingOriginal()
                          ->usingFileName($media->file_name)
                          ->toMediaCollection('images');
                } catch (\Throwable $e) {
                    // Log but don't fail entire clone
                    \Illuminate\Support\Facades\Log::warning('AuctionCloneService: media copy failed', [
                        'media_id'   => $media->id,
                        'auction_id' => $source->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            \App\Models\AuditLog::record('auction.cloned', Auction::class, $clone->id, [
                'source_auction_id' => $source->id,
            ]);

            return $clone;
        });
    }
}
