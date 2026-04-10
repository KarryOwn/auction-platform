<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Auction;
use App\Models\AuctionAttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Generates realistic completed auctions with attribute values for price prediction training.
 *
 * Run with:  php artisan db:seed --class=PriceModelSeeder
 */
class PriceModelSeeder extends Seeder
{
    // ── Attribute slugs expected from AttributeSeeder ────────────
    private const SLUG_STORAGE   = 'storage';
    private const SLUG_RAM       = 'ram';
    private const SLUG_SCREEN    = 'screen-size';
    private const SLUG_PROCESSOR = 'processor';
    private const SLUG_COLOR     = 'color';
    private const SLUG_SIZE      = 'size';
    private const SLUG_MATERIAL  = 'material';
    private const SLUG_SHOE_SIZE = 'shoe-size';
    private const SLUG_CASE_MAT  = 'case-material';
    private const SLUG_MOVEMENT  = 'movement-type';
    private const SLUG_MILEAGE   = 'mileage';
    private const SLUG_YEAR      = 'year';
    private const SLUG_FUEL      = 'fuel-type';
    private const SLUG_TRANS     = 'transmission';
    private const SLUG_WEIGHT    = 'weight';

    public function run(): void
    {
        $seller = User::where('role', 'seller')->first()
            ?? User::first();

        if (! $seller) {
            $this->command->error('No users found. Run DatabaseSeeder first.');
            return;
        }

        $attrs   = Attribute::all()->keyBy('slug');
        $brands  = Brand::all()->keyBy('name');
        $cats    = Category::all()->keyBy('name');
        $total   = 0;

        $datasets = [
            [$this, 'seedSmartphones'],
            [$this, 'seedLaptops'],
            [$this, 'seedTablets'],
            [$this, 'seedWatches'],
            [$this, 'seedShoes'],
            [$this, 'seedClothing'],
            [$this, 'seedCars'],
            [$this, 'seedCameras'],
            [$this, 'seedGaming'],
        ];

        foreach ($datasets as $seeder) {
            $count = call_user_func($seeder, $seller, $attrs, $brands, $cats);
            $total += $count;
            $this->command->info("  ✓ {$count} auctions seeded via " . $seeder[1]);
        }

        $this->command->info("Price model seeding complete: {$total} completed auctions with attributes.");
    }

    // ── SMARTPHONES ──────────────────────────────────────────────
    private function seedSmartphones(User $seller, $attrs, $brands, $cats): int
    {
        $cat = $cats['Smartphones'] ?? null;
        if (! $cat) return 0;

        $phones = [
            // [brand, storage, ram, processor, base_price, variance_pct]
            ['Apple',   '64GB',  '4GB',  'Apple A13 Bionic',   280,  0.10],
            ['Apple',   '128GB', '4GB',  'Apple A13 Bionic',   340,  0.10],
            ['Apple',   '256GB', '4GB',  'Apple A13 Bionic',   420,  0.10],
            ['Apple',   '128GB', '6GB',  'Apple A15 Bionic',   580,  0.12],
            ['Apple',   '256GB', '6GB',  'Apple A15 Bionic',   720,  0.12],
            ['Apple',   '512GB', '6GB',  'Apple A15 Bionic',   850,  0.12],
            ['Apple',   '128GB', '8GB',  'Apple A17 Pro',      880,  0.14],
            ['Apple',   '256GB', '8GB',  'Apple A17 Pro',     1050,  0.14],
            ['Apple',   '512GB', '8GB',  'Apple A17 Pro',     1200,  0.14],
            ['Apple',   '1TB',   '8GB',  'Apple A17 Pro',     1380,  0.14],
            ['Samsung', '128GB', '6GB',  'Snapdragon 8 Gen 1', 420,  0.12],
            ['Samsung', '256GB', '8GB',  'Snapdragon 8 Gen 1', 560,  0.12],
            ['Samsung', '128GB', '8GB',  'Snapdragon 8 Gen 2', 680,  0.13],
            ['Samsung', '256GB', '12GB', 'Snapdragon 8 Gen 2', 820,  0.13],
            ['Samsung', '512GB', '12GB', 'Snapdragon 8 Gen 3', 950,  0.14],
            ['Samsung', '256GB', '12GB', 'Snapdragon 8 Gen 3',1050,  0.14],
            ['Google',  '128GB', '8GB',  'Google Tensor G2',   380,  0.11],
            ['Google',  '256GB', '12GB', 'Google Tensor G3',   620,  0.11],
            ['Google',  '512GB', '12GB', 'Google Tensor G3',   750,  0.11],
        ];

        $colors      = ['Black', 'White', 'Silver', 'Gold', 'Blue'];
        $screenSizes = ['6.1', '6.4', '6.7', '5.4'];
        $count       = 0;

        foreach ($phones as $phone) {
            [$brandName, $storage, $ram, $processor, $basePrice, $variance] = $phone;
            $brand = $brands[$brandName] ?? null;

            for ($i = 0; $i < 4; $i++) {
                $finalPrice = $this->randomPrice($basePrice, $variance);
                $auction    = $this->createAuction(
                    $seller, "{$brandName} Smartphone {$storage} {$ram}",
                    $cat->id, $brand?->id, $finalPrice
                );
                $this->setAttr($auction, $attrs, self::SLUG_STORAGE,   $storage);
                $this->setAttr($auction, $attrs, self::SLUG_RAM,        $ram);
                $this->setAttr($auction, $attrs, self::SLUG_PROCESSOR,  $processor);
                $this->setAttr($auction, $attrs, self::SLUG_SCREEN,     $screenSizes[array_rand($screenSizes)]);
                $this->setAttr($auction, $attrs, self::SLUG_COLOR,      $colors[array_rand($colors)]);
                $count++;
            }
        }

        return $count;
    }

    // ── LAPTOPS ──────────────────────────────────────────────────
    private function seedLaptops(User $seller, $attrs, $brands, $cats): int
    {
        $cat = $cats['Laptops & Computers'] ?? null;
        if (! $cat) return 0;

        $laptops = [
            ['Apple',     '256GB',  '8GB',  'Apple M1',      750,  0.10],
            ['Apple',     '512GB',  '8GB',  'Apple M1',      920,  0.10],
            ['Apple',     '512GB',  '16GB', 'Apple M2',     1150,  0.11],
            ['Apple',     '1TB',    '16GB', 'Apple M2 Pro', 1650,  0.12],
            ['Apple',     '1TB',    '32GB', 'Apple M3 Pro', 2200,  0.12],
            ['Apple',     '2TB',    '64GB', 'Apple M3 Max', 3200,  0.13],
            ['Dell',      '256GB',  '8GB',  'Intel i5',      480,  0.12],
            ['Dell',      '512GB',  '16GB', 'Intel i7',      820,  0.12],
            ['Dell',      '1TB',    '16GB', 'Intel i7',      980,  0.12],
            ['Dell',      '512GB',  '16GB', 'AMD Ryzen 7',   750,  0.11],
            ['HP',        '256GB',  '8GB',  'Intel i5',      420,  0.12],
            ['HP',        '512GB',  '16GB', 'Intel i7',      750,  0.12],
            ['HP',        '1TB',    '32GB', 'Intel i9',     1350,  0.13],
            ['Lenovo',    '256GB',  '8GB',  'AMD Ryzen 5',   380,  0.11],
            ['Lenovo',    '512GB',  '16GB', 'AMD Ryzen 7',   680,  0.11],
            ['Lenovo',    '1TB',    '32GB', 'AMD Ryzen 9',  1100,  0.12],
            ['Microsoft', '256GB',  '8GB',  'Intel i5',      680,  0.11],
            ['Microsoft', '512GB',  '16GB', 'Intel i7',     1050,  0.11],
            ['Microsoft', '1TB',    '32GB', 'Intel i7',     1450,  0.12],
            ['ASUS',      '512GB',  '16GB', 'Intel i7',      620,  0.12],
            ['ASUS',      '1TB',    '32GB', 'Intel i9',     1200,  0.13],
        ];

        $screens = ['13.3', '14', '15.6', '16'];
        $colors  = ['Silver', 'Black', 'White'];
        $count   = 0;

        foreach ($laptops as $config) {
            [$brandName, $storage, $ram, $processor, $basePrice, $variance] = $config;
            $brand = $brands[$brandName] ?? null;

            for ($i = 0; $i < 4; $i++) {
                $finalPrice = $this->randomPrice($basePrice, $variance);
                $auction    = $this->createAuction(
                    $seller, "{$brandName} Laptop {$ram} {$storage}",
                    $cat->id, $brand?->id, $finalPrice
                );
                $this->setAttr($auction, $attrs, self::SLUG_STORAGE,   $storage);
                $this->setAttr($auction, $attrs, self::SLUG_RAM,        $ram);
                $this->setAttr($auction, $attrs, self::SLUG_PROCESSOR,  $processor);
                $this->setAttr($auction, $attrs, self::SLUG_SCREEN,     $screens[array_rand($screens)]);
                $this->setAttr($auction, $attrs, self::SLUG_COLOR,      $colors[array_rand($colors)]);
                $count++;
            }
        }

        return $count;
    }

    // ── TABLETS ──────────────────────────────────────────────────
    private function seedTablets(User $seller, $attrs, $brands, $cats): int
    {
        $cat = $cats['Tablets'] ?? null;
        if (! $cat) return 0;

        $tablets = [
            ['Apple',   '64GB',  '4GB',  'Apple A14',          280, 0.10],
            ['Apple',   '128GB', '8GB',  'Apple M1',           450, 0.11],
            ['Apple',   '256GB', '8GB',  'Apple M2',           620, 0.11],
            ['Apple',   '512GB', '16GB', 'Apple M2',           850, 0.12],
            ['Apple',   '1TB',   '16GB', 'Apple M4',          1050, 0.12],
            ['Samsung', '64GB',  '4GB',  'Snapdragon 870',     220, 0.11],
            ['Samsung', '128GB', '6GB',  'Snapdragon 870',     320, 0.11],
            ['Samsung', '256GB', '8GB',  'Snapdragon 8 Gen 1', 450, 0.12],
            ['Samsung', '512GB', '12GB', 'Snapdragon 8 Gen 2', 580, 0.12],
        ];

        $screens = ['10.9', '11', '12.9'];
        $colors  = ['Silver', 'Space Gray', 'Gold', 'Black'];
        $count   = 0;

        foreach ($tablets as $config) {
            [$brandName, $storage, $ram, $processor, $basePrice, $variance] = $config;
            $brand = $brands[$brandName] ?? null;

            for ($i = 0; $i < 3; $i++) {
                $finalPrice = $this->randomPrice($basePrice, $variance);
                $auction    = $this->createAuction(
                    $seller, "{$brandName} Tablet {$storage}",
                    $cat->id, $brand?->id, $finalPrice
                );
                $this->setAttr($auction, $attrs, self::SLUG_STORAGE,   $storage);
                $this->setAttr($auction, $attrs, self::SLUG_RAM,        $ram);
                $this->setAttr($auction, $attrs, self::SLUG_PROCESSOR,  $processor);
                $this->setAttr($auction, $attrs, self::SLUG_SCREEN,     $screens[array_rand($screens)]);
                $this->setAttr($auction, $attrs, self::SLUG_COLOR,      $colors[array_rand($colors)]);
                $count++;
            }
        }

        return $count;
    }

    // ── WATCHES ──────────────────────────────────────────────────
    private function seedWatches(User $seller, $attrs, $brands, $cats): int
    {
        $cat = $cats['Watches'] ?? null;
        if (! $cat) return 0;

        $watches = [
            // [brand, case_material, movement, base_price, variance_pct]
            ['Rolex',   'Stainless Steel', 'Automatic',  8500, 0.15],
            ['Rolex',   'Gold',            'Automatic', 22000, 0.18],
            ['Rolex',   'Stainless Steel', 'Automatic', 12500, 0.15],
            ['Omega',   'Stainless Steel', 'Automatic',  3200, 0.14],
            ['Omega',   'Titanium',        'Automatic',  4800, 0.14],
            ['Omega',   'Gold',            'Automatic',  9500, 0.16],
            ['Cartier', 'Stainless Steel', 'Quartz',     2800, 0.13],
            ['Cartier', 'Gold',            'Automatic',  8200, 0.16],
            ['Apple',   'Stainless Steel', 'Quartz',      380, 0.10],
            ['Apple',   'Ceramic',         'Quartz',      780, 0.10],
            ['Samsung', 'Stainless Steel', 'Quartz',      180, 0.10],
            ['Samsung', 'Titanium',        'Quartz',      280, 0.10],
        ];

        $count = 0;
        foreach ($watches as $config) {
            [$brandName, $caseMat, $movement, $basePrice, $variance] = $config;
            $brand = $brands[$brandName] ?? null;

            for ($i = 0; $i < 5; $i++) {
                $finalPrice = $this->randomPrice($basePrice, $variance);
                $auction    = $this->createAuction(
                    $seller, "{$brandName} Watch {$caseMat}",
                    $cat->id, $brand?->id, $finalPrice
                );
                $this->setAttr($auction, $attrs, self::SLUG_CASE_MAT, $caseMat);
                $this->setAttr($auction, $attrs, self::SLUG_MOVEMENT,  $movement);
                $count++;
            }
        }

        return $count;
    }

    // ── SHOES ────────────────────────────────────────────────────
    private function seedShoes(User $seller, $attrs, $brands, $cats): int
    {
        $cat = $cats['Shoes'] ?? null;
        if (! $cat) return 0;

        $shoes = [
            // [brand, material, base_price, variance_pct]
            ['Nike',         'Mesh',    95,  0.15],
            ['Nike',         'Leather', 120, 0.15],
            ['Nike',         'Canvas',  75,  0.12],
            ['Adidas',       'Mesh',    85,  0.15],
            ['Adidas',       'Leather', 110, 0.15],
            ['Puma',         'Mesh',    70,  0.14],
            ['Gucci',        'Leather', 580, 0.20],
            ['Gucci',        'Canvas',  420, 0.20],
            ['Louis Vuitton','Leather', 780, 0.22],
        ];

        $sizes  = ['7', '8', '9', '10', '11'];
        $colors = ['Black', 'White', 'Red', 'Blue', 'Green'];
        $count  = 0;

        foreach ($shoes as $config) {
            [$brandName, $material, $basePrice, $variance] = $config;
            $brand = $brands[$brandName] ?? null;

            for ($i = 0; $i < 4; $i++) {
                $finalPrice = $this->randomPrice($basePrice, $variance);
                $auction    = $this->createAuction(
                    $seller, "{$brandName} Shoes {$material}",
                    $cat->id, $brand?->id, $finalPrice
                );
                $this->setAttr($auction, $attrs, self::SLUG_SHOE_SIZE, $sizes[array_rand($sizes)]);
                $this->setAttr($auction, $attrs, self::SLUG_MATERIAL,  $material);
                $this->setAttr($auction, $attrs, self::SLUG_COLOR,     $colors[array_rand($colors)]);
                $count++;
            }
        }

        return $count;
    }

    // ── CLOTHING ─────────────────────────────────────────────────
    private function seedClothing(User $seller, $attrs, $brands, $cats): int
    {
        $catMen   = $cats["Men's Clothing"] ?? null;
        $catWomen = $cats["Women's Clothing"] ?? null;

        $items = [
            // [brand, material, base_price, variance, cat_key]
            ['Nike',         'Cotton',    45,  0.15, 'men'],
            ['Nike',         'Polyester', 50,  0.15, 'men'],
            ['Adidas',       'Cotton',    40,  0.15, 'men'],
            ['Gucci',        'Cotton',   280,  0.20, 'men'],
            ['Louis Vuitton','Silk',      650, 0.22, 'men'],
            ['Gucci',        'Silk',      380, 0.20, 'women'],
            ['Louis Vuitton','Cotton',    320, 0.22, 'women'],
            ['Nike',         'Polyester', 45,  0.15, 'women'],
            ['Adidas',       'Cotton',    38,  0.15, 'women'],
        ];

        $sizes  = ['XS', 'S', 'M', 'L', 'XL'];
        $colors = ['Black', 'White', 'Blue', 'Red', 'Green'];
        $count  = 0;

        foreach ($items as $config) {
            [$brandName, $material, $basePrice, $variance, $catKey] = $config;
            $cat   = ($catKey === 'men') ? $catMen : $catWomen;
            $brand = $brands[$brandName] ?? null;
            if (! $cat) continue;

            for ($i = 0; $i < 3; $i++) {
                $finalPrice = $this->randomPrice($basePrice, $variance);
                $auction    = $this->createAuction(
                    $seller, "{$brandName} Clothing {$material}",
                    $cat->id, $brand?->id, $finalPrice
                );
                $this->setAttr($auction, $attrs, self::SLUG_SIZE,     $sizes[array_rand($sizes)]);
                $this->setAttr($auction, $attrs, self::SLUG_MATERIAL, $material);
                $this->setAttr($auction, $attrs, self::SLUG_COLOR,    $colors[array_rand($colors)]);
                $count++;
            }
        }

        return $count;
    }

    // ── CARS ─────────────────────────────────────────────────────
    private function seedCars(User $seller, $attrs, $brands, $cats): int
    {
        $cat = $cats['Cars'] ?? null;
        if (! $cat) return 0;

        $cars = [
            // [brand, year, mileage, fuel_type, transmission, base_price, variance_pct]
            ['Toyota', 2015, 85000, 'Gasoline', 'Automatic', 12000, 0.15],
            ['Toyota', 2018, 55000, 'Gasoline', 'Automatic', 18500, 0.14],
            ['Toyota', 2020, 30000, 'Hybrid',   'CVT',       24000, 0.13],
            ['Toyota', 2022, 10000, 'Hybrid',   'CVT',       30000, 0.12],
            ['Honda',  2016, 70000, 'Gasoline', 'Automatic', 13500, 0.15],
            ['Honda',  2019, 40000, 'Gasoline', 'Automatic', 20000, 0.13],
            ['Honda',  2021, 20000, 'Gasoline', 'Automatic', 26000, 0.12],
            ['BMW',    2015, 90000, 'Gasoline', 'Automatic', 22000, 0.18],
            ['BMW',    2018, 50000, 'Gasoline', 'Automatic', 38000, 0.16],
            ['BMW',    2020, 25000, 'Gasoline', 'Automatic', 52000, 0.15],
            ['BMW',    2022, 8000,  'Gasoline', 'Automatic', 68000, 0.14],
            ['Toyota', 2016, 75000, 'Diesel',   'Manual',    14000, 0.15],
            ['Honda',  2017, 60000, 'Gasoline', 'Manual',    15000, 0.14],
            ['BMW',    2019, 35000, 'Diesel',   'Automatic', 42000, 0.15],
        ];

        $colors = ['Black', 'White', 'Silver', 'Blue', 'Red'];
        $count  = 0;

        foreach ($cars as $config) {
            [$brandName, $year, $mileage, $fuel, $trans, $basePrice, $variance] = $config;
            $brand = $brands[$brandName] ?? null;

            // Price depreciates with mileage: -0.5% per 5000 miles over 30000
            $mileageAdjust = max(0, ($mileage - 30000) / 5000) * 0.005;
            $adjustedBase  = (int) ($basePrice * (1 - $mileageAdjust));

            for ($i = 0; $i < 3; $i++) {
                $finalPrice = $this->randomPrice($adjustedBase, $variance);
                $auction    = $this->createAuction(
                    $seller, "{$brandName} {$year} {$fuel}",
                    $cat->id, $brand?->id, $finalPrice
                );
                $this->setAttr($auction, $attrs, self::SLUG_YEAR,    (string) $year);
                $this->setAttr($auction, $attrs, self::SLUG_MILEAGE, (string) $mileage);
                $this->setAttr($auction, $attrs, self::SLUG_FUEL,    $fuel);
                $this->setAttr($auction, $attrs, self::SLUG_TRANS,   $trans);
                $this->setAttr($auction, $attrs, self::SLUG_COLOR,   $colors[array_rand($colors)]);
                $count++;
            }
        }

        return $count;
    }

    // ── CAMERAS ──────────────────────────────────────────────────
    private function seedCameras(User $seller, $attrs, $brands, $cats): int
    {
        $cat = $cats['Cameras & Photography'] ?? null;
        if (! $cat) return 0;

        $cameras = [
            ['Canon', 'Black',  580,  0.12],
            ['Canon', 'Silver', 620,  0.12],
            ['Nikon', 'Black',  520,  0.12],
            ['Nikon', 'Silver', 560,  0.12],
            ['Sony',  'Black',  750,  0.13],
            ['Sony',  'Silver', 780,  0.13],
            ['Canon', 'Black',  1200, 0.13],
            ['Nikon', 'Black',  1100, 0.13],
            ['Sony',  'Black',  1800, 0.14],
        ];

        $count = 0;
        foreach ($cameras as $config) {
            [$brandName, $color, $basePrice, $variance] = $config;
            $brand = $brands[$brandName] ?? null;

            for ($i = 0; $i < 3; $i++) {
                $finalPrice = $this->randomPrice($basePrice, $variance);
                $auction    = $this->createAuction(
                    $seller, "{$brandName} Camera",
                    $cat->id, $brand?->id, $finalPrice
                );
                $this->setAttr($auction, $attrs, self::SLUG_COLOR, $color);
                $count++;
            }
        }

        return $count;
    }

    // ── GAMING ───────────────────────────────────────────────────
    private function seedGaming(User $seller, $attrs, $brands, $cats): int
    {
        $cat = $cats['Gaming'] ?? null;
        if (! $cat) return 0;

        $consoles = [
            ['Sony',      '825GB', '16GB', 'AMD Zen 2', 350, 0.12],
            ['Sony',      '1TB',   '16GB', 'AMD Zen 2', 420, 0.12],
            ['Microsoft', '512GB', '10GB', 'AMD Zen 2', 250, 0.12],
            ['Microsoft', '1TB',   '16GB', 'AMD Zen 3', 420, 0.12],
            ['Nintendo',  '32GB',  '4GB',  'Nvidia',    180, 0.10],
            ['Nintendo',  '64GB',  '4GB',  'Nvidia',    250, 0.10],
        ];

        $colors = ['Black', 'White'];
        $count  = 0;

        foreach ($consoles as $config) {
            [$brandName, $storage, $ram, $processor, $basePrice, $variance] = $config;
            $brand = $brands[$brandName] ?? null;

            for ($i = 0; $i < 4; $i++) {
                $finalPrice = $this->randomPrice($basePrice, $variance);
                $auction    = $this->createAuction(
                    $seller, "{$brandName} Gaming Console {$storage}",
                    $cat->id, $brand?->id, $finalPrice
                );
                $this->setAttr($auction, $attrs, self::SLUG_STORAGE,   $storage);
                $this->setAttr($auction, $attrs, self::SLUG_RAM,        $ram);
                $this->setAttr($auction, $attrs, self::SLUG_PROCESSOR,  $processor);
                $this->setAttr($auction, $attrs, self::SLUG_COLOR,      $colors[array_rand($colors)]);
                $count++;
            }
        }

        return $count;
    }

    // ── Shared helpers ───────────────────────────────────────────

    private function createAuction(
        User   $seller,
        string $title,
        int    $categoryId,
        ?int   $brandId,
        float  $finalPrice
    ): Auction {
        $startedAt  = now()->subDays(rand(7, 365));
        $endedAt    = $startedAt->copy()->addDays(rand(3, 10));
        $conditions = ['new', 'like_new', 'used_good', 'used_fair', 'refurbished'];

        $auction = Auction::create([
            'user_id'             => $seller->id,
            'title'               => $title,
            'description'         => "Auction listing for {$title}.",
            'starting_price'      => round($finalPrice * 0.50, 2),
            'current_price'       => $finalPrice,
            'winning_bid_amount'  => $finalPrice,
            'reserve_price'       => round($finalPrice * 0.80, 2),
            'reserve_met'         => true,
            'min_bid_increment'   => 1.00,
            'currency'            => 'USD',
            'status'              => Auction::STATUS_COMPLETED,
            'condition'           => $conditions[array_rand($conditions)],
            'brand_id'            => $brandId,
            'bid_count'           => rand(5, 40),
            'unique_bidder_count' => rand(3, 20),
            'start_time'          => $startedAt,
            'end_time'            => $endedAt,
            'closed_at'           => $endedAt,
            'payment_status'      => 'paid',
            'winner_id'           => $seller->id,
        ]);

        $auction->categories()->sync([$categoryId => ['is_primary' => true]]);

        return $auction;
    }

    private function setAttr(Auction $auction, $attrs, string $slug, string $value): void
    {
        $attr = $attrs[$slug] ?? null;
        if (! $attr) return;

        AuctionAttributeValue::updateOrCreate(
            ['auction_id' => $auction->id, 'attribute_id' => $attr->id],
            ['value' => $value]
        );
    }

    private function randomPrice(float $base, float $variancePct): float
    {
        $factor = 1 + (mt_rand(-100, 100) / 100) * $variancePct;
        return round($base * $factor, 2);
    }
}
