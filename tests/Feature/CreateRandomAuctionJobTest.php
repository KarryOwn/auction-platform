<?php

use App\Jobs\CreateRandomAuction;
use App\Models\Auction;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;

test('create random auction job attaches a generated image', function () {
    Storage::fake('public');

    Category::create([
        'name' => 'Random Job Category',
        'is_active' => true,
    ]);

    createSeller();

    (new CreateRandomAuction())->handle();

    $auction = Auction::query()->latest('id')->first();

    expect($auction)->not->toBeNull()
        ->and($auction->getMedia('images'))->toHaveCount(1)
        ->and($auction->getFirstMedia('images')?->file_name)->toBe("seed-{$auction->id}-1.png")
        ->and($auction->getFirstMedia('images')?->disk)->toBe('public');
});
