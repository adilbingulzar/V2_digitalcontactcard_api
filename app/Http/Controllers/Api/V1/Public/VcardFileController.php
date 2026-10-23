<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Vcards\BuildVcf;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Wallet\BusinessCard;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class VcardFileController extends Controller
{
    /**
     * "Save card": the card as a contact file for the phone's address book.
     */
    public function show(User $user, BuildVcf $buildVcf): Response
    {
        abort_unless($user->vcard?->is_active, 404);

        return response($buildVcf->handle(BusinessCard::for($user, $user->vcard, $user->vcardTheme)), 200, [
            'Content-Type' => 'text/vcard; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.(Str::slug($user->name) ?: 'contact').'.vcf"',
        ]);
    }
}
