<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagsSeeder extends Seeder
{
    public function run(): void
    {
        $tagGroups = [
            // Production Status (Red/Orange/Yellow)
            'status' => [
                ['name' => 'Rush Order', 'color' => '#dc2626', 'price_per_piece' => 0],
                ['name' => 'Awaiting Artwork', 'color' => '#f97316', 'price_per_piece' => 0],
                ['name' => 'Proof Sent', 'color' => '#eab308', 'price_per_piece' => 0],
                ['name' => 'Ready to Press', 'color' => '#16a34a', 'price_per_piece' => 0],
                ['name' => 'Printed', 'color' => '#3b82f6', 'price_per_piece' => 0],
                ['name' => 'Quality Control Failed', 'color' => '#be123c', 'price_per_piece' => 0],
                ['name' => 'Reprint Needed', 'color' => '#9333ea', 'price_per_piece' => 0],
                ['name' => 'On Hold', 'color' => '#334155', 'price_per_piece' => 0],
            ],
        ];

        // Create the defined realistic tags first
        foreach ($tagGroups as $group) {
            foreach ($group as $tag) {
                Tag::create($tag);
            }
        }
    }
}
