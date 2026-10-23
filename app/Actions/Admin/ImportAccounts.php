<?php

namespace App\Actions\Admin;

use App\Actions\Auth\RegisterUser;
use App\Enums\AccountType;
use App\Enums\EmployeeStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Mail\AccountInviteMail;
use App\Models\Business;
use App\Models\Employee;
use App\Models\User;
use App\Models\Vcard;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Creates accounts from a CSV file (the old "Auto signup"): one row per person,
 * each gets a business card and, optionally, joins a business. Instead of the
 * old password (their phone number), everyone gets an email to choose one.
 */
class ImportAccounts
{
    /**
     * Columns of the CSV file, in the order of the old spreadsheet.
     */
    public const array COLUMNS = [
        'first_name', 'last_name', 'title', 'email', 'phone', 'social_1', 'social_2', 'social_3', 'social_4',
        'appointment_url', 'ticket_url', 'address', 'latitude', 'longitude',
    ];

    /**
     * @param  array{country_code: string, country: string, theme: int, business_id?: ?int, message?: ?string}  $options
     * @return array{created: list<string>, skipped: list<string>}
     */
    public function handle(UploadedFile $file, array $options): array
    {
        $business = isset($options['business_id']) ? Business::find($options['business_id']) : null;
        $result = ['created' => [], 'skipped' => []];

        $handle = fopen($file->getRealPath(), 'r');
        $header = array_map(fn (?string $column): string => Str::snake(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $column))), fgetcsv($handle, escape: '\\') ?: []);
        $line = 1;

        while (($values = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;
            if ($values === [null] || array_filter($values, fn (?string $value): bool => trim((string) $value) !== '') === []) {
                continue;
            }

            $row = array_map(fn (?string $value): string => trim((string) $value), array_combine(
                array_slice($header, 0, count($values)),
                array_slice($values, 0, count($header)),
            ) ?: []);

            $problem = $this->problem($row);
            if ($problem !== null) {
                $result['skipped'][] = "Line {$line}: {$problem}";

                continue;
            }

            $user = $this->create($row, $options, $business);
            $result['created'][] = $user->email;
        }
        fclose($handle);

        return $result;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function problem(array $row): ?string
    {
        $validator = Validator::make($row, [
            'first_name' => ['required', 'max:100'],
            'last_name' => ['nullable', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'regex:/^[\d\s]{6,20}$/'],
            'appointment_url' => ['nullable', 'url:http,https'],
            'ticket_url' => ['nullable', 'url:http,https'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        return User::withTrashed()->where('email', Str::lower($row['email']))->exists()
            ? $row['email'].' already has an account.'
            : null;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array{country_code: string, country: string, theme: int, business_id?: ?int, message?: ?string}  $options
     */
    private function create(array $row, array $options, ?Business $business): User
    {
        $user = DB::transaction(function () use ($row, $options, $business): User {
            $user = new User([
                'name' => trim($row['first_name'].' '.($row['last_name'] ?? '')),
                'email' => Str::lower($row['email']),
                'password' => Str::password(40),
                'account_type' => AccountType::Private,
                'phone' => preg_replace('/\D/', '', $row['phone'] ?? '') ?: null,
                'country_code' => ltrim($options['country_code'], '+0'),
                'country' => $options['country'],
                'slug' => 'pending-'.Str::random(20),
            ]);
            $user->forceFill(['role' => UserRole::User, 'status' => UserStatus::Active, 'email_verified_at' => now()])->save();
            $user->forceFill(['slug' => RegisterUser::slugFor($row['first_name'], $user->id)])->save();

            if ($business !== null) {
                $employee = new Employee(['user_id' => $user->id, 'agreement_id' => $business->default_agreement_id, 'requested_by' => 'business']);
                $employee->business()->associate($business);
                $employee->forceFill(['status' => EmployeeStatus::Accepted])->save();
            }

            $card = new Vcard([
                'business_name' => $business?->name ?? $user->name,
                'business_title' => ($row['title'] ?? '') ?: null,
                'business_email' => $user->email,
                'business_address' => ($row['address'] ?? '') ?: null,
                'latitude' => ($row['latitude'] ?? '') ?: null,
                'longitude' => ($row['longitude'] ?? '') ?: null,
                'theme' => $options['theme'],
                'social_links' => array_values(array_filter(
                    [$row['social_1'] ?? '', $row['social_2'] ?? '', $row['social_3'] ?? '', $row['social_4'] ?? ''],
                    fn (string $link): bool => preg_match('#^https?://\S+$#i', $link) === 1,
                )),
                'appointment_url' => ($row['appointment_url'] ?? '') ?: null,
                'ambassador_url' => ($row['ticket_url'] ?? '') ?: null,
                'show_location' => filled($row['address'] ?? null),
                'show_opening_hours' => false,
            ]);
            $card->user()->associate($user)->save();

            return $user;
        });

        $link = config('tiqs.frontend_url').'/reset-password?'.http_build_query(['token' => Password::broker()->createToken($user), 'email' => $user->email]);
        Mail::to($user)->send(new AccountInviteMail($user->name, $business?->name, $link, $options['message'] ?? null));

        return $user;
    }
}
