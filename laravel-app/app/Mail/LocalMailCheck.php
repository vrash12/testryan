<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class LocalMailCheck extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'NestCrafters local mail check');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.local-check');
    }
}
