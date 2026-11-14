<?php

namespace App\Mail;

use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TakenOverWorkItemMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public WorkItem $workItem,
        public User $newOwner,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('app.name').'] '.$this->newOwner->full_name.' took over a work item',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.taken-over-work-item',
        );
    }
}
