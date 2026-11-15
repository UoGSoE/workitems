<?php

namespace App\Mail;

use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkItemsMovedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $whatHappened  as the reader would put it, eg "moved all of your open work to Jenny MacAdmin"
     * @param  Collection<int, WorkItem>  $workItems
     */
    public function __construct(
        public User $who,
        public string $whatHappened,
        public Collection $workItems,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '['.config('app.name').'] '.$this->who->full_name.' moved some work items',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.work-items-moved',
        );
    }
}
