<?php

namespace App\Mail;

use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TakenOffWorkItemMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $whatHappened  as the reader would put it, eg "took you off"
     */
    public function __construct(
        public WorkItem $workItem,
        public User $who,
        public string $whatHappened,
        public ?string $theirMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('app.name').'] '.$this->who->full_name.' '.$this->whatHappened.' a work item',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.taken-off-work-item',
        );
    }
}
