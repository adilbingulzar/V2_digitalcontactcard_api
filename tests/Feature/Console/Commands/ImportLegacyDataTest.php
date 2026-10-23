<?php

namespace Tests\Feature\Console\Commands;

use App\Enums\EmployeeStatus;
use App\Enums\PaymentStatus;
use App\Enums\TipType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\BankAccount;
use App\Models\Employee;
use App\Models\FeeCollection;
use App\Models\Tip;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vcard;
use Illuminate\Database\Connection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportLegacyDataTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_imports_user_with_legacy_password_role_and_profile_slug(): void
    {
        $legacy = $this->emptyLegacyDatabase();
        $legacy->table('tbl_cl_users')->insert([
            'id' => 7, 'fullname' => 'Anna Admin', 'email' => ' Anna@Example.com ', 'password' => md5('secret1'),
            'roll' => 2, 'type' => 1, 'status' => 1, 'bdate' => '15-06-1990', 'created' => 1700000000,
        ]);
        $legacy->table('tbl_cl_profile')->insert(['user_id' => 7, 'user_uri' => 'anna', 'user_status' => 'Hi!']);

        $this->artisan('tiqs:import-legacy')->assertSuccessful();

        $user = User::findOrFail(7);
        $this->assertSame('anna@example.com', $user->email);
        $this->assertSame('anna', $user->slug);
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame(md5('secret1'), $user->legacy_password_md5);
        $this->assertNull($user->password);
        $this->assertSame('1990-06-15', $user->birth_date->toDateString());
        $this->assertSame('Hi!', $user->bio);
    }

    public function test_accounts_that_share_an_email_are_both_kept_with_their_own_records(): void
    {
        $legacy = $this->emptyLegacyDatabase();
        $legacy->table('tbl_cl_users')->insert([
            ['id' => 1, 'fullname' => 'Owner', 'email' => 'owner@example.com', 'type' => 2, 'status' => 1],
            ['id' => 2, 'fullname' => 'Waiter', 'email' => 'waiter@example.com', 'type' => 1, 'status' => 1],
            ['id' => 3, 'fullname' => 'Waiter second card', 'email' => 'WAITER@example.com', 'type' => 1, 'status' => 1],
        ]);
        $legacy->table('tbl_cl_profile')->insert([
            ['user_id' => 2, 'user_uri' => 'waiter2', 'user_image' => 'user-2.png'],
            ['user_id' => 3, 'user_uri' => 'waiter_auth01', 'user_image' => 'Waiter.png'],
        ]);
        $legacy->table('tbl_vcards')->insert([
            ['id' => 30, 'userid' => 2, 'business_name' => 'CAFE', 'business_logo' => 'logo-2.png'],
            ['id' => 31, 'userid' => 3, 'business_name' => 'SECOND COMPANY', 'business_logo' => 'logo-3.png'],
        ]);
        $legacy->table('tbl_cl_business')->insert(['id' => 10, 'bname' => 'Cafe', 'owner_id' => 1, 'bizcode' => 'BIZ1', 'status' => 1]);
        $legacy->table('tbl_cl_employee')->insert(['bizid' => 10, 'empid' => 2, 'request_from' => 'business', 'status' => 1]);

        $this->artisan('tiqs:import-legacy')->assertSuccessful();

        $this->assertSame(['waiter2', 'waiter_auth01'], User::where('email', 'waiter@example.com')->orderBy('id')->pluck('slug')->all());
        $this->assertSame('Waiter.png', User::find(3)->avatar);
        $this->assertSame('logo-3.png', Vcard::where('user_id', 3)->sole()->logo);
        $this->assertSame('logo-2.png', Vcard::where('user_id', 2)->sole()->logo);
        $employee = Employee::sole();
        $this->assertSame(2, $employee->user_id);
        $this->assertSame(EmployeeStatus::Accepted, $employee->status);
    }

    public function test_merges_both_tip_tables_and_links_fees_by_token(): void
    {
        $legacy = $this->emptyLegacyDatabase();
        $legacy->table('tbl_cl_users')->insert([
            ['id' => 1, 'fullname' => 'Owner', 'email' => 'owner@example.com', 'type' => 2, 'status' => 1],
            ['id' => 2, 'fullname' => 'Waiter', 'email' => 'waiter@example.com', 'type' => 1, 'status' => 1],
        ]);
        $legacy->table('tbl_cl_business')->insert(['id' => 10, 'bname' => 'Cafe', 'owner_id' => 1, 'bizcode' => 'BIZ1', 'status' => 1]);
        $legacy->table('tbl_cl_spot')->insert(['id' => 20, 'spotname' => 'Bar', 'spoturi' => 'bar', 'bizid' => 10, 'status' => 1]);
        $legacy->table('tbl_touchtip')->insert([
            'client_id' => 2, 'bizid' => 10, 'trans_type' => 'online', 'trans_amount' => 5, 'trans_currency' => '€',
            'status' => 100, 'orderId' => 'ORDER-1', 'token_key' => 'person-token', 'tip_rating' => 4, 'time' => 1700000000,
        ]);
        $legacy->table('tbl_tips_spot')->insert([
            ['spotid' => 20, 'bizid' => 10, 'client_id' => 2, 'trans_type' => 'online', 'trans_amount' => 3,
                'trans_currency' => 'euro', 'status' => -90, 'token_key' => 'spot-token', 'time' => 1700000000],
            ['spotid' => 0, 'bizid' => 10, 'client_id' => 0, 'trans_type' => 'online', 'trans_amount' => 2,
                'trans_currency' => 'euro', 'status' => 100, 'token_key' => 'business-token', 'time' => 1700000000],
        ]);
        $legacy->table('tbl_fees_collection')->insert(['from_user' => 2, 'token_key' => 'person-token', 'tip_amount' => 5, 'fees_amount' => 0.25, 'status' => 100]);

        $this->artisan('tiqs:import-legacy')->assertSuccessful();

        $personTip = Tip::where('token', 'person-token')->sole();
        $this->assertSame(TipType::Online, $personTip->type);
        $this->assertSame(PaymentStatus::Paid, $personTip->payment_status);
        $this->assertSame('EUR', $personTip->currency);
        $this->assertSame(4, $personTip->rating);
        $this->assertNull($personTip->spot_id);

        $spotTip = Tip::where('token', 'spot-token')->sole();
        $this->assertSame(20, $spotTip->spot_id);
        $this->assertSame(PaymentStatus::Cancelled, $spotTip->payment_status);

        $businessTip = Tip::where('token', 'business-token')->sole();
        $this->assertNull($businessTip->spot_id);
        $this->assertSame(10, $businessTip->business_id);

        $this->assertSame($personTip->id, FeeCollection::sole()->tip_id);
    }

    public function test_converts_text_dates_from_the_legacy_timezone_to_utc(): void
    {
        $legacy = $this->emptyLegacyDatabase();
        $legacy->table('tbl_cl_users')->insert(['id' => 2, 'fullname' => 'Waiter', 'email' => 'waiter@example.com', 'status' => 1]);
        $legacy->table('tbl_touchtip')->insert([
            'client_id' => 2, 'trans_type' => 'online', 'trans_amount' => 5, 'status' => 100,
            'token_key' => 'token', 'trans_time' => '2024-01-10 15:30:00',
        ]);

        $this->artisan('tiqs:import-legacy', ['--legacy-timezone' => 'Asia/Kolkata'])->assertSuccessful();

        $this->assertSame('2024-01-10 10:00:00', Tip::sole()->paid_at->utc()->toDateTimeString());
    }

    public function test_encrypts_imported_bank_account_numbers(): void
    {
        $legacy = $this->emptyLegacyDatabase();
        $legacy->table('tbl_cl_users')->insert(['id' => 2, 'fullname' => 'Waiter', 'email' => 'waiter@example.com', 'status' => 1]);
        $legacy->table('tbl_cl_bank_account')->insert(['user_id' => 2, 'bank_name' => 'ING', 'account_name' => 'Waiter', 'account_number' => 'NL91ABNA0417164300']);

        $this->artisan('tiqs:import-legacy')->assertSuccessful();

        $this->assertSame('NL91ABNA0417164300', BankAccount::sole()->account_number);
        $this->assertStringNotContainsString('NL91ABNA0417164300', DB::table('bank_accounts')->value('account_number'));
    }

    public function test_refuses_to_import_into_a_database_that_already_has_users(): void
    {
        $this->emptyLegacyDatabase();
        User::factory()->create();

        $this->artisan('tiqs:import-legacy')->assertFailed();
    }

    /**
     * Recreate the legacy tables in the legacy test database, without rows.
     */
    public function test_business_notifications_go_to_the_business_owner(): void
    {
        $legacy = $this->emptyLegacyDatabase();
        $legacy->table('tbl_cl_users')->insert([
            ['id' => 1, 'fullname' => 'Owner', 'email' => 'owner@example.com', 'type' => 2, 'status' => 1],
            ['id' => 2, 'fullname' => 'Waiter', 'email' => 'waiter@example.com', 'type' => 1, 'status' => 1],
        ]);
        $legacy->table('tbl_cl_business')->insert(['id' => 10, 'bname' => 'Cafe', 'owner_id' => 1, 'bizcode' => 'BIZ1', 'status' => 1]);
        $notification = ['title' => 'Join', 'message' => 'Waiter asked to join', 'noti_link' => '', 'noti_type' => 'join_request', 'noti_icon' => '', 'status' => 1, 'time' => 1700000000];
        $legacy->table('tbl_notification')->insert([
            ['userid' => 2, 'bizid' => 10, 'to_notify' => 'business', 'is_read' => 0, ...$notification],
            ['userid' => 2, 'bizid' => 10, 'to_notify' => 'user', 'is_read' => 1, ...$notification],
        ]);

        $this->artisan('tiqs:import-legacy')->assertSuccessful();

        $this->assertSame(1, UserNotification::where('user_id', 1)->whereNull('read_at')->count());
        $this->assertSame(1, UserNotification::where('user_id', 2)->count());
    }

    private function emptyLegacyDatabase(): Connection
    {
        $legacy = DB::connection('legacy');

        $legacy->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($legacy->getSchemaBuilder()->getTableListing(schema: $legacy->getDatabaseName(), schemaQualified: false) as $table) {
            $legacy->statement("DROP TABLE `{$table}`");
        }
        $legacy->unprepared(file_get_contents(base_path('tests/Fixtures/legacy_schema.sql')));
        $legacy->statement('SET FOREIGN_KEY_CHECKS=1');

        return $legacy;
    }
}
