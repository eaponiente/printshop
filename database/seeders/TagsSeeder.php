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
                ['name' => 'Quality Control Failed', 'color' => 'bg-rose-700'],
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
    }
}
