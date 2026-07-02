<?php

namespace Database\Seeders;

use App\Enums\Sublimations\SublimationStatus;
use App\Models\Customer;
use App\Models\Sublimation;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Sales\CashOnHandService;
use App\Services\Sales\SalesService;
use Carbon\Carbon;
use Faker\Factory;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    private array $paymentTypes = ['cash', 'card', 'gcash', 'debit', 'bank_transfer', 'check'];

    public function run(): void
    {
        $faker = Factory::create();
        $customerIds = Customer::pluck('id');
        $staffMembers = User::whereIn('role', ['staff', 'admin'])->whereNotNull('branch_id')->get();

        if ($customerIds->isEmpty() || $staffMembers->isEmpty()) {
            $this->command?->warn('Run CustomerSeeder and UsersSeeder before TransactionSeeder.');

            return;
        }

        // Printing-shop services: name, sample description, and a price range.
        $standardServices = [
            ['name' => 'Tarpaulin Printing', 'desc' => '3x5 ft full-color tarpaulin', 'min' => 150, 'max' => 1200],
            ['name' => 'Business Cards', 'desc' => '2-sided matte, 100 pcs', 'min' => 250, 'max' => 900],
            ['name' => 'Sticker Printing', 'desc' => 'Die-cut vinyl stickers', 'min' => 100, 'max' => 1500],
            ['name' => 'Photo Printing', 'desc' => 'Glossy 4R prints', 'min' => 50, 'max' => 600],
            ['name' => 'Document Printing', 'desc' => 'Colored A4, bulk run', 'min' => 50, 'max' => 800],
            ['name' => 'Banner / Streamer', 'desc' => 'Event backdrop banner', 'min' => 800, 'max' => 6000],
            ['name' => 'Invitation Cards', 'desc' => 'Wedding invites, 50 pcs', 'min' => 500, 'max' => 4000],
            ['name' => 'Mug Printing', 'desc' => 'Personalized ceramic mug', 'min' => 120, 'max' => 500],
            ['name' => 'ID Lace / Lanyard', 'desc' => 'Printed lanyards, bulk', 'min' => 200, 'max' => 2500],
            ['name' => 'T-Shirt Printing', 'desc' => 'DTF print, per piece', 'min' => 150, 'max' => 3000],
        ];

        $sublimationProducts = [
            'Full Sublimation Jersey' => ['Basketball set', 'Volleyball uniform', 'Esports jersey'],
            'Corporate Lanyard' => ['1-inch nylon', 'Digital print'],
            'Custom Hoodie' => ['Pullover with back print', 'Zip-up with logo'],
        ];

        foreach (range(1, 100) as $i) {
            $staff = $staffMembers->random();
            auth()->login($staff); // recordPayment() stamps auth()->id() on the payment

            // Spread across the last ~90 days for daily/weekly/monthly/yearly variety.
            $date = Carbon::now()
                ->subDays(rand(0, 90))
                ->setTime(rand(8, 18), rand(0, 59), 0);

            if ($faker->boolean(15)) {
                $this->seedSublimationTransaction($faker, $customerIds, $staff, $sublimationProducts, $date);
            } else {
                $this->seedStandardTransaction($faker, $customerIds, $staff, $standardServices, $date);
            }
        }

        auth()->logout();
    }

    private function seedStandardTransaction($faker, $customerIds, $staff, array $services, Carbon $date): void
    {
        $service = $faker->randomElement($services);

        $transaction = Transaction::create([
            'invoice_number' => Transaction::generateNumber(),
            // customer_id is nullable now — ~15% are walk-in guests with no customer on file.
            'customer_id' => $faker->boolean(15) ? null : $customerIds->random(),
            'particular' => $service['name'],
            'description' => $faker->boolean(70) ? $service['desc'] : null,
            'amount_total' => rand($service['min'], $service['max']),
            'amount_paid' => 0,
            'status' => 'pending',
            'staff_id' => $staff->id,
            'branch_id' => $staff->branch_id,
            'transaction_date' => $date,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $this->applyPayments($transaction, $faker, $date);
    }

    private function seedSublimationTransaction($faker, $customerIds, $staff, array $products, Carbon $date): void
    {
        $particular = $faker->randomElement(array_keys($products));
        $subDesc = $faker->randomElement($products[$particular]);
        $transactionType = $faker->randomElement(['retail', 'purchase_order']);

        // Only statuses that have entered production carry a transaction/collection.
        $status = $faker->randomElement([
            SublimationStatus::DOWNPAYMENT_COMPLETE,
            SublimationStatus::FOR_SIZING,
            SublimationStatus::PRINTED,
            SublimationStatus::SEWING,
            SublimationStatus::CLAIMED,
            SublimationStatus::COMPLETED,
        ]);

        $sublimation = Sublimation::query()->create([
            'branch_id' => $staff->branch_id,
            'customer_id' => $customerIds->random(),
            'user_id' => $staff->id,
            'status' => $status,
            'transaction_type' => $transactionType,
            'production_authorized' => $transactionType === 'retail' ? $faker->boolean(20) : false,
            'amount_total' => rand(1000, 8000),
            'description' => "Order: {$subDesc}",
            'quantity' => rand(10, 60),
            'due_at' => $date->clone()->addDays(rand(5, 21)),
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $transaction = app(SalesService::class)->createTransaction([
            'invoice_number' => Transaction::generateNumber(),
            'customer_id' => $sublimation->customer_id,
            'particular' => 'Sublimation',
            'description' => $sublimation->description,
            'amount_total' => $sublimation->amount_total,
            'amount_paid' => 0,
            'status' => 'pending',
            'staff_id' => $sublimation->user_id,
            'branch_id' => $sublimation->branch_id,
            'transaction_date' => $date,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $sublimation->update(['transaction_id' => $transaction->id]);

        $this->applyPayments($transaction, $faker, $date);
    }

    /**
     * Give the transaction a realistic settlement outcome:
     * ~15% pending, ~50% fully paid, ~35% partial — sometimes across two
     * installments with different payment types/dates. Cash collections feed
     * the branch cash-on-hand, matching the live payment flow.
     *
     * Amounts are whole pesos and every payment is capped to the live balance,
     * so recordPayment()'s overpayment guard can never trip on the exact
     * DECIMAL arithmetic MySQL uses.
     */
    private function applyPayments(Transaction $transaction, $faker, Carbon $date): void
    {
        $roll = rand(1, 100);
        $total = (int) round((float) $transaction->amount_total);

        if ($roll <= 15 || $total <= 0) {
            return; // pending — no collection yet
        }

        $fullyPaid = $roll <= 65;
        $target = $fullyPaid ? $total : max(1, (int) round($total * rand(20, 80) / 100));

        $installments = $faker->boolean(35) ? 2 : 1;
        $collected = 0;
        $payDate = $date->clone();

        for ($n = 1; $n <= $installments; $n++) {
            $portion = $n === $installments ? $target - $collected : intdiv($target, 2);

            // Never pay more than what is actually outstanding.
            $balance = (int) round((float) $transaction->fresh()->balance);
            $portion = min($portion, $balance);

            if ($portion <= 0) {
                break;
            }

            $collected += $portion;
            $type = $faker->randomElement($this->paymentTypes);

            $transaction->recordPayment($portion, $type);
            $transaction->payments()->latest('id')->first()->update([
                'created_at' => $payDate,
                'updated_at' => $payDate,
            ]);

            if ($type === 'cash') {
                app(CashOnHandService::class)->adjustBalance($transaction->branch_id, $portion, 'revenue');
            }

            $payDate = $payDate->clone()->addDays(rand(1, 10));
            if ($payDate->greaterThan(now())) {
                $payDate = now();
            }
        }
    }
}
