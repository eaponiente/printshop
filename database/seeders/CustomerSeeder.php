<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $senators = [
            ['first' => 'Juan Miguel', 'last' => 'Zubiri', 'office' => 'Office of Senator Migz Zubiri'],
            ['first' => 'Loren', 'last' => 'Legarda', 'office' => 'Office of Senator Loren Legarda'],
            ['first' => 'Joel', 'last' => 'Villanueva', 'office' => 'Office of Senator Joel Villanueva'],
            ['first' => 'Francis', 'last' => 'Escudero', 'office' => 'Office of Senator Chiz Escudero'],
            ['first' => 'Raffy', 'last' => 'Tulfo', 'office' => 'Office of Senator Raffy Tulfo'],
            ['first' => 'Win', 'last' => 'Gatchalian', 'office' => 'Office of Senator Win Gatchalian'],
            ['first' => 'Grace', 'last' => 'Poe', 'office' => 'Office of Senator Grace Poe'],
            ['first' => 'Cynthia', 'last' => 'Villar', 'office' => 'Office of Senator Cynthia Villar'],
            ['first' => 'Mark', 'last' => 'Villar', 'office' => 'Office of Senator Mark Villar'],
            ['first' => 'Risa', 'last' => 'Hontiveros', 'office' => 'Office of Senator Risa Hontiveros'],
            ['first' => 'Nancy', 'last' => 'Binay', 'office' => 'Office of Senator Nancy Binay'],
            ['first' => 'Pia', 'last' => 'Cayetano', 'office' => 'Office of Senator Pia Cayetano'],
            ['first' => 'Alan Peter', 'last' => 'Cayetano', 'office' => 'Office of Senator Alan Peter Cayetano'],
            ['first' => 'Ronald', 'last' => 'Dela Rosa', 'office' => 'Office of Senator Bato Dela Rosa'],
            ['first' => 'Christopher', 'last' => 'Go', 'office' => 'Office of Senator Bong Go'],
            ['first' => 'Robinhood', 'last' => 'Padilla', 'note' => 'Office of Senator Robin Padilla'],
            ['first' => 'Imee', 'last' => 'Marcos', 'office' => 'Office of Senator Imee Marcos'],
            ['first' => 'Jinggoy', 'last' => 'Estrada', 'office' => 'Office of Senator Jinggoy Estrada'],
            ['first' => 'JV', 'last' => 'Ejercito', 'office' => 'Office of Senator JV Ejercito'],
            ['first' => 'Lito', 'last' => 'Lapid', 'office' => 'Office of Senator Lito Lapid'],
            ['first' => 'Ramon', 'last' => 'Revilla Jr.', 'office' => 'Office of Senator Bong Revilla'],
            ['first' => 'Francis', 'last' => 'Tolentino', 'office' => 'Office of Senator Tolentino'],
            // New 2025/2026 Batch Names
            ['first' => 'Erwin', 'last' => 'Tulfo', 'office' => 'Office of Senator Erwin Tulfo'],
            ['first' => 'Benhur', 'last' => 'Abalos', 'office' => 'Office of Senator Benhur Abalos'],
        ];

        foreach ($senators as $senator) {
            Customer::create([
                'first_name' => $senator['first'],
                'last_name' => $senator['last'],
                'company' => $senator['office'] ?? 'Senate of the Philippines',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
