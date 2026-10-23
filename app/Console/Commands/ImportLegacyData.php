<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\EmployeeStatus;
use App\Enums\PaymentStatus;
use App\Enums\TipType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\AppSetting;
use App\Models\BankAccount;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Copies the data of the old CodeIgniter app (the "legacy" connection) into
 * the new schema. The legacy database is only read, never written.
 *
 * Primary keys are kept wherever a table maps one to one, so links in old
 * emails and QR codes that carry an id keep pointing at the same record.
 * Tips are the exception: the two legacy tip tables are merged and get new ids.
 */
#[Signature('tiqs:import-legacy
    {--fresh : Drop all tables, run the migrations, then import}
    {--legacy-path= : Path of the old app on disk, used to read email template files}
    {--legacy-timezone=Asia/Kolkata : Timezone the old app wrote its text dates in (its timezone switch never matched the live host, so it always used Asia/Kolkata)}')]
#[Description('Import users, businesses, tips and settings from the old CodeIgniter database')]
class ImportLegacyData extends Command
{
    private const int CHUNK = 500;

    /** Platform fee the old app charged on tips (its FEES_PERCENT and FEES_PRICE constants). */
    private const string LEGACY_TIP_FEE_PERCENT = '0.50';

    private const string LEGACY_TIP_FEE_FIXED = '0.00';

    /** @var array<string, array{read: int, imported: int, skipped: int}> */
    private array $stats = [];

    /** @var list<string> */
    private array $warnings = [];

    /** @var array<int, int> Legacy user id => new user id */
    private array $userIds = [];

    /** @var array<int, true> */
    private array $businessIds = [];

    /** @var array<int, true> */
    private array $roleIds = [];

    /** @var array<int, true> */
    private array $agreementIds = [];

    /** @var array<int, int> Spot id => business id */
    private array $spotBusiness = [];

    /** @var array<string, int> Withdrawal reference => withdrawal id */
    private array $withdrawalIds = [];

    /** @var array<string, int> Legacy tip token => new tip id */
    private array $tipIds = [];

    public function handle(): int
    {
        if (! $this->prepareTarget()) {
            return self::FAILURE;
        }

        $started = microtime(true);

        try {
            DB::transaction(function (): void {
                $this->importUsers();
                $this->importBusinesses();
                $this->importBusinessRoles();
                $this->importAgreements();
                $this->importEmployees();
                $this->importSpots();
                $this->importSpotEmployees();
                $this->importWorkingHours();
                $this->importBankAccounts();
                $this->importWithdrawals();
                $this->importTips();
                $this->importFeeCollections();
                $this->importUserFees();
                $this->importSubscriptions();
                $this->importReferrals();
                $this->importPaymentMethods();
                $this->importUserSettings();
                $this->importTipPageSettings();
                $this->importQrSettings();
                $this->importVcards();
                $this->importVcardThemes();
                $this->importWidgetSettings();
                $this->importAppSettings();
                $this->importEmailTemplates();
                $this->importTranslations();
                $this->importNotifications();
                $this->importNotificationPreferences();
                $this->importVisitorLogs();
            });
        } catch (Throwable $exception) {
            $this->components->error('Import failed and was rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->report(microtime(true) - $started);

        return self::SUCCESS;
    }

    private function prepareTarget(): bool
    {
        if ($this->option('fresh')) {
            if (app()->isProduction() && ! $this->confirm('This drops every table in the production database. Continue?')) {
                return false;
            }

            $this->call('migrate:fresh', ['--force' => true]);

            return true;
        }

        if (User::withTrashed()->exists()) {
            $this->components->error('The target database already has users. Run with --fresh to start from an empty database.');

            return false;
        }

        return true;
    }

    private function importUsers(): void
    {
        // A user can have several profile rows; the newest one wins.
        $profiles = [];
        foreach ($this->legacyRows('tbl_cl_profile') as $profile) {
            $profiles[(int) $profile->user_id] = $profile;
        }

        $usedSlugs = [];
        $rows = [];

        foreach ($this->legacyRows('tbl_cl_users') as $user) {
            $this->read('users');
            $id = (int) $user->id;
            $email = Str::lower(trim((string) $user->email));

            if ($email === '') {
                $this->skip('users', "User {$id} has no email address.");

                continue;
            }

            // Accounts that share an email address are all kept, as in the old app
            // (for example one business card per company); login picks the oldest
            // account whose password matches (AuthenticateUser).
            $this->userIds[$id] = $id;

            $profile = $profiles[$id] ?? null;
            $slug = $this->uniqueSlug(trim(stripslashes((string) ($profile->user_uri ?? ''))), $id, $usedSlugs);
            $createdAt = $this->timestamp($user->created) ?? now()->toDateTimeString();
            $updatedAt = $this->timestamp($user->modified) ?? $createdAt;
            $legacyStatus = (int) $user->status;

            $rows[] = [
                'id' => $id,
                'name' => $this->clean($user->fullname) ?? Str::before($email, '@'),
                'email' => $email,
                'email_verified_at' => $legacyStatus === 0 ? null : $createdAt,
                'password' => null,
                'legacy_password_md5' => preg_match('/^[a-f0-9]{32}$/i', (string) $user->password) ? Str::lower($user->password) : null,
                'slug' => $slug,
                'role' => (int) $user->roll === 2 ? UserRole::Admin->value : UserRole::User->value,
                'account_type' => (int) $user->type === 2 ? AccountType::Business->value : AccountType::Private->value,
                'status' => match ($legacyStatus) {
                    0 => UserStatus::Pending->value,
                    2 => UserStatus::Deactivated->value,
                    default => UserStatus::Active->value,
                },
                'phone' => $this->clean($user->phone),
                'country_code' => $this->clean($user->countrycode),
                'country' => $this->clean($user->country),
                'birth_date' => $this->birthDate($user->bdate),
                'address' => $this->clean($user->address),
                'bio' => $this->clean($profile->user_status ?? null),
                'avatar' => $this->clean($profile->user_image ?? null),
                'accepts_individual_tips' => (int) $user->tip_account === 1,
                'push_token' => $this->clean($user->playerid),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'deleted_at' => $legacyStatus === 3 ? $updatedAt : null,
            ];
        }

        $this->insert('users', $rows);
    }

    private function importBusinesses(): void
    {
        $usedCodes = [];
        $rows = [];

        foreach ($this->legacyRows('tbl_cl_business') as $business) {
            $this->read('businesses');
            $ownerId = $this->user($business->owner_id);

            if ($ownerId === null) {
                $this->skip('businesses', "Business {$business->id} has no existing owner.");

                continue;
            }

            $code = Str::upper(trim((string) $business->bizcode));
            if ($code === '' || isset($usedCodes[$code])) {
                $code = 'BIZ'.str_pad((string) $business->id, 8, '0', STR_PAD_LEFT);
            }
            $usedCodes[$code] = true;

            $this->businessIds[(int) $business->id] = true;
            $rows[] = [
                'id' => (int) $business->id,
                'owner_id' => $ownerId,
                'name' => $this->clean($business->bname) ?? 'Business '.$business->id,
                'email' => $this->clean($business->bemail),
                'address' => $this->clean($business->baddress),
                'code' => $code,
                'pay_vendor_id' => $this->clean($business->tiqs_vendor_id),
                'default_agreement_id' => null,
                'is_active' => (int) $business->status === 1,
                'created_at' => $this->timestamp($business->created),
                'updated_at' => $this->timestamp($business->modified) ?? $this->timestamp($business->created),
            ];
        }

        $this->insert('businesses', $rows);
    }

    private function importBusinessRoles(): void
    {
        $rows = [];
        $parents = [];

        foreach ($this->legacyRows('tbl_business_roll') as $role) {
            $this->read('business_roles');

            if (! $this->business($role->bizid)) {
                $this->skip('business_roles', "Role {$role->id} belongs to a missing business.");

                continue;
            }

            $this->roleIds[(int) $role->id] = true;
            if ((int) $role->parent_id > 0) {
                $parents[(int) $role->id] = (int) $role->parent_id;
            }

            $rows[] = [
                'id' => (int) $role->id,
                'business_id' => (int) $role->bizid,
                'parent_id' => null,
                'name' => $this->clean($role->roll_name) ?? 'Role '.$role->id,
                'level' => (int) $role->level,
                'receives_tips' => (int) $role->tip === 1,
                'is_active' => (int) $role->status === 1,
                'created_at' => $this->timestamp($role->modified),
                'updated_at' => $this->timestamp($role->modified),
            ];
        }

        $this->insert('business_roles', $rows);

        // Parents are linked afterwards, because a parent may have a higher id than its child.
        foreach ($parents as $roleId => $parentId) {
            if (isset($this->roleIds[$parentId])) {
                DB::table('business_roles')->where('id', $roleId)->update(['parent_id' => $parentId]);
            } else {
                $this->note('business_roles', "Role {$roleId} pointed at missing parent role {$parentId}; it now has no parent.");
            }
        }
    }

    private function importAgreements(): void
    {
        $rows = [];

        foreach ($this->legacyRows('tbl_agreement') as $agreement) {
            $this->read('agreements');

            if (! $this->business($agreement->bizid)) {
                $this->skip('agreements', "Agreement {$agreement->id} belongs to a missing business.");

                continue;
            }

            $this->agreementIds[(int) $agreement->id] = true;
            $rows[] = [
                'id' => (int) $agreement->id,
                'business_id' => (int) $agreement->bizid,
                'created_by' => $this->user($agreement->userid),
                'title' => $this->clean($agreement->header) ?? 'Agreement '.$agreement->id,
                'content' => (string) $agreement->content,
                'role_shares' => json_encode($this->roleShares($agreement->percentage, (int) $agreement->id)),
                'is_default' => (int) $agreement->is_default === 1,
                'is_for_spots' => (int) $agreement->is_spot === 1,
                'is_active' => (int) $agreement->status === 1,
                'created_at' => $this->timestamp($agreement->time),
                'updated_at' => $this->timestamp($agreement->time),
            ];
        }

        $this->insert('agreements', $rows);

        foreach ($this->legacyRows('tbl_cl_business') as $business) {
            $agreementId = (int) $business->default_agrid;

            if ($agreementId > 0 && isset($this->businessIds[(int) $business->id], $this->agreementIds[$agreementId])) {
                DB::table('businesses')->where('id', $business->id)->update(['default_agreement_id' => $agreementId]);
            }
        }
    }

    private function importEmployees(): void
    {
        $seen = [];
        $rows = [];

        foreach ($this->legacyRows('tbl_cl_employee') as $employee) {
            $this->read('employees');
            $userId = $this->user($employee->empid);

            if ($userId === null || ! $this->business($employee->bizid)) {
                $this->skip('employees', "Employee record {$employee->id} has a missing user or business.");

                continue;
            }

            $pair = $employee->bizid.'-'.$userId;
            if (isset($seen[$pair])) {
                $this->skip('employees', "Employee record {$employee->id} duplicates user {$userId} at business {$employee->bizid}.");

                continue;
            }
            $seen[$pair] = true;

            $rows[] = [
                'id' => (int) $employee->id,
                'business_id' => (int) $employee->bizid,
                'user_id' => $userId,
                'business_role_id' => $this->role($employee->roll),
                'agreement_id' => $this->agreement($employee->agreement_id),
                'requested_by' => $this->clean($employee->request_from) ?? 'business',
                'status' => match ((int) $employee->status) {
                    1 => EmployeeStatus::Accepted->value,
                    2 => EmployeeStatus::Rejected->value,
                    default => EmployeeStatus::Pending->value,
                },
                'reason' => $this->clean($employee->reason),
                'is_live' => (int) $employee->live === 1,
                'last_live_at' => $this->timestamp($employee->last_live),
                'created_at' => $this->timestamp($employee->time),
                'updated_at' => $this->timestamp($employee->time),
            ];
        }

        $this->insert('employees', $rows);
    }

    private function importSpots(): void
    {
        $usedSlugs = [];
        $rows = [];

        foreach ($this->legacyRows('tbl_cl_spot') as $spot) {
            $this->read('spots');

            if (! $this->business($spot->bizid)) {
                $this->skip('spots', "Spot {$spot->id} belongs to a missing business.");

                continue;
            }

            $slug = trim((string) $spot->spoturi);
            if ($slug === '') {
                $slug = Str::slug((string) $spot->spotname) ?: 'spot-'.$spot->id;
            }
            $key = $spot->bizid.'/'.Str::lower($slug);
            if (isset($usedSlugs[$key])) {
                $this->note('spots', "Spot {$spot->id} had the same URL name as another spot of business {$spot->bizid}; renamed to {$slug}-{$spot->id}.");
                $slug .= '-'.$spot->id;
            }
            $usedSlugs[$key] = true;

            $this->spotBusiness[(int) $spot->id] = (int) $spot->bizid;
            $rows[] = [
                'id' => (int) $spot->id,
                'business_id' => (int) $spot->bizid,
                'name' => $this->clean($spot->spotname) ?? 'Spot '.$spot->id,
                'slug' => Str::limit($slug, 100, ''),
                'is_multi' => (int) $spot->multispot === 1,
                'is_active' => (int) $spot->status === 1,
                'created_at' => $this->timestamp($spot->created),
                'updated_at' => $this->timestamp($spot->modify) ?? $this->timestamp($spot->created),
            ];
        }

        $this->insert('spots', $rows);
    }

    private function importSpotEmployees(): void
    {
        $seen = [];
        $rows = [];

        foreach ($this->legacyRows('tbl_spot_employee') as $member) {
            $this->read('spot_employees');
            $userId = $this->user($member->userid);

            if ($userId === null || ! isset($this->spotBusiness[(int) $member->spotid])) {
                $this->skip('spot_employees', "Spot employee {$member->id} has a missing user or spot.");

                continue;
            }

            $pair = $member->spotid.'-'.$userId;
            if (isset($seen[$pair])) {
                $this->skip('spot_employees', "Spot employee {$member->id} is a duplicate.");

                continue;
            }
            $seen[$pair] = true;

            $rows[] = [
                'id' => (int) $member->id,
                'spot_id' => (int) $member->spotid,
                'user_id' => $userId,
                'business_role_id' => $this->role($member->roll),
                'agreement_id' => $this->agreement($member->agreement),
                'is_live' => (int) $member->live === 1,
                'is_active' => (int) $member->status === 1,
                'created_at' => $this->timestamp($member->created),
                'updated_at' => $this->timestamp($member->modify) ?? $this->timestamp($member->created),
            ];
        }

        $this->insert('spot_employees', $rows);
    }

    private function importWorkingHours(): void
    {
        $rows = [];

        foreach ($this->legacyRows('tbl_shift_hours') as $shift) {
            $this->read('working_hours');
            $isVcard = $shift->foruse === 'cards';
            $userId = $this->user((int) $shift->empid > 0 ? $shift->empid : $shift->userid);

            if ($userId === null || ! in_array($shift->days, WorkingHour::DAYS, true)) {
                $this->skip('working_hours', "Shift {$shift->id} has a missing user or an unknown day.");

                continue;
            }

            $rows[] = $this->workingHourRow($isVcard ? 'vcard' : 'shift', $userId, $this->business($shift->bizid) ? (int) $shift->bizid : null, null, $shift);
        }

        foreach ($this->legacyRows('tbl_shift_hours_spot') as $shift) {
            $this->read('working_hours');
            $userId = $this->user($shift->userid);

            if ($userId === null || ! isset($this->spotBusiness[(int) $shift->spotid]) || ! in_array($shift->days, WorkingHour::DAYS, true)) {
                $this->skip('working_hours', "Spot shift {$shift->id} has a missing user or spot, or an unknown day.");

                continue;
            }

            $rows[] = $this->workingHourRow('spot', $userId, $this->spotBusiness[(int) $shift->spotid], (int) $shift->spotid, $shift);
        }

        $this->insert('working_hours', $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function workingHourRow(string $purpose, int $userId, ?int $businessId, ?int $spotId, object $shift): array
    {
        $ranges = json_decode((string) $shift->hours, true);

        return [
            'purpose' => $purpose,
            'user_id' => $userId,
            'business_id' => $businessId,
            'spot_id' => $spotId,
            'day' => $shift->days,
            'time_ranges' => json_encode(is_array($ranges) ? array_values($ranges) : []),
            'is_active' => (int) $shift->status === 1,
            'created_at' => $this->timestamp($shift->time),
            'updated_at' => $this->timestamp($shift->time),
        ];
    }

    private function importBankAccounts(): void
    {
        $seen = [];

        foreach ($this->legacyRows('tbl_cl_bank_account') as $account) {
            $this->read('bank_accounts');
            $userId = $this->user($account->user_id);

            if ($userId === null || isset($seen[$userId])) {
                $this->skip('bank_accounts', "Bank account {$account->id} has a missing user or is a duplicate.");

                continue;
            }
            $seen[$userId] = true;

            // Saved through the model so the account number is encrypted.
            $model = new BankAccount;
            $model->forceFill([
                'id' => (int) $account->id,
                'user_id' => $userId,
                'bank_name' => $this->clean($account->bank_name) ?? '',
                'account_name' => $this->clean($account->account_name) ?? '',
                'account_type' => (int) $account->account_type,
                'account_number' => trim((string) $account->account_number),
                'swift_code' => $this->clean($account->swift_code),
                'created_at' => $this->timestamp($account->modified),
                'updated_at' => $this->timestamp($account->modified),
            ]);
            $model->timestamps = false;
            $model->saveQuietly();
            $this->imported('bank_accounts');
        }
    }

    private function importWithdrawals(): void
    {
        $rows = [];

        foreach ($this->legacyRows('tbl_withdrawal') as $withdrawal) {
            $this->read('withdrawals');
            $userId = $this->user($withdrawal->client_id);

            if ($userId === null) {
                $this->skip('withdrawals', "Withdrawal {$withdrawal->id} belongs to a missing user.");

                continue;
            }

            $reference = trim((string) $withdrawal->process_id);
            if ($reference === '' || isset($this->withdrawalIds[$reference])) {
                $reference = 'LEGACY-'.$withdrawal->id;
            }
            $this->withdrawalIds[$reference] = (int) $withdrawal->id;

            $isPaid = (int) $withdrawal->w_status === 1;
            $requestedAt = $this->timestamp($withdrawal->w_date) ?? $this->timestamp($withdrawal->w_time);

            $rows[] = [
                'id' => (int) $withdrawal->id,
                'user_id' => $userId,
                'reference' => $reference,
                'type' => (int) $withdrawal->w_type,
                'amount' => $this->money($withdrawal->w_amount),
                'fee' => $this->money($withdrawal->w_fees),
                'status' => $isPaid ? WithdrawalStatus::Paid->value : WithdrawalStatus::Requested->value,
                'requested_at' => $requestedAt,
                'paid_at' => $isPaid ? $requestedAt : null,
                'created_at' => $requestedAt,
                'updated_at' => $requestedAt,
            ];
        }

        $this->insert('withdrawals', $rows);
    }

    /**
     * Merges tbl_touchtip (tips to a person) and tbl_tips_spot (tips at a spot).
     */
    private function importTips(): void
    {
        $usedTokens = [];
        $usedOrders = [];
        $rows = [];

        foreach (['tbl_touchtip' => false, 'tbl_tips_spot' => true] as $table => $isSpotTable) {
            foreach ($this->legacyRows($table) as $tip) {
                $this->read('tips');

                // Spot id 0 in tbl_tips_spot is a tip to the business as a whole, not to one spot.
                $spotId = null;
                if ($isSpotTable && (int) $tip->spotid > 0) {
                    if (! isset($this->spotBusiness[(int) $tip->spotid])) {
                        $this->skip('tips', "Spot tip {$tip->id} belongs to missing spot {$tip->spotid}.");

                        continue;
                    }
                    $spotId = (int) $tip->spotid;
                }

                $businessId = $this->business($tip->bizid) ? (int) $tip->bizid : ($spotId !== null ? $this->spotBusiness[$spotId] : null);
                $status = PaymentStatus::fromPayNlCode((int) $tip->status);

                $token = trim((string) $tip->token_key);
                $legacyToken = $token;
                if ($token === '' || isset($usedTokens[$token])) {
                    $token = Str::random(40);
                    $legacyToken = '';
                }
                $usedTokens[$token] = true;

                $orderId = trim((string) $tip->orderId);
                if ($orderId === '' || $orderId === '0' || isset($usedOrders[$orderId])) {
                    $orderId = null;
                } else {
                    $usedOrders[$orderId] = true;
                }

                $reference = trim((string) $tip->process_id);
                $withdrawalId = $reference !== '' ? ($this->withdrawalIds[$reference] ?? null) : null;
                if ((int) $tip->process === 2 && $withdrawalId === null) {
                    $this->note('tips', "{$table} {$tip->id} was marked as paid out, but its withdrawal was not found; it is back in the balance.");
                }

                $rating = (float) $tip->tip_rating;
                $createdAt = $this->timestamp($tip->time) ?? $this->timestamp($tip->trans_time);

                $rows[] = [
                    'type' => $tip->trans_type === 'online' ? TipType::Online->value : TipType::Rating->value,
                    'recipient_id' => $this->user($tip->client_id),
                    'business_id' => $businessId,
                    'spot_id' => $spotId,
                    'sender_id' => $isSpotTable ? null : $this->user($tip->from_user),
                    'amount' => $this->money($tip->trans_amount),
                    'currency' => $this->currency($tip->trans_currency),
                    'payment_status' => $status->value,
                    'pay_order_id' => $orderId,
                    'paid_at' => $status === PaymentStatus::Paid ? ($this->timestamp($tip->trans_time) ?? $createdAt) : null,
                    'withdrawal_id' => $withdrawalId,
                    'rating' => $rating > 0 ? max(1, min(5, (int) round($rating))) : null,
                    'review' => $this->clean($tip->service_review),
                    'is_review_pinned' => (int) $tip->sticky_review === 1,
                    'reviewer_name' => $this->clean($tip->review_name),
                    'reviewer_email' => $this->clean($tip->buyer_email),
                    'token' => $token,
                    'ip_address' => Str::limit((string) $tip->user_ip, 45, '') ?: null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                    '_legacy_token' => $legacyToken,
                ];
            }
        }

        $legacyTokens = array_filter(array_column($rows, '_legacy_token'));
        $rows = array_map(fn (array $row): array => array_diff_key($row, ['_legacy_token' => true]), $rows);

        $this->insert('tips', $rows);

        // Fee rows link to tips by token, so remember which new id each legacy token got.
        foreach (array_chunk($legacyTokens, self::CHUNK) as $tokens) {
            foreach (DB::table('tips')->whereIn('token', $tokens)->pluck('id', 'token') as $token => $id) {
                $this->tipIds[$token] = (int) $id;
            }
        }
    }

    private function importFeeCollections(): void
    {
        $rows = [];

        foreach ($this->legacyRows('tbl_fees_collection') as $fee) {
            $this->read('fee_collections');

            $rows[] = [
                'id' => (int) $fee->id,
                'user_id' => $this->user($fee->from_user),
                'payment_id' => null,
                'tip_id' => $this->tipIds[trim((string) $fee->token_key)] ?? null,
                'withdrawal_id' => null,
                'fee_type' => (int) $fee->fees_type,
                'tip_amount' => $this->money($fee->tip_amount),
                'fee_amount' => $this->money($fee->fees_amount),
                'tax_amount' => $this->money($fee->tax_amount),
                'payment_status' => PaymentStatus::fromPayNlCode((int) $fee->status)->value,
                'created_at' => $this->timestamp($fee->ondate) ?? $this->timestamp($fee->time),
                'updated_at' => $this->timestamp($fee->ondate) ?? $this->timestamp($fee->time),
            ];
        }

        $this->insert('fee_collections', $rows);
    }

    private function importUserFees(): void
    {
        $rows = [];

        foreach ($this->legacyRows('tbl_fees_user') as $fee) {
            $this->read('user_fees');
            $userId = $this->user($fee->userid);

            if ($userId === null) {
                $this->skip('user_fees', "User fee {$fee->id} belongs to a missing user.");

                continue;
            }

            $rows[] = [
                'id' => (int) $fee->id,
                'user_id' => $userId,
                'type' => Str::limit((string) $fee->type, 50, ''),
                'applies_to' => $this->clean($fee->option_for),
                'fixed_fee' => $this->money($fee->fees_price),
                'percent_fee' => $this->money($fee->fees_percent),
                'is_active' => (int) $fee->status === 1,
                'created_at' => $this->timestamp($fee->time),
                'updated_at' => $this->timestamp($fee->time),
            ];
        }

        $this->insert('user_fees', $rows);
    }

    private function importSubscriptions(): void
    {
        $usedOrders = [];
        $rows = [];

        foreach ($this->legacyRows('tbl_subscription') as $subscription) {
            $this->read('subscriptions');
            $userId = $this->user($subscription->userid);

            if ($userId === null) {
                $this->skip('subscriptions', "Subscription {$subscription->id} belongs to a missing user.");

                continue;
            }

            $orderId = trim((string) $subscription->orderId);
            // Order id 0 marked a plan given by an admin without payment.
            if ($orderId === '' || $orderId === '0' || isset($usedOrders[$orderId])) {
                $orderId = null;
            } else {
                $usedOrders[$orderId] = true;
            }

            $rows[] = [
                'id' => (int) $subscription->id,
                'user_id' => $userId,
                'plan' => $this->clean($subscription->subscription_plan) ?? 'unknown',
                'amount' => $this->money($subscription->amount),
                'pay_order_id' => $orderId,
                'payment_status' => PaymentStatus::fromPayNlCode((int) $subscription->status)->value,
                'reference' => $this->clean($subscription->process_id),
                'starts_on' => $this->date($subscription->valid_from),
                'ends_on' => $this->date($subscription->valid_till),
                'is_active' => (int) $subscription->active === 1,
                'modified_by' => $this->user($subscription->modify_by),
                'created_at' => $this->timestamp($subscription->time),
                'updated_at' => $this->timestamp($subscription->time),
            ];
        }

        $this->insert('subscriptions', $rows);
    }

    private function importReferrals(): void
    {
        $seen = [];
        $rows = [];

        foreach ($this->legacyRows('tbl_referral') as $referral) {
            $this->read('referrals');
            $referrerId = $this->user($referral->userid);
            $referredId = $this->user($referral->r_userid);

            if ($referrerId === null || $referredId === null || $referrerId === $referredId || isset($seen[$referrerId.'-'.$referredId])) {
                $this->skip('referrals', "Referral {$referral->id} has a missing user or is a duplicate.");

                continue;
            }
            $seen[$referrerId.'-'.$referredId] = true;

            $reference = trim((string) $referral->process_id);
            $rows[] = [
                'id' => (int) $referral->id,
                'referrer_id' => $referrerId,
                'referred_user_id' => $referredId,
                'amount' => $this->money($referral->r_amount),
                'is_confirmed' => (int) $referral->status === 1,
                'withdrawal_id' => $reference !== '' ? ($this->withdrawalIds[$reference] ?? null) : null,
                'created_at' => $this->timestamp($referral->time),
                'updated_at' => $this->timestamp($referral->time),
            ];
        }

        $this->insert('referrals', $rows);
    }

    private function importPaymentMethods(): void
    {
        $rows = [];

        foreach ($this->legacyRows('tbl_payment_methods') as $method) {
            $this->read('payment_methods');
            $userId = $this->user($method->userid);

            if ($userId === null || isset($rows[$userId])) {
                $this->skip('payment_methods', "Payment methods {$method->id} have a missing user or are a duplicate.");

                continue;
            }

            $optionIds = array_values(array_map('intval', array_filter(
                array_map('trim', explode(',', (string) $method->method)),
                fn (string $id): bool => ctype_digit($id),
            )));

            $rows[$userId] = [
                'id' => (int) $method->id,
                'user_id' => $userId,
                'pay_option_ids' => json_encode($optionIds),
                'is_active' => (int) $method->status === 1,
                'created_at' => $this->timestamp($method->time),
                'updated_at' => $this->timestamp($method->time),
            ];
        }

        $this->insert('payment_methods', array_values($rows));
    }

    private function importUserSettings(): void
    {
        $rows = [];

        foreach ($this->legacyRows('tbl_settings') as $setting) {
            $this->read('user_settings');
            $userId = $this->user($setting->userid);
            $key = trim((string) $setting->type);

            if ($userId === null || $key === '') {
                $this->skip('user_settings', "Setting {$setting->id} has a missing user or no name.");

                continue;
            }

            if (isset($rows[$userId.'-'.$key])) {
                $this->skip('user_settings', "Setting {$setting->id} repeats \"{$key}\" for user {$userId}; the newest value is kept.");
            }

            $rows[$userId.'-'.$key] = [
                'id' => (int) $setting->id,
                'user_id' => $userId,
                'business_id' => $this->business($setting->bizid) ? (int) $setting->bizid : null,
                'key' => Str::limit($key, 30, ''),
                'value' => $this->clean($setting->value),
                'sub_value' => $this->clean($setting->subvalue),
                'created_at' => $this->timestamp($setting->time),
                'updated_at' => $this->timestamp($setting->time),
            ];
        }

        $this->insert('user_settings', array_values($rows));
    }

    private function importTipPageSettings(): void
    {
        $rows = [];

        foreach ($this->legacyRows('tbl_settings_profile') as $page) {
            $this->read('tip_page_settings');
            $userId = $this->user($page->userid);

            if ($userId === null || isset($rows[$userId])) {
                $this->skip('tip_page_settings', "Tip page settings {$page->id} have a missing user or are a duplicate.");

                continue;
            }

            $amounts = array_values(array_map('intval', array_filter(
                [$page->payampunt1, $page->payampunt2, $page->payampunt3],
                fn ($amount): bool => $amount !== null && (int) $amount > 0,
            )));

            $rows[$userId] = [
                'id' => (int) $page->id,
                'user_id' => $userId,
                'profile_type' => (int) $page->profile_type,
                'default_page' => (int) $page->defaultpage,
                'page_header' => $this->clean($page->page_header),
                'page_title' => $this->clean($page->page_title),
                'success_header' => $this->clean($page->success_header),
                'success_message' => $this->clean($page->success_message),
                'theme' => (int) $page->theme,
                'background_image' => $this->clean($page->back_image),
                'panel_color' => $this->clean($page->back_color),
                'page_color' => $this->clean($page->bg_color),
                'text_color' => $this->clean($page->text_color),
                'emoji_color' => $this->clean($page->emoji_color),
                'emoji_type' => (int) $page->emoji_type,
                'button_color' => Str::limit((string) $page->button_color, 150, '') ?: null,
                'font_size' => Str::limit((string) $page->font_size, 50, '') ?: null,
                'show_email_field' => $page->emailbox === null || (int) $page->emailbox === 1,
                'show_review_field' => $page->reviewbox === null || (int) $page->reviewbox === 1,
                'review_style' => (int) $page->review_style,
                'pin_reviews' => (int) $page->sticky_review === 1,
                'preset_amounts' => $amounts === [] ? null : json_encode($amounts),
                'is_active' => $page->status === null || (int) $page->status === 1,
                'created_at' => $this->timestamp($page->time),
                'updated_at' => $this->timestamp($page->time),
            ];
        }

        $this->insert('tip_page_settings', array_values($rows));
    }

    private function importQrSettings(): void
    {
        $rows = [];

        foreach ($this->legacyRows('tbl_settings_qr') as $qr) {
            $this->read('qr_settings');
            $userId = $this->user($qr->userid);

            if ($userId === null || isset($rows[$userId])) {
                $this->skip('qr_settings', "QR settings {$qr->id} have a missing user or are a duplicate.");

                continue;
            }

            $rows[$userId] = [
                'id' => (int) $qr->id,
                'user_id' => $userId,
                'header' => $this->clean($qr->header),
                'title' => $this->clean($qr->title),
                'qr_image' => Str::limit((string) $qr->qrcode, 255, '') ?: null,
                'is_active' => (int) $qr->status === 1,
                'created_at' => $this->timestamp($qr->time),
                'updated_at' => $this->timestamp($qr->time),
            ];
        }

        $this->insert('qr_settings', array_values($rows));
    }

    private function importVcards(): void
    {
        $rows = [];

        foreach ($this->legacyRows('tbl_vcards') as $card) {
            $this->read('vcards');
            $userId = $this->user($card->userid);

            if ($userId === null) {
                $this->skip('vcards', "vCard {$card->id} belongs to a missing user.");

                continue;
            }

            if (isset($rows[$userId])) {
                $this->skip('vcards', "vCard {$card->id} is a second card of user {$userId}; the first one is kept.");

                continue;
            }

            $links = array_values(array_filter(array_map('trim', explode(',', (string) $card->solinks))));

            $rows[$userId] = [
                'id' => (int) $card->id,
                'user_id' => $userId,
                'business_name' => $this->clean($card->business_name),
                'business_title' => $this->clean($card->business_title),
                'business_email' => $this->clean($card->business_email),
                'business_address' => $this->clean($card->business_add),
                'latitude' => $this->coordinate($card->blatitude, 90),
                'longitude' => $this->coordinate($card->blongitude, 180),
                'logo' => $this->clean($card->business_logo),
                'banner' => Str::limit((string) $card->banner, 255, '') ?: null,
                'theme' => (int) $card->theme,
                'social_links' => json_encode($links),
                'google_review_url' => $this->clean($card->googler_link),
                'ambassador_url' => $this->clean($card->ambassador_link),
                'appointment_url' => $this->clean($card->appontment_link),
                'google_wallet_url' => $this->clean($card->googlewallet_link),
                'apple_wallet_url' => $this->clean($card->ioswallet_link),
                // These four were "hide" flags in the old app: 0 = shown. is_bizname = 1 showed the logo instead of the name.
                'show_business_name' => (int) $card->is_bizname === 0,
                'show_location' => (int) $card->is_blocation === 0,
                'show_opening_hours' => (int) $card->is_bizours === 0,
                'show_review' => (int) $card->is_review === 0,
                'show_business_icon' => (int) $card->is_bizicon === 1,
                'show_email_icon' => (int) $card->is_emailicon === 1,
                'show_phone_icon' => (int) $card->is_phoneicon === 1,
                'show_address_icon' => (int) $card->is_addressicon === 1,
                'is_active' => (int) $card->status === 1,
                'created_at' => $this->timestamp($card->time),
                'updated_at' => $this->timestamp($card->time),
            ];
        }

        $this->insert('vcards', array_values($rows));
    }

    private function importVcardThemes(): void
    {
        $columns = [
            'pbgcolor' => 'primary_background', 'sbgcolor' => 'secondary_background',
            'ptextcolor' => 'primary_text', 'stextcolor' => 'secondary_text',
            'pbtncolor' => 'primary_button', 'sbtncolor' => 'secondary_button',
            'pbtntextcolor' => 'primary_button_text', 'sbtntextcolor' => 'secondary_button_text',
            'brdcolor' => 'border', 'rtncolor' => 'return_button',
        ];
        $rows = [];

        foreach ($this->legacyRows('tbl_vcards_theme') as $theme) {
            $this->read('vcard_themes');
            $userId = $this->user($theme->userid);

            if ($userId === null || isset($rows[$userId])) {
                $this->skip('vcard_themes', "vCard theme {$theme->id} has a missing user or is a duplicate.");

                continue;
            }

            $row = [
                'id' => (int) $theme->id,
                'user_id' => $userId,
                'is_active' => (int) $theme->status === 1,
                'created_at' => $this->timestamp($theme->time),
                'updated_at' => $this->timestamp($theme->time),
            ];
            foreach ($columns as $legacy => $column) {
                $row[$column] = Str::limit(trim((string) $theme->{$legacy}), 30, '') ?: null;
            }

            $rows[$userId] = $row;
        }

        $this->insert('vcard_themes', array_values($rows));
    }

    private function importWidgetSettings(): void
    {
        $rows = [];

        foreach ($this->legacyRows('tbl_widget_settings') as $widget) {
            $this->read('widget_settings');
            $name = $this->clean($widget->for_widget) ?? 'review';

            if (! $this->business($widget->bizid) || isset($rows[$widget->bizid.'-'.$name])) {
                $this->skip('widget_settings', "Widget settings {$widget->id} have a missing business or are a duplicate.");

                continue;
            }

            $rows[$widget->bizid.'-'.$name] = [
                'id' => (int) $widget->id,
                'business_id' => (int) $widget->bizid,
                'widget' => Str::limit($name, 30, ''),
                'width' => (int) $widget->width ?: null,
                'height' => (int) $widget->height ?: null,
                'frame_size' => $this->clean($widget->framesize),
                'is_scrollable' => (int) $widget->scrollable === 1,
                'theme' => (int) $widget->theme,
                'is_active' => (int) $widget->status === 1,
                'created_at' => $this->timestamp($widget->time),
                'updated_at' => $this->timestamp($widget->time),
            ];
        }

        $this->insert('widget_settings', array_values($rows));
    }

    private function importAppSettings(): void
    {
        $settings = $this->legacy()->table('tbl_app_settings')->orderBy('id')->first();
        $this->read('app_settings');

        // The old app never read the fee columns of tbl_app_settings: tips were charged
        // with its FEES_PERCENT / FEES_PRICE constants (0.5% + EUR 0). Carry those over.
        $model = AppSetting::current();
        $model->forceFill([
            'tip_fee_fixed' => self::LEGACY_TIP_FEE_FIXED,
            'tip_fee_percent' => self::LEGACY_TIP_FEE_PERCENT,
            'business_subscription_required' => (int) ($settings->business_subscription ?? 0) === 1,
            'user_subscription_required' => (int) ($settings->user_subscription ?? 0) === 1,
            'support_url' => $this->clean($settings->support_url ?? null),
            'support_email' => $this->clean($settings->support_email ?? null),
            'terms_and_conditions' => $this->clean($settings->term_conditions ?? null),
        ])->save();
        $this->imported('app_settings');
    }

    private function importEmailTemplates(): void
    {
        $legacyPath = $this->option('legacy-path');
        $rows = [];

        foreach ($this->legacyRows('tbl_email_template') as $template) {
            $this->read('email_templates');
            $file = $legacyPath ? rtrim($legacyPath, '/').'/assets/template/email/'.basename((string) $template->temp_file) : null;

            if ($file === null || ! is_file($file)) {
                $this->skip('email_templates', "Email template \"{$template->temp_name}\" was skipped: its HTML file is not available (pass --legacy-path).");

                continue;
            }

            $rows[] = [
                'id' => (int) $template->id,
                'name' => Str::limit((string) $template->temp_name, 100, ''),
                'type' => $this->clean($template->temp_type),
                'subject' => (string) $template->temp_subject,
                'html' => file_get_contents($file),
                'editor_design' => $this->clean($template->temp_unlayer),
                'is_active' => (int) $template->status === 1,
                'created_at' => $this->timestamp($template->time),
                'updated_at' => $this->timestamp($template->time),
            ];
        }

        $this->insert('email_templates', $rows);
    }

    /**
     * Same rules as the seeder data: "english" becomes "en", and the newest row wins.
     */
    private function importTranslations(): void
    {
        $rows = [];

        foreach ($this->legacyRows('tbl_language') as $translation) {
            $this->read('translations');
            $locale = $translation->language === 'english' ? 'en' : (string) $translation->language;
            $key = trim((string) $translation->key);

            if ($key === '' || $locale === '') {
                $this->skip('translations', "Translation {$translation->id} has no key or language.");

                continue;
            }

            if (isset($rows[$locale."\0".$key])) {
                $this->stats['translations']['skipped']++;
            }

            $rows[$locale."\0".$key] = [
                'locale' => Str::limit($locale, 10, ''),
                'key' => $key,
                'text' => $translation->text,
                'group' => $this->clean($translation->controller),
                'created_at' => $this->timestamp($translation->createdAt),
                'updated_at' => $this->timestamp($translation->createdAt),
            ];
        }

        $this->insert('translations', array_values($rows));
    }

    private function importNotifications(): void
    {
        $rows = [];

        $owners = DB::table('businesses')->pluck('owner_id', 'id');

        foreach ($this->legacyRows('tbl_notification') as $notification) {
            $this->read('user_notifications');
            $forBusiness = trim((string) $notification->to_notify) === 'business' && isset($owners[(int) $notification->bizid]);
            // The old app showed business notifications to the business owner, whoever they were stored under.
            $userId = $forBusiness ? (int) $owners[(int) $notification->bizid] : $this->user($notification->userid);

            if ($userId === null) {
                $this->skip('user_notifications', "Notification {$notification->id} belongs to a missing user.");

                continue;
            }

            $sentAt = $this->timestamp($notification->time);
            $type = trim((string) $notification->noti_type);

            $rows[] = [
                'id' => (int) $notification->id,
                'user_id' => $userId,
                'business_id' => $this->business($notification->bizid) ? (int) $notification->bizid : null,
                'audience' => $this->clean($notification->to_notify) ?? 'user',
                'type' => $type === '' || $type === '0' ? null : Str::limit($type, 50, ''),
                'title' => Str::limit((string) $notification->title, 255, ''),
                'message' => (string) $notification->message,
                'link' => Str::limit((string) $notification->noti_link, 255, '') ?: null,
                'icon' => Str::limit((string) $notification->noti_icon, 60, '') ?: null,
                'read_at' => (int) $notification->is_read === 1 ? $sentAt : null,
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ];
        }

        $this->insert('user_notifications', $rows);
    }

    private function importNotificationPreferences(): void
    {
        $rows = [];

        foreach ($this->legacyRows('tbl_setting_notification') as $preference) {
            $this->read('notification_preferences');
            $userId = $this->user($preference->user_id);
            // The old app spelled this event "service_raiting".
            $event = trim((string) $preference->for_notify) === 'service_raiting' ? 'service_rating' : trim((string) $preference->for_notify);

            if ($userId === null || $event === '') {
                $this->skip('notification_preferences', "Notification preference {$preference->id} has a missing user or no event.");

                continue;
            }

            if (isset($rows[$userId.'-'.$event])) {
                $this->skip('notification_preferences', "Notification preference {$preference->id} repeats \"{$event}\" for user {$userId}; the newest is kept.");
            }

            $rows[$userId.'-'.$event] = [
                'id' => (int) $preference->id,
                'user_id' => $userId,
                'event' => Str::limit($event, 55, ''),
                'channel' => $this->clean($preference->for_sub),
                'is_enabled' => (int) $preference->status === 1,
                'created_at' => $this->timestamp($preference->time),
                'updated_at' => $this->timestamp($preference->time),
            ];
        }

        $this->insert('notification_preferences', array_values($rows));
    }

    private function importVisitorLogs(): void
    {
        $rows = [];

        foreach ($this->legacyRows('tbl_visitor_log') as $visit) {
            $this->read('visitor_logs');
            $visitedAt = $this->timestamp($visit->time);

            $rows[] = [
                'id' => (int) $visit->id,
                'page_type' => Str::limit((string) $visit->log_type, 30, ''),
                'page_url' => (string) $visit->page_url,
                'ip_address' => Str::limit((string) $visit->ip_address, 45, '') ?: null,
                'completed' => (int) $visit->activity === 1,
                'created_at' => $visitedAt,
                'updated_at' => $visitedAt,
            ];
        }

        $this->insert('visitor_logs', $rows);
    }

    private function legacy(): Connection
    {
        return DB::connection('legacy');
    }

    /**
     * @return LazyCollection<int, object>
     */
    private function legacyRows(string $table): LazyCollection
    {
        return $this->legacy()->table($table)->lazyById(self::CHUNK);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table($table)->insert($chunk);
        }

        $this->stats[$table] ??= ['read' => 0, 'imported' => 0, 'skipped' => 0];
        $this->stats[$table]['imported'] += count($rows);
    }

    private function read(string $table): void
    {
        $this->stats[$table] ??= ['read' => 0, 'imported' => 0, 'skipped' => 0];
        $this->stats[$table]['read']++;
    }

    private function imported(string $table): void
    {
        $this->stats[$table]['imported']++;
    }

    private function skip(string $table, string $reason): void
    {
        $this->stats[$table]['skipped']++;
        $this->note($table, $reason);
    }

    private function note(string $table, string $message): void
    {
        $this->warnings[] = "[{$table}] {$message}";
    }

    private function user(mixed $legacyId): ?int
    {
        return $this->userIds[(int) $legacyId] ?? null;
    }

    private function business(mixed $legacyId): bool
    {
        return isset($this->businessIds[(int) $legacyId]);
    }

    private function role(mixed $legacyId): ?int
    {
        return is_numeric($legacyId) && isset($this->roleIds[(int) $legacyId]) ? (int) $legacyId : null;
    }

    private function agreement(mixed $legacyId): ?int
    {
        return isset($this->agreementIds[(int) $legacyId]) ? (int) $legacyId : null;
    }

    /**
     * @param  array<string, true>  $used
     */
    private function uniqueSlug(string $slug, int $userId, array &$used): string
    {
        $slug = Str::limit($slug, 50, '');

        if ($slug === '') {
            $slug = 'user'.$userId;
            $this->note('users', "User {$userId} had no public URL name; it is now {$slug}.");
        }

        if (isset($used[Str::lower($slug)])) {
            $renamed = Str::limit($slug, 40, '').'-'.$userId;
            $this->note('users', "User {$userId} had the same public URL name as another user (\"{$slug}\"); it is now \"{$renamed}\". Printed QR codes for this user need replacing.");
            $slug = $renamed;
        }

        $used[Str::lower($slug)] = true;

        return $slug;
    }

    /**
     * Legacy percentage JSON looks like [{"3":"100"}] (business role id => percent).
     *
     * @return array<string, float>
     */
    private function roleShares(mixed $json, int $agreementId): array
    {
        $decoded = json_decode((string) $json, true);
        $shares = [];

        if (! is_array($decoded)) {
            $this->note('agreements', "Agreement {$agreementId} had unreadable tip shares; they were left empty.");

            return $shares;
        }

        array_walk_recursive($decoded, function (mixed $percent, mixed $roleId) use (&$shares): void {
            if (is_numeric($roleId) && is_numeric($percent)) {
                $shares[(string) $roleId] = (float) $percent;
            }
        });

        return $shares;
    }

    /**
     * Unix seconds or a date string from the legacy tables, as a MySQL datetime.
     */
    private function timestamp(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === '0' || $value === 0) {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value > 0 ? CarbonImmutable::createFromTimestamp((int) $value)->toDateTimeString() : null;
        }

        if (str_starts_with((string) $value, '0000-00-00')) {
            return null;
        }

        // Text dates were written with PHP date() in the old app's timezone; store them as UTC.
        try {
            return CarbonImmutable::parse((string) $value, $this->option('legacy-timezone'))->utc()->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }

    private function date(mixed $value): ?string
    {
        return $this->timestamp($value) !== null ? substr($this->timestamp($value), 0, 10) : null;
    }

    /**
     * Legacy birth dates are text in d-m-Y; 01-01-1970 was used for "not given".
     */
    private function birthDate(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '01-01-1970') {
            return null;
        }

        foreach (['d-m-Y', 'Y-m-d', 'd/m/Y', 'F j, Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function money(mixed $value): string
    {
        return number_format(round((float) $value, 2), 2, '.', '');
    }

    /**
     * The legacy tables stored the currency as "euro", "€", "EUR", "0" or "USD".
     */
    private function currency(mixed $value): string
    {
        $value = Str::upper(trim((string) $value));

        return match (true) {
            in_array($value, ['USD', '$'], true) => 'USD',
            in_array($value, ['GBP', '£'], true) => 'GBP',
            default => 'EUR',
        };
    }

    private function coordinate(mixed $value, int $limit): ?string
    {
        return is_numeric($value) && abs((float) $value) <= $limit ? number_format((float) $value, 7, '.', '') : null;
    }

    private function clean(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function report(float $seconds): void
    {
        $this->newLine();
        $this->table(
            ['Table', 'Read', 'Imported', 'Skipped'],
            collect($this->stats)->map(fn (array $counts, string $table): array => [$table, $counts['read'], $counts['imported'], $counts['skipped']])->values()->all(),
        );

        if ($this->warnings !== []) {
            $this->components->warn(count($this->warnings).' data issues were fixed or skipped:');
            foreach ($this->warnings as $warning) {
                $this->line('  '.$warning);
            }
        }

        $this->components->info(sprintf('Legacy data imported in %.1f seconds.', $seconds));
    }
}
