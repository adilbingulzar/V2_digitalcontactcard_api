<?php

namespace App\Models;

use Database\Factories\VisitorLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A visit to a public tip or review page.
 */
#[Fillable(['page_type', 'page_url', 'ip_address', 'completed'])]
class VisitorLog extends Model
{
    /** @use HasFactory<VisitorLogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
        ];
    }
}
