<?php

namespace Database\Seeders;

use App\Models\Agreement;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\BusinessRole;
use App\Models\Employee;
use App\Models\PaymentMethod;
use App\Models\Spot;
use App\Models\SpotEmployee;
use App\Models\Tip;
use App\Models\TipPageSetting;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vcard;
use App\Models\VcardTheme;
use App\Models\Withdrawal;
use App\Models\WorkingHour;
use Illuminate\Database\Seeder;

/**
 * Demo accounts for local development and testing. Every password is "password".
 *
 * - admin@tiqs.test     platform admin
 * - business@tiqs.test  business owner with roles, spots, staff and tips
 * - waiter@tiqs.test    employee with tips, reviews, a payout and a vCard
 * - legacy@tiqs.test    imported user still on an old md5 password
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'TIQS Admin',
            'email' => 'admin@tiqs.test',
            'slug' => 'admin',
        ]);

        $owner = User::factory()->business()->create([
            'name' => 'Demo Business Owner',
            'email' => 'business@tiqs.test',
            'slug' => 'demobusiness',
        ]);

        $business = Business::factory()->for($owner, 'owner')->create([
            'name' => 'Café Demo',
            'code' => 'BIZ00000001',
        ]);

        $manager = BusinessRole::factory()->for($business)->create(['name' => 'Manager', 'level' => 1]);
        $waiterRole = BusinessRole::factory()->for($business)->create(['name' => 'Waiter', 'parent_id' => $manager->id]);

        $agreement = Agreement::factory()->for($business)->create([
            'created_by' => $owner->id,
            'title' => 'Standard tip sharing',
            'role_shares' => [(string) $manager->id => 20, (string) $waiterRole->id => 80],
            'is_default' => true,
        ]);
        $business->update(['default_agreement_id' => $agreement->id]);

        $waiter = User::factory()->create([
            'name' => 'Demo Waiter',
            'email' => 'waiter@tiqs.test',
            'slug' => 'demowaiter',
            'bio' => 'Happy to serve you!',
        ]);

        $staff = collect([$waiter])->merge(User::factory(3)->create());

        foreach ($staff as $member) {
            Employee::factory()->for($business)->for($member)->create([
                'business_role_id' => $waiterRole->id,
                'agreement_id' => $agreement->id,
                'is_live' => true,
                'last_live_at' => now(),
            ]);
        }

        Employee::factory()->pending()->for($business)->create([
            'requested_by' => 'employee',
            'business_role_id' => $waiterRole->id,
        ]);

        $spots = collect(['Terrace', 'Bar', 'Table 1'])->map(
            fn (string $name): Spot => Spot::factory()->for($business)->create([
                'name' => $name,
                'slug' => str($name)->slug()->toString(),
            ]),
        );

        foreach ($spots as $spot) {
            SpotEmployee::factory()->for($spot)->for($waiter)->create([
                'business_role_id' => $waiterRole->id,
                'agreement_id' => $agreement->id,
                'is_live' => true,
            ]);

            Tip::factory(3)->paid()->for($spot)->create([
                'recipient_id' => $waiter->id,
                'business_id' => $business->id,
            ]);
        }

        // The waiter's balance: paid tips not yet requested for payout.
        Tip::factory(5)->paid()->for($waiter, 'recipient')->create(['business_id' => $business->id]);
        Tip::factory(4)->rating()->for($waiter, 'recipient')->create(['business_id' => $business->id]);
        Tip::factory(2)->for($waiter, 'recipient')->create();

        $withdrawal = Withdrawal::factory()->paid()->for($waiter)->create(['amount' => 25]);
        Tip::factory(5)->paid()->for($waiter, 'recipient')->create(['amount' => 5, 'withdrawal_id' => $withdrawal->id]);

        BankAccount::factory()->for($waiter)->create(['account_name' => $waiter->name]);
        PaymentMethod::factory()->for($waiter)->create();
        TipPageSetting::factory()->for($waiter)->create();
        Vcard::factory()->for($waiter)->create(['business_name' => $business->name]);
        VcardTheme::factory()->for($waiter)->create();

        foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri'] as $day) {
            WorkingHour::factory()->for($waiter)->create(['day' => $day]);
        }

        UserNotification::factory(3)->for($waiter)->create();
        UserNotification::factory()->for($owner)->create([
            'business_id' => $business->id,
            'audience' => 'business',
            'type' => 'join_request',
            'title' => 'Join request',
            'message' => 'A new employee asked to join your business.',
        ]);

        User::factory()->withLegacyPassword()->create([
            'name' => 'Legacy User',
            'email' => 'legacy@tiqs.test',
            'slug' => 'legacyuser',
        ]);
    }
}
