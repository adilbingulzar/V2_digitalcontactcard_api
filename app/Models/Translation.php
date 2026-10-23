<?php

namespace App\Models;

use Database\Factories\TranslationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-editable UI text. `key` is the English source text, `text` its translation in `locale`.
 */
#[Fillable(['locale', 'key', 'text', 'group'])]
class Translation extends Model
{
    /** @use HasFactory<TranslationFactory> */
    use HasFactory;

    /**
     * Locales written right to left; the frontend switches layout direction for these.
     *
     * @var list<string>
     */
    public const array RTL_LOCALES = ['ar', 'fa', 'he', 'ps', 'sd', 'ug', 'ur', 'ku'];
}
