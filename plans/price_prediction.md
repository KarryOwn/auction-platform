# Price Prediction Feature — Agent Implementation Guide

> **Stack:** Laravel 12 · PostgreSQL · Redis · PHP 8.2+  
> **Depends on:** `CategorySeeder`, `BrandSeeder`, `AttributeSeeder` (already in codebase)

---

## Table of Contents

1. [Overview](#1-overview)
2. [Architecture & Data Flow](#2-architecture--data-flow)
3. [Algorithm Reference](#3-algorithm-reference)
4. [File Map — What to Create / Replace](#4-file-map--what-to-create--replace)
5. [Step 1 — Create the Mock Data Seeder](#5-step-1--create-the-mock-data-seeder)
6. [Step 2 — Create the Prediction Service](#6-step-2--create-the-prediction-service)
7. [Step 3 — Replace the InsightController](#7-step-3--replace-the-insightcontroller)
8. [Step 4 — Register the Service Binding](#8-step-4--register-the-service-binding)
9. [Step 5 — Add Routes](#9-step-5--add-routes)
10. [Step 6 — Add Database Indexes](#10-step-6--add-database-indexes)
11. [Step 7 — Run the Seeder](#11-step-7--run-the-seeder)
12. [API Reference](#12-api-reference)
13. [Attribute Slug Reference](#13-attribute-slug-reference)
14. [Condition Adjustment Table](#14-condition-adjustment-table)
15. [Confidence Label Rules](#15-confidence-label-rules)
16. [Known Limitations & Future Improvements](#16-known-limitations--future-improvements)

---

## 1. Overview

This feature adds **attribute-aware price prediction** to the seller dashboard. When a seller fills in a category, brand, condition, and product attributes (storage, RAM, mileage, case material, etc.) the system returns:

- A **predicted selling price** with a low/high confidence range
- A **suggested starting price** (65% of predicted)
- A **suggested reserve price** (87% of predicted)
- **Attribute insights** — which attributes are driving price up or down and by how much
- A **brand premium** percentage derived from historical auction data
- A **condition discount** applied on top of the raw prediction

The approach is a **attribute-weighted k-Nearest Neighbours (k-NN)** algorithm running entirely in PHP/SQL against the auction history already stored in the database. No external ML service is required.

---

## 2. Architecture & Data Flow

```
Seller fills prediction form
        │
        ▼
POST /seller/insights/predict
        │
        ▼
InsightController::predict()
        │
        ▼
AttributePricePredictionService::predict()
        │
        ├─► Cache::remember() — 5-minute TTL per unique input hash
        │
        ├─► getCategoryTree($categoryId)
        │   └─ Returns [$categoryId, ...descendant_ids]
        │
        ├─► fetchComparables($categoryIds)
        │   └─ SQL: completed auctions + json_object_agg(attr_slug, value)
        │          WHERE closed_at >= now() - 180 days
        │          LIMIT 500
        │
        ├─► scoreComparables()
        │   └─ For each comparable: computeSimilarity(inputAttrs, auctionAttrs)
        │      • number  attrs → 1 − |normalised_diff|
        │      • select  attrs → exact match = 1.0
        │      • boolean attrs → match = 1.0, mismatch = 0.5
        │      • text    attrs → exact match = 1.0
        │      Weighted by TYPE_WEIGHT[type]
        │
        ├─► Take top-20 by similarity score (k = 20)
        │
        ├─► weightedMedian()  → predicted_price (raw)
        │
        ├─► calculateBrandPremium() → % above/below category avg
        │   └─ Cache::remember() 1 hour per brand+category combo
        │
        ├─► conditionAdjustment() → % discount by condition string
        │
        ├─► standardDeviation() → confidence interval ±1.2σ
        │
        └─► attributeInsights()
            └─ Per attribute: avg price WITH vs WITHOUT this value
               → price_impact_pct, direction
```

---

## 3. Algorithm Reference

### Similarity Scoring

Each comparable auction is scored 0.0–1.0 against the input attributes:

```
similarity = Σ(typeWeight[i] × matchScore[i])
             ──────────────────────────────────
             Σ(typeWeight[i])    for all input attrs
```

**Type weights:**

| Attribute Type | Weight |
|---|---|
| `number` | 1.0 |
| `select` | 0.9 |
| `boolean` | 0.7 |
| `text` | 0.4 |

**Match score per type:**

| Type | Formula |
|---|---|
| `number` | `max(0, 1 − │a − b│ / max(│a│, │b│))` |
| `select` | `a == b ? 1.0 : 0.0` (case-insensitive) |
| `boolean` | `a == b ? 1.0 : 0.5` |
| `text` | `a == b ? 1.0 : 0.0` (case-insensitive) |

### Weighted Median

Top-20 neighbours sorted descending by similarity. Cumulative similarity weight is accumulated until it reaches 50% of the total — the price at that point is the weighted median.

### Brand Premium

```
brandPremium% = ((avgPriceForBrand − avgPriceForCategory) / avgPriceForCategory) × 100
```

Negative values mean the brand sells below category average (budget brands).

### Condition Adjustment

Applied as a multiplicative factor after brand premium:

```
finalPrice = rawPrice × (1 + brandPremium%) × (1 + conditionAdj%)
```

---

## 4. File Map — What to Create / Replace

| Action | Path |
|---|---|
| **CREATE** | `database/seeders/PriceModelSeeder.php` |
| **CREATE** | `app/Services/AttributePricePredictionService.php` |
| **REPLACE** | `app/Http/Controllers/Seller/InsightController.php` |
| **EDIT** | `app/Providers/AppServiceProvider.php` — add singleton binding |
| **EDIT** | `routes/web.php` — add 2 new routes inside seller group |
| **EDIT** | `database/seeders/DatabaseSeeder.php` — add `PriceModelSeeder` call |

---

## 5. Step 1 — Create the Mock Data Seeder

**File:** `database/seeders/PriceModelSeeder.php`

This seeder creates ~600 completed auctions with realistic prices and attribute values across 9 product categories. It must run **after** `CategorySeeder`, `BrandSeeder`, and `AttributeSeeder`.

```php
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
```

---

## 6. Step 2 — Create the Prediction Service

**File:** `app/Services/AttributePricePredictionService.php`

```php
<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Auction;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttributePricePredictionService
{
    private const MIN_SAMPLE_SIZE = 5;
    private const LOOKBACK_DAYS   = 180;
    private const MAX_COMPARABLES = 500;
    private const K_NEIGHBOURS    = 20;

    private const TYPE_WEIGHT = [
        'number'  => 1.0,
        'select'  => 0.9,
        'boolean' => 0.7,
        'text'    => 0.4,
    ];

    public function predict(
        int     $categoryId,
        array   $inputAttrs = [],
        ?int    $brandId    = null,
        ?string $condition  = null,
    ): array {
        $cacheKey = 'price_predict:' . md5(serialize([$categoryId, $inputAttrs, $brandId, $condition]));

        return Cache::remember($cacheKey, 300, function () use ($categoryId, $inputAttrs, $brandId, $condition) {
            return $this->computePrediction($categoryId, $inputAttrs, $brandId, $condition);
        });
    }

    private function computePrediction(int $categoryId, array $inputAttrs, ?int $brandId, ?string $condition): array
    {
        $attrDefinitions = Attribute::with('categories')->get()->keyBy('slug');
        $normalizedInput = $this->normalizeInput($inputAttrs, $attrDefinitions);
        $categoryIds     = $this->getCategoryTree($categoryId);
        $comparables     = $this->fetchComparables($categoryIds);

        $fallback = false;
        if ($comparables->count() < self::MIN_SAMPLE_SIZE) {
            $comparables = $this->fetchComparables(null);
            $fallback    = true;
        }

        if ($comparables->isEmpty()) {
            return $this->emptyPrediction();
        }

        $scored         = $this->scoreComparables($comparables, $normalizedInput, $attrDefinitions);
        $topK           = $scored->sortByDesc('similarity')->take(self::K_NEIGHBOURS);
        $predictedPrice = $this->weightedMedian($topK);

        $brandPremiumPct = 0.0;
        if ($brandId) {
            $brandPremiumPct = $this->calculateBrandPremium($brandId, $categoryIds);
            $predictedPrice  = round($predictedPrice * (1 + $brandPremiumPct / 100), 2);
        }

        $conditionAdjPct = $this->conditionAdjustment($condition);
        $predictedPrice  = round($predictedPrice * (1 + $conditionAdjPct / 100), 2);

        $prices    = $topK->pluck('price')->values();
        $stdDev    = $this->standardDeviation($prices, $predictedPrice);
        $lowEst    = max(0.01, round($predictedPrice - $stdDev * 1.2, 2));
        $highEst   = round($predictedPrice + $stdDev * 1.2, 2);

        $confidence   = $this->confidenceLabel($topK->count(), $fallback, $normalizedInput);
        $attrInsights = $this->attributeInsights($normalizedInput, $attrDefinitions, $categoryIds);

        return [
            'predicted_price'            => $predictedPrice,
            'low_estimate'               => $lowEst,
            'high_estimate'              => $highEst,
            'confidence'                 => $confidence,
            'sample_size'                => $comparables->count(),
            'comparables_used'           => $topK->count(),
            'brand_premium_pct'          => round($brandPremiumPct, 2),
            'condition_adjustment_pct'   => round($conditionAdjPct, 2),
            'attribute_insights'         => $attrInsights,
            'suggested_starting_price'   => round($predictedPrice * 0.65, 2),
            'suggested_reserve_price'    => round($predictedPrice * 0.87, 2),
            'fallback'                   => $fallback,
        ];
    }

    private function scoreComparables(Collection $comparables, array $normalizedInput, Collection $attrDefinitions): Collection
    {
        return $comparables->map(function (object $row) use ($normalizedInput, $attrDefinitions) {
            $auctionAttrs = is_string($row->attr_json)
                ? (json_decode($row->attr_json, true) ?? [])
                : [];

            $similarity = $this->computeSimilarity($normalizedInput, $auctionAttrs, $attrDefinitions);

            return [
                'auction_id' => $row->auction_id,
                'price'      => (float) $row->winning_bid_amount,
                'similarity' => $similarity,
            ];
        })->filter(fn ($row) => $row['similarity'] > 0.0);
    }

    private function computeSimilarity(array $inputAttrs, array $auctionAttrs, Collection $attrDefinitions): float
    {
        if (empty($inputAttrs)) {
            return 0.5;
        }

        $totalWeight = 0.0;
        $matchScore  = 0.0;

        foreach ($inputAttrs as $slug => $inputValue) {
            $def         = $attrDefinitions[$slug] ?? null;
            if (! $def) continue;

            $typeWeight  = self::TYPE_WEIGHT[$def->type] ?? 0.5;
            $totalWeight += $typeWeight;

            if (! isset($auctionAttrs[$slug])) {
                continue;
            }

            $matchScore += $typeWeight * $this->attributeMatch($def->type, $inputValue, $auctionAttrs[$slug], $def);
        }

        return $totalWeight === 0.0 ? 0.5 : $matchScore / $totalWeight;
    }

    private function attributeMatch(string $type, string $a, string $b, $def): float
    {
        if ($type === 'number') {
            $fa  = (float) $a;
            $fb  = (float) $b;
            $max = max(abs($fa), abs($fb));
            if ($max === 0.0) return 1.0;
            return max(0.0, 1 - abs($fa - $fb) / $max);
        }

        if ($type === 'boolean') {
            return $a === $b ? 1.0 : 0.5;
        }

        return strtolower(trim($a)) === strtolower(trim($b)) ? 1.0 : 0.0;
    }

    private function attributeInsights(array $inputAttrs, Collection $attrDefs, array $categoryIds): array
    {
        if (empty($inputAttrs) || empty($categoryIds)) {
            return [];
        }

        $insights = [];

        foreach ($inputAttrs as $slug => $value) {
            $attr = $attrDefs[$slug] ?? null;
            if (! $attr) continue;

            $withValue    = $this->avgPriceForAttrValue($attr->id, $value, $categoryIds);
            $withoutValue = $this->avgPriceForAttrAbsent($attr->id, $value, $categoryIds);

            if ($withValue === null || $withoutValue === null) {
                continue;
            }

            $impactPct = $withoutValue > 0
                ? round((($withValue - $withoutValue) / $withoutValue) * 100, 1)
                : 0.0;

            $insights[] = [
                'attribute'          => $attr->name,
                'slug'               => $slug,
                'your_value'         => $value,
                'avg_price_with'     => round($withValue, 2),
                'avg_price_without'  => round($withoutValue, 2),
                'price_impact_pct'   => $impactPct,
                'direction'          => $impactPct >= 0 ? 'positive' : 'negative',
            ];
        }

        usort($insights, fn ($a, $b) => abs($b['price_impact_pct']) <=> abs($a['price_impact_pct']));

        return $insights;
    }

    private function avgPriceForAttrValue(int $attrId, string $value, array $catIds): ?float
    {
        $result = DB::table('auctions')
            ->join('auction_attribute_values as aav', 'auctions.id', '=', 'aav.auction_id')
            ->join('auction_category as ac', 'auctions.id', '=', 'ac.auction_id')
            ->where('aav.attribute_id', $attrId)
            ->whereRaw('LOWER(aav.value) = ?', [strtolower($value)])
            ->whereIn('ac.category_id', $catIds)
            ->where('auctions.status', Auction::STATUS_COMPLETED)
            ->whereNotNull('auctions.winning_bid_amount')
            ->whereDate('auctions.closed_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->avg('auctions.winning_bid_amount');

        return $result !== null ? (float) $result : null;
    }

    private function avgPriceForAttrAbsent(int $attrId, string $value, array $catIds): ?float
    {
        $result = DB::table('auctions')
            ->join('auction_category as ac', 'auctions.id', '=', 'ac.auction_id')
            ->whereIn('ac.category_id', $catIds)
            ->where('auctions.status', Auction::STATUS_COMPLETED)
            ->whereNotNull('auctions.winning_bid_amount')
            ->whereDate('auctions.closed_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->where(function ($q) use ($attrId, $value) {
                $q->whereNotExists(function ($sub) use ($attrId) {
                    $sub->from('auction_attribute_values as aav')
                        ->whereColumn('aav.auction_id', 'auctions.id')
                        ->where('aav.attribute_id', $attrId);
                })->orWhereExists(function ($sub) use ($attrId, $value) {
                    $sub->from('auction_attribute_values as aav')
                        ->whereColumn('aav.auction_id', 'auctions.id')
                        ->where('aav.attribute_id', $attrId)
                        ->whereRaw('LOWER(aav.value) != ?', [strtolower($value)]);
                });
            })
            ->avg('auctions.winning_bid_amount');

        return $result !== null ? (float) $result : null;
    }

    private function calculateBrandPremium(int $brandId, array $categoryIds): float
    {
        $cacheKey = "brand_premium:{$brandId}:" . implode(',', $categoryIds);

        return Cache::remember($cacheKey, 3600, function () use ($brandId, $categoryIds) {
            $brandAvg = DB::table('auctions')
                ->join('auction_category as ac', 'auctions.id', '=', 'ac.auction_id')
                ->where('auctions.brand_id', $brandId)
                ->whereIn('ac.category_id', $categoryIds)
                ->where('auctions.status', Auction::STATUS_COMPLETED)
                ->whereNotNull('auctions.winning_bid_amount')
                ->avg('auctions.winning_bid_amount');

            $catAvg = DB::table('auctions')
                ->join('auction_category as ac', 'auctions.id', '=', 'ac.auction_id')
                ->whereIn('ac.category_id', $categoryIds)
                ->where('auctions.status', Auction::STATUS_COMPLETED)
                ->whereNotNull('auctions.winning_bid_amount')
                ->avg('auctions.winning_bid_amount');

            if (! $brandAvg || ! $catAvg || $catAvg == 0) {
                return 0.0;
            }

            return (($brandAvg - $catAvg) / $catAvg) * 100;
        });
    }

    private function conditionAdjustment(?string $condition): float
    {
        return match ($condition) {
            'new'         =>   0.0,
            'like_new'    =>  -3.0,
            'refurbished' =>  -8.0,
            'used_good'   => -18.0,
            'used_fair'   => -30.0,
            'for_parts'   => -55.0,
            default       =>   0.0,
        };
    }

    private function fetchComparables(?array $categoryIds): Collection
    {
        $query = DB::table('auctions as a')
            ->select([
                'a.id as auction_id',
                'a.winning_bid_amount',
                DB::raw(
                    "json_object_agg(COALESCE(attr.slug,'_unknown'), aav.value) " .
                    "FILTER (WHERE aav.attribute_id IS NOT NULL) as attr_json"
                ),
            ])
            ->leftJoin('auction_attribute_values as aav', 'a.id', '=', 'aav.auction_id')
            ->leftJoin('attributes as attr', 'aav.attribute_id', '=', 'attr.id')
            ->where('a.status', Auction::STATUS_COMPLETED)
            ->whereNotNull('a.winning_bid_amount')
            ->whereDate('a.closed_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->groupBy('a.id', 'a.winning_bid_amount')
            ->limit(self::MAX_COMPARABLES);

        if ($categoryIds !== null) {
            $query->join('auction_category as ac', 'a.id', '=', 'ac.auction_id')
                  ->whereIn('ac.category_id', $categoryIds);
        }

        try {
            return $query->get();
        } catch (\Throwable $e) {
            Log::warning('AttributePricePrediction: query failed', ['error' => $e->getMessage()]);
            return collect();
        }
    }

    private function getCategoryTree(int $categoryId): array
    {
        $category = Category::find($categoryId);
        if (! $category) return [$categoryId];
        return array_merge([$categoryId], $category->descendant_ids);
    }

    private function weightedMedian(Collection $scored): float
    {
        $items      = $scored->sortByDesc('similarity')->values();
        $total      = $items->sum('similarity');
        if ($total == 0) return (float) $items->median('price');

        $cumulative = 0.0;
        $half       = $total / 2;

        foreach ($items as $item) {
            $cumulative += $item['similarity'];
            if ($cumulative >= $half) {
                return round((float) $item['price'], 2);
            }
        }

        return round((float) $items->last()['price'], 2);
    }

    private function standardDeviation(Collection $prices, float $mean): float
    {
        $count = $prices->count();
        if ($count < 2) return $mean * 0.10;

        $variance = $prices->reduce(fn ($carry, $p) => $carry + (($p - $mean) ** 2), 0.0) / ($count - 1);
        return sqrt($variance);
    }

    private function normalizeInput(array $input, Collection $attrDefs): array
    {
        $normalized = [];
        foreach ($input as $key => $value) {
            if ($value === null || $value === '') continue;
            if (is_numeric($key)) {
                $def = $attrDefs->firstWhere('id', (int) $key);
                if ($def) $normalized[$def->slug] = (string) $value;
            } else {
                $normalized[(string) $key] = (string) $value;
            }
        }
        return $normalized;
    }

    private function confidenceLabel(int $k, bool $fallback, array $input): string
    {
        if ($fallback)     return 'low';
        if ($k < 5)        return 'low';
        if (empty($input)) return 'low';
        if ($k < 10)       return 'medium';
        return 'high';
    }

    private function emptyPrediction(): array
    {
        return [
            'predicted_price'          => 0.0,
            'low_estimate'             => 0.0,
            'high_estimate'            => 0.0,
            'confidence'               => 'none',
            'sample_size'              => 0,
            'comparables_used'         => 0,
            'brand_premium_pct'        => 0.0,
            'condition_adjustment_pct' => 0.0,
            'attribute_insights'       => [],
            'suggested_starting_price' => 0.0,
            'suggested_reserve_price'  => 0.0,
            'fallback'                 => true,
        ];
    }
}
```

---

## 7. Step 3 — Replace the InsightController

**File:** `app/Http/Controllers/Seller/InsightController.php`  
This **fully replaces** the existing file. It keeps the two original methods (`suggestPrice`, `auctionInsights`) and adds two new ones (`predict`, `categoryAttributes`).

```php
<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Models\Category;
use App\Services\AttributePricePredictionService;
use App\Services\PriceSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightController extends Controller
{
    public function __construct(
        private readonly PriceSuggestionService          $suggestionService,
        private readonly AttributePricePredictionService $predictionService,
    ) {}

    // Existing — keyword-based suggestion (unchanged)
    public function suggestPrice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $data = $this->suggestionService->suggest(
            $validated['title'],
            $validated['description'] ?? null,
        );

        return response()->json(['data' => $data]);
    }

    // NEW — attribute-aware price prediction
    public function predict(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id'  => ['required', 'integer', 'exists:categories,id'],
            'brand_id'     => ['nullable', 'integer', 'exists:brands,id'],
            'condition'    => ['nullable', 'string', 'in:new,like_new,used_good,used_fair,refurbished,for_parts'],
            'attributes'   => ['nullable', 'array'],
            'attributes.*' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->predictionService->predict(
            categoryId: (int) $validated['category_id'],
            inputAttrs: $validated['attributes'] ?? [],
            brandId:    isset($validated['brand_id']) ? (int) $validated['brand_id'] : null,
            condition:  $validated['condition'] ?? null,
        );

        return response()->json(['data' => $result]);
    }

    // NEW — returns attributes for a category so the frontend can build the form
    public function categoryAttributes(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $category   = Category::findOrFail($request->integer('category_id'));
        $attributes = $category->getAllAttributes()->map(fn ($attr) => [
            'id'            => $attr->id,
            'name'          => $attr->name,
            'slug'          => $attr->slug,
            'type'          => $attr->type,
            'unit'          => $attr->unit,
            'options'       => $attr->options,
            'is_filterable' => $attr->is_filterable,
            'is_required'   => $attr->is_required,
            'sort_order'    => $attr->sort_order,
        ]);

        return response()->json(['data' => $attributes]);
    }

    // Existing — live auction health insights (unchanged)
    public function auctionInsights(Auction $auction)
    {
        abort_unless($auction->user_id === auth()->id(), 403);

        $insights = $this->suggestionService->auctionInsights($auction);

        return view('seller.insights.show', compact('auction', 'insights'));
    }
}
```

---

## 8. Step 4 — Register the Service Binding

**File:** `app/Providers/AppServiceProvider.php`

Inside the `register()` method, add:

```php
$this->app->singleton(
    \App\Services\AttributePricePredictionService::class
);
```

Full `register()` after edit:

```php
public function register(): void
{
    Stripe::setApiKey(config('services.stripe.secret'));

    $this->app->bind(BiddingStrategy::class, RedisAtomicEngine::class);

    $this->app->singleton(BidRateLimiter::class, function () {
        return new BidRateLimiter(
            maxBids: (int) config('auction.rate_limit.max_bids', 10),
            windowSeconds: (int) config('auction.rate_limit.window_seconds', 60),
        );
    });

    // ── NEW ───────────────────────────────────────────────────────
    $this->app->singleton(
        \App\Services\AttributePricePredictionService::class
    );
}
```

---

## 9. Step 5 — Add Routes

**File:** `routes/web.php`

Find the existing seller insight routes inside the `seller` middleware group:

```php
// OLD — remove these two lines:
Route::post('/insights/price-suggestion', [InsightController::class, 'suggestPrice'])->name('insights.price-suggestion');
Route::get('/auctions/{auction}/insights', [InsightController::class, 'auctionInsights'])->name('auctions.insights');
```

Replace with these four routes:

```php
Route::post('/insights/price-suggestion',
            [InsightController::class, 'suggestPrice'])
     ->name('insights.price-suggestion');

Route::post('/insights/predict',
            [InsightController::class, 'predict'])
     ->name('insights.predict');

Route::get('/insights/category-attributes',
           [InsightController::class, 'categoryAttributes'])
     ->name('insights.category-attributes');

Route::get('/auctions/{auction}/insights',
           [InsightController::class, 'auctionInsights'])
     ->name('auctions.insights');
```

---

## 10. Step 6 — Add Database Indexes

Run these once via `php artisan db:statement` or a migration. All are `CONCURRENTLY` safe on PostgreSQL.

```sql
-- Speed up completed-auction lookups (used by every prediction query)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_auctions_completed_closed
  ON auctions (status, closed_at)
  WHERE status = 'completed' AND deleted_at IS NULL;

-- Speed up attribute value filtering (attribute insights queries)
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_aav_attr_value
  ON auction_attribute_values (attribute_id, lower(value));

-- Speed up category tree joins
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ac_category_id
  ON auction_category (category_id);
```

Or add a migration:

```php
// database/migrations/2026_XX_XX_add_price_prediction_indexes.php
public function up(): void
{
    DB::statement("
        CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_auctions_completed_closed
        ON auctions (status, closed_at)
        WHERE status = 'completed' AND deleted_at IS NULL
    ");

    DB::statement("
        CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_aav_attr_value
        ON auction_attribute_values (attribute_id, lower(value))
    ");

    DB::statement("
        CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_ac_category_id
        ON auction_category (category_id)
    ");
}
```

---

## 11. Step 7 — Run the Seeder

**Option A — standalone run:**
```bash
php artisan db:seed --class=PriceModelSeeder
```

**Option B — add to `DatabaseSeeder.php`:**
```php
$this->call([
    CategorySeeder::class,
    BrandSeeder::class,
    TagSeeder::class,
    AttributeSeeder::class,
    PriceModelSeeder::class,   // ← add after AttributeSeeder
]);
```

Expected output:
```
✓ 76 auctions seeded via seedSmartphones
✓ 84 auctions seeded via seedLaptops
✓ 27 auctions seeded via seedTablets
✓ 60 auctions seeded via seedWatches
✓ 36 auctions seeded via seedShoes
✓ 27 auctions seeded via seedClothing
✓ 42 auctions seeded via seedCars
✓ 27 auctions seeded via seedCameras
✓ 24 auctions seeded via seedGaming
Price model seeding complete: ~600 completed auctions with attributes.
```

---

## 12. API Reference

### `GET /seller/insights/category-attributes`

Returns attribute definitions for a category so the frontend can render the prediction form dynamically.

**Query params:** `category_id` (integer, required)

**Response:**
```json
{
  "data": [
    {
      "id": 3,
      "name": "Storage",
      "slug": "storage",
      "type": "select",
      "unit": null,
      "options": ["16GB","32GB","64GB","128GB","256GB","512GB","1TB","2TB"],
      "is_filterable": true,
      "is_required": false,
      "sort_order": 3
    }
  ]
}
```

---

### `POST /seller/insights/predict`

**Request body:**
```json
{
  "category_id": 5,
  "brand_id": 1,
  "condition": "like_new",
  "attributes": {
    "storage":   "256GB",
    "ram":       "16GB",
    "processor": "Apple M2"
  }
}
```

> `attributes` keys can be **attribute slugs** (strings) or **attribute IDs** (integers). Both are accepted.

**Validation rules:**

| Field | Rules |
|---|---|
| `category_id` | required, integer, exists:categories |
| `brand_id` | nullable, integer, exists:brands |
| `condition` | nullable, one of the condition enum values |
| `attributes` | nullable, array |
| `attributes.*` | nullable, string, max:255 |

**Response:**
```json
{
  "data": {
    "predicted_price": 920.00,
    "low_estimate": 780.00,
    "high_estimate": 1060.00,
    "confidence": "high",
    "sample_size": 87,
    "comparables_used": 15,
    "brand_premium_pct": 12.5,
    "condition_adjustment_pct": -3.0,
    "attribute_insights": [
      {
        "attribute": "Storage",
        "slug": "storage",
        "your_value": "256GB",
        "avg_price_with": 920.00,
        "avg_price_without": 720.00,
        "price_impact_pct": 27.8,
        "direction": "positive"
      },
      {
        "attribute": "RAM",
        "slug": "ram",
        "your_value": "16GB",
        "avg_price_with": 880.00,
        "avg_price_without": 760.00,
        "price_impact_pct": 15.8,
        "direction": "positive"
      }
    ],
    "suggested_starting_price": 598.00,
    "suggested_reserve_price": 800.40,
    "fallback": false
  }
}
```

**Response field glossary:**

| Field | Description |
|---|---|
| `predicted_price` | Final price after brand premium + condition adjustment |
| `low_estimate` | `predicted_price − 1.2σ` |
| `high_estimate` | `predicted_price + 1.2σ` |
| `confidence` | `"none"` / `"low"` / `"medium"` / `"high"` |
| `sample_size` | Total completed auctions found in category |
| `comparables_used` | Number of top-k neighbours actually used |
| `brand_premium_pct` | % this brand sells above/below category average |
| `condition_adjustment_pct` | % discount applied for condition |
| `attribute_insights` | Sorted by absolute price impact, descending |
| `suggested_starting_price` | `predicted_price × 0.65` |
| `suggested_reserve_price` | `predicted_price × 0.87` |
| `fallback` | `true` if category had < 5 comparables and global data was used |

---

## 13. Attribute Slug Reference

These are the slugs used by `PriceModelSeeder` and expected by the prediction service. They are generated by `AttributeSeeder` via `Str::slug($name)`.

| Category | Attribute Name | Slug | Type |
|---|---|---|---|
| Electronics, Phones, Laptops, Gaming | Storage | `storage` | select |
| Electronics, Phones, Laptops, Gaming | RAM | `ram` | select |
| Phones, Laptops, Tablets | Screen Size | `screen-size` | number |
| Phones, Laptops, Tablets, Gaming | Processor | `processor` | text |
| All | Color | `color` | select |
| Clothing | Size | `size` | select |
| Clothing, Shoes | Material | `material` | text |
| Shoes | Shoe Size | `shoe-size` | select |
| Watches | Case Material | `case-material` | select |
| Watches | Movement Type | `movement-type` | select |
| Vehicles | Mileage | `mileage` | number |
| Vehicles | Year | `year` | number |
| Vehicles | Fuel Type | `fuel-type` | select |
| Vehicles | Transmission | `transmission` | select |
| All | Weight | `weight` | number |

---

## 14. Condition Adjustment Table

| Condition value | Adjustment |
|---|---|
| `new` | 0% |
| `like_new` | −3% |
| `refurbished` | −8% |
| `used_good` | −18% |
| `used_fair` | −30% |
| `for_parts` | −55% |
| `null` / omitted | 0% |

---

## 15. Confidence Label Rules

Applied after k-NN scoring:

| Condition | Label |
|---|---|
| `fallback = true` (category had < 5 auctions) | `"low"` |
| `comparables_used < 5` | `"low"` |
| No attributes provided | `"low"` |
| `comparables_used` between 5–9 | `"medium"` |
| `comparables_used >= 10` with attributes | `"high"` |

---

## 16. Known Limitations & Future Improvements

### Current limitations

- **`json_object_agg` (PostgreSQL only).** The `fetchComparables` query uses PostgreSQL's `json_object_agg`. If the project ever switches to MySQL 8+, swap to `JSON_OBJECTAGG`. A try-catch guard exists but logs a warning.
- **`winner_id` in seeder.** `PriceModelSeeder` sets `winner_id` to the seller's own ID as a placeholder since the seeder doesn't create real bid chains. This is fine for training data but would fail `FOREIGN KEY` constraints if the users table is wiped independently.
- **Cache invalidation.** Predictions are cached for 5 minutes, brand premiums for 1 hour. If you reseed or bulk-import auctions, flush the cache: `php artisan cache:clear`.
- **Numeric attribute encoding.** Values like `"256GB"` are compared as strings for `select` types. For true numeric comparison of storage values, add a normalisation step that strips units before scoring.

### Suggested future improvements

**a) Time-decay weighting**
Auctions from the last 30 days should count more than 6-month-old data. Multiply each comparable's similarity by a decay factor:
```php
$ageDays  = $row->closed_at->diffInDays(now());
$decayFactor = exp(-0.01 * $ageDays);   // half-life ≈ 69 days
$similarity *= $decayFactor;
```

**b) Demand signal adjustment**
If a category has unusually high bid velocity (bids in last 7 days vs. 30-day average), nudge the prediction up by 5–15%:
```php
$recentBids = Bid::whereIn('auction_id', $categoryAuctionIds)
    ->where('created_at', '>=', now()->subDays(7))->count();
$normalBids = ... / 4;  // 7-day slice of 30-day avg
$demandMultiplier = min(1.15, 1 + (($recentBids - $normalBids) / max(1, $normalBids)) * 0.1);
```

**c) Nightly pre-computed cache**
Add a scheduled command that pre-computes and caches predictions for the most common category+attribute combos (based on search/view logs), eliminating the cold-query latency for sellers.

**d) External ML microservice**
Once you have 10,000+ completed auctions with attributes, train a gradient-boosting model (XGBoost / LightGBM) in Python with one-hot encoded attributes and numeric features. Serve it via FastAPI. The Laravel service becomes a thin HTTP client:
```php
$response = Http::post('http://ml-service/predict', [
    'category_id' => $categoryId,
    'attributes'  => $normalizedInput,
    'brand_id'    => $brandId,
]);
return $response->json('predicted_price');
```

**e) Automatic unit stripping for numeric attributes**
Convert `"256GB"` → `256`, `"85000 miles"` → `85000` before scoring so that numeric similarity is computed on the actual number rather than failing to string-match:
```php
private function toNumber(string $value): float
{
    return (float) preg_replace('/[^0-9.]/', '', $value);
}
```