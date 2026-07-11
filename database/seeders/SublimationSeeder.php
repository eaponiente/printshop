<?php

namespace Database\Seeders;

use App\Enums\Sublimations\SublimationStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payroll\SewedItem;
use App\Models\Sublimation;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SublimationSeeder extends Seeder
{
    private array $sublimationDescriptions = [
        'Full Sublimation Basketball Jersey Set',
        'Volleyball Uniform – Custom Design',
        'Esports Team Jersey',
        'Corporate Lanyard – 1-inch Nylon',
        'Digital Print Lanyard',
        'Custom Hoodie – Pullover with Back Print',
        'Zip-up Hoodie with Logo',
        'Running Singlet – Marathon Event',
        'Cycling Jersey – Custom Fit',
        'Motorcycle Club Jacket',
        'School Uniform – PE Set',
        'Company Polo Shirt – Embroidered',
        'Event T-Shirt – Bulk Order',
        'Dry-Fit Shirt – Sports Event',
        'Long Sleeve Jersey – Winter League',
        'Training Shorts – Sublimated',
        'Warm-up Jacket – Team Logo',
        'Track Pants – Sublimated Stripe',
        'Sports Bra – Custom Design',
        'Leggings – All-over Print',
        'Cap – Sublimated Panel',
        'Bucket Hat – Custom Artwork',
        'Face Mask – Reusable Fabric',
        'Tote Bag – Full Color Print',
        'Drawstring Bag – Event Giveaway',
        'Sports Towel – Team Branding',
        'Arm Sleeve – Compression Fit',
        'Knee Pad Cover – Custom Print',
        'Goalkeeper Jersey – Padded',
        'Referee Uniform – Striped',
        'Cheerleading Uniform – Sparkle',
        'Dance Costume – Competition Set',
        'Gymnastics Leotard – Sublimated',
        'Swim Cap – Silicone Print',
        'Rash Guard – UV Protection',
        'Board Shorts – Beach Event',
        'Fishing Vest – Utility Print',
        'Hiking Shirt – Quick Dry',
        'Camping Chair Cover – Custom',
        'Tablecloth – Event Branding',
        'Banner Flag – Team Colors',
        'Pennant – Championship Design',
        'Sash – Pageant/Event',
        'Graduation Stole – Custom Year',
        'Lab Gown – Printed Trim',
        'Scrubs Set – Medical Team',
        'Chef Coat – Restaurant Logo',
        'Apron – Full Sublimation',
        'Work Vest – Safety Print',
        'Backpack Panel – Replacement',
        'Luggage Cover – Travel Brand',
        'Pet Jersey – Dog/Cat Size',
        'Baby Onesie – Custom Print',
        'Pillowcase – Photo Print',
        'Blanket – Fleece Sublimation',
        'Curtain Panel – Decorative',
        'Umbrella Canopy – Logo Print',
        'Car Seat Cover – Custom Fit',
        'Steering Wheel Cover – Print',
        'Neck Gaiter – Multi-use',
        'Team Bandana – Custom Print',
        'Wristband Set – Sweat Absorbent',
        'Socks – All-over Graphic',
        'Shoe Bag – Travel Print',
        'Yoga Mat Cover – Custom Art',
        'Gym Bag – Team Logo',
        'Cooler Sleeve – Bottle Wrap',
        'Laptop Sleeve – Full Print',
        'Mouse Pad – Extended Desk',
        'Coaster Set – Round Ceramic',
        'Keychain – Acrylic Print',
        'Badge Reel – Retractable',
        'Phone Case – Tough Shell',
        'Tablet Stand Cover – Printed',
        'Wallet – Bifold Custom',
        'Belt – Sublimated Strap',
        'Suspenders – Printed Elastic',
        'Bow Tie – Pre-tied Print',
        'Tie – Full Length Print',
        'Scarf – Winter Knit Print',
        'Beanie – Cuffed Custom',
        'Bandana – Triangle Pet',
        'Koozie – Can Cooler Print',
        'Placemat – Dining Set',
        'Napkin Ring – Fabric Wrap',
        'Wine Bag – Bottle Sleeve',
        'Gift Wrap – Roll Print',
        'Ribbon – Satin Custom',
        'Patch – Iron-on Embroidered',
        'Pin – Enamel Custom',
        'Magnet – Photo Keepsake',
        'Sticker Sheet – Vinyl Die-cut',
        'Decal – Window Cling',
        'Wrap – Vehicle Panel',
        'Sign – Yard Stake Print',
        'Poster – Matte Finish',
        'Canvas Print – Gallery Wrap',
        'Photo Book – Hardcover',
        'Calendar – Wall Spiral',
        'Planner – Coil Bound',
        'Notebook – Spiral Custom',
        'Folder – Pocket Print',
        'Envelope – Mailing Custom',
        'Card – Greeting Set',
        'Invitation – Foil Accent',
        'Menu – Restaurant Laminated',
        'Flyer – Promo Handout',
        'Brochure – Tri-fold Gloss',
        'Bookmark – Tassel Custom',
        'Puzzle – Photo Jigsaw',
        'Playing Cards – Custom Deck',
        'Game Board – Roll-up Mat',
        'Dice Bag – Drawstring Print',
        'Tarot Cloth – Altar Spread',
        'Yoga Strap – Printed Cotton',
        'Resistance Band – Logo Loop',
        'Jump Rope – Handle Print',
        'Frisbee – Disc Golf Custom',
        'Ball – Stress Relief Print',
        'Pennant – Wall Hanging',
        'Flag – Garden Banner',
        'Banner – Mesh Outdoor',
        'Backdrop – Step & Repeat',
        'Red Carpet Runner – Event',
        'Stage Skirt – Pleated Print',
        'Podium Cover – Branded Wrap',
        'Chair Sash – Banquet Tie',
        'Table Runner – Center Print',
    ];

    private function createSublimation(
        User $staff,
        Customer $customer,
        SublimationStatus $status,
        Carbon $date,
        array $tags,
        string $description,
    ): Sublimation {
        $transactionType = rand(1, 100) <= 20 ? 'purchase_order' : 'retail';
        $amountTotal = rand(1000, 15000);
        $quantity = rand(5, 80);

        $sublimation = Sublimation::create([
            'branch_id' => $staff->branch_id,
            'customer_id' => $customer->id,
            'user_id' => $staff->id,
            'status' => $status,
            'transaction_type' => $transactionType,
            'production_authorized' => $transactionType === 'retail' ? rand(1, 100) <= 25 : false,
            'amount_total' => $amountTotal,
            'description' => $description,
            'quantity' => $quantity,
            'due_at' => $date->clone()->addDays(rand(5, 30)),
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        // Attach 1-3 tags with random quantities
        $tagCount = rand(1, 3);
        $selectedTags = collect($tags)->random(min($tagCount, count($tags)));
        $tagData = [];
        foreach ($selectedTags as $tag) {
            $tagData[$tag->id] = ['quantity' => rand(1, max(1, $quantity))];
        }
        $sublimation->tags()->attach($tagData);

        return $sublimation;
    }

    private function createSewedItem(Sublimation $sublimation, User $staff, Carbon $date, array $tags): void
    {
        $unitPrice = round($sublimation->amount_total / max(1, $sublimation->quantity), 2);
        $sewedQuantity = rand(1, $sublimation->quantity);
        $sewedAmount = round($unitPrice * $sewedQuantity, 2);

        $sewedItem = SewedItem::create([
            'sublimation_id' => $sublimation->id,
            'quantity' => $sewedQuantity,
            'unit_price' => $unitPrice,
            'amount' => $sewedAmount,
            'branch_id' => $sublimation->branch_id,
            'user_id' => $staff->id,
            'notes' => rand(1, 100) <= 30 ? 'Completed ahead of schedule.' : null,
            'sewed_date' => $date->clone()->addDays(rand(3, 20))->format('Y-m-d'),
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        // Attach 1-2 tags to the sewed item with quantity and price_per_piece
        $sewedTagCount = rand(1, 2);
        $selectedSewedTags = collect($tags)->random(min($sewedTagCount, count($tags)));
        $sewedTagData = [];
        foreach ($selectedSewedTags as $tag) {
            $sewedTagData[$tag->id] = [
                'quantity' => rand(1, $sewedQuantity),
                'price_per_piece' => rand(10, 100),
            ];
        }
        $sewedItem->tags()->attach($sewedTagData);
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = Branch::all();
        $customers = Customer::all();
        $staffMembers = User::whereIn('role', ['staff', 'admin'])
            ->whereNotNull('branch_id')
            ->get();
        $tags = Tag::all();

        if ($branches->isEmpty() || $customers->isEmpty() || $staffMembers->isEmpty()) {
            $this->command?->warn('Run BranchSeeder, CustomerSeeder and UsersSeeder before SublimationSeeder.');

            return;
        }

        if ($tags->isEmpty()) {
            $this->command?->warn('Run TagsSeeder before SublimationSeeder.');

            return;
        }

        $statuses = SublimationStatus::cases();
        $productionStatuses = array_filter($statuses, fn (SublimationStatus $s) => $s->isProductionPhase());
        $prePaymentStatuses = array_filter($statuses, fn (SublimationStatus $s) => $s->isPrePaymentPhase());

        $tagArray = $tags->all();
        $staffArray = $staffMembers->all();
        $customerArray = $customers->all();

        // ── Batch 1: 25 SEWED sublimations, each with a sewed item ──
        $sewedCount = 0;
        foreach (range(1, 25) as $i) {
            $staff = $staffArray[array_rand($staffArray)];
            $customer = $customerArray[array_rand($customerArray)];
            $date = Carbon::now()
                ->subDays(rand(0, 90))
                ->setTime(rand(8, 18), rand(0, 59), 0);

            $sublimation = $this->createSublimation(
                $staff,
                $customer,
                SublimationStatus::SEWED,
                $date,
                $tagArray,
                $this->sublimationDescriptions[($i - 1) % count($this->sublimationDescriptions)],
            );

            $this->createSewedItem($sublimation, $staff, $date, $tagArray);
            $sewedCount++;
        }

        // ── Batch 2: 20 more sublimations with sewed items (CHECKED, READY_FOR_PICKUP, CLAIMED, COMPLETED)
        $postSewedStatuses = [
            SublimationStatus::CHECKED,
            SublimationStatus::READY_FOR_PICKUP,
            SublimationStatus::CLAIMED,
            SublimationStatus::COMPLETED,
        ];
        foreach (range(26, 45) as $i) {
            $staff = $staffArray[array_rand($staffArray)];
            $customer = $customerArray[array_rand($customerArray)];
            $date = Carbon::now()
                ->subDays(rand(0, 90))
                ->setTime(rand(8, 18), rand(0, 59), 0);

            $status = $postSewedStatuses[array_rand($postSewedStatuses)];

            $sublimation = $this->createSublimation(
                $staff,
                $customer,
                $status,
                $date,
                $tagArray,
                $this->sublimationDescriptions[($i - 1) % count($this->sublimationDescriptions)],
            );

            $this->createSewedItem($sublimation, $staff, $date, $tagArray);
        }

        // ── Batch 3: 55 random-status sublimations to reach 100+ total ──
        foreach (range(46, 100) as $i) {
            $staff = $staffArray[array_rand($staffArray)];
            $customer = $customerArray[array_rand($customerArray)];
            $date = Carbon::now()
                ->subDays(rand(0, 90))
                ->setTime(rand(8, 18), rand(0, 59), 0);

            // ~40% pre-payment phase, ~60% production/delivery phase
            $statusPool = rand(1, 100) <= 40 ? $prePaymentStatuses : $statuses;
            $status = collect($statusPool)->random();

            $sublimation = $this->createSublimation(
                $staff,
                $customer,
                $status,
                $date,
                $tagArray,
                $this->sublimationDescriptions[($i - 1) % count($this->sublimationDescriptions)],
            );

            // Create sewed item only for SEWED and later statuses
            if (in_array($status, [
                SublimationStatus::SEWED,
                SublimationStatus::CHECKED,
                SublimationStatus::READY_FOR_PICKUP,
                SublimationStatus::CLAIMED,
                SublimationStatus::COMPLETED,
            ], true)) {
                $this->createSewedItem($sublimation, $staff, $date, $tagArray);
            }
        }

        $this->command?->info('Seeded 100 sublimations (25 SEWED + 20 post-sewed + 55 random).');
    }
}
