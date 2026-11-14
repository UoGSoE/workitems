<?php

namespace App\Models;

use Database\Factories\WorkItemHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in the story of a work item: who did what, and when.
 */
class WorkItemHistory extends Model
{
    /** @use HasFactory<WorkItemHistoryFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'description',
    ];

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The line in words, eg "Jenny MacAdmin parked it on 9 Nov 2026".
     */
    public function sentence(): string
    {
        return $this->user->full_name.' '.$this->description.' on '.$this->created_at->format('j M Y');
    }
}
