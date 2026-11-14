<?php

namespace App\Mail;

use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AddedToWorkItemMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WorkItem $workItem,
        public User $addedBy,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('app.name').'] '.$this->addedBy->full_name.' added you to a work item',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.added-to-work-item',
        );
    }
}
