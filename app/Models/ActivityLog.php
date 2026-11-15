<?php

namespace App\Models;

use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in the record of who did what on the people page, or who impersonated whom.
 */
class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The line in words, eg "Jenny MacAdmin made Olivia McUser the lead of Developers on 20 Nov 2026".
     */
    public function sentence(): string
    {
        return $this->user->full_name.' '.$this->description.' on '.$this->created_at->format('j M Y');
    }
}
