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
                ['name' => 'Rush Order', 'color' => 'bg-red-600'],
                ['name' => 'Awaiting Artwork', 'color' => 'bg-orange-500'],
                ['name' => 'Proof Sent', 'color' => 'bg-yellow-500'],
                ['name' => 'Ready to Press', 'color' => 'bg-green-600'],
                ['name' => 'Printed', 'color' => 'bg-blue-500'],
                ['name' => 'QC Failed', 'color' => 'bg-rose-700'],
                ['name' => 'Reprint Needed', 'color' => 'bg-purple-600'],
                ['name' => 'On Hold', 'color' => 'bg-slate-700'],
            ],
        ];

        // Create the defined realistic tags first
        foreach ($tagGroups as $group) {
            foreach ($group as $tag) {
                Tag::create($tag);
            }
        }

        // Fill the rest with 30 more generic/faker tags to reach 50
        $colors = ['bg-slate-500', 'bg-zinc-500', 'bg-neutral-500', 'bg-orange-400', 'bg-amber-500'];

        for ($i = 0; $i < 30; $i++) {
            Tag::create([
                'name' => 'Client-' . fake()->unique()->company(),
                'color' => $colors[array_rand($colors)]
            ]);
        }
    }
}
