<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['issue_id', 'user_id', 'body', 'attachments'])]
class IssueComment extends Model
{
    protected function casts(): array
    {
        return ['attachments' => 'array'];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
