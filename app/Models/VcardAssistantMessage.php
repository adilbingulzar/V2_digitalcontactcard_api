<?php

namespace App\Models;

use Database\Factories\VcardAssistantMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in the chat with the card builder's AI assistant.
 */
#[Fillable(['user_id', 'role', 'content', 'attachment', 'changes', 'provider', 'model', 'input_tokens', 'output_tokens'])]
class VcardAssistantMessage extends Model
{
    /** @use HasFactory<VcardAssistantMessageFactory> */
    use HasFactory;

    public const string ROLE_USER = 'user';

    public const string ROLE_ASSISTANT = 'assistant';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachment' => 'array',
            'changes' => 'array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
