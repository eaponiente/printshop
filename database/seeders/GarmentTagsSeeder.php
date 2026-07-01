<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class GarmentTagsSeeder extends Seeder
{
    public function run(): void
    {
        $garments = [
            ['name' => 'Shirt', 'color' => '#3b82f6', 'price_per_piece' => 15],
            ['name' => 'Shorts', 'color' => '#f97316', 'price_per_piece' => 12],
            ['name' => 'Sando', 'color' => '#eab308', 'price_per_piece' => 10],
            ['name' => 'Jersey', 'color' => '#16a34a', 'price_per_piece' => 18],
            ['name' => 'Polo', 'color' => '#6366f1', 'price_per_piece' => 20],
            ['name' => 'Hoodie', 'color' => '#9333ea', 'price_per_piece' => 35],
            ['name' => 'Jacket', 'color' => '#0891b2', 'price_per_piece' => 40],
            ['name' => 'Cap', 'color' => '#be123c', 'price_per_piece' => 8],
            ['name' => 'Tote Bag', 'color' => '#78716c', 'price_per_piece' => 10],
            ['name' => 'Lanyard', 'color' => '#475569', 'price_per_piece' => 5],
        ];

        foreach ($garments as $garment) {
            Tag::firstOrCreate(['name' => $garment['name']], $garment);
        }
    }
}
