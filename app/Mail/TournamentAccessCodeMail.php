<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TournamentAccessCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $tournamentName;
    public $playerName;
    public $accessCode;

    /**
     * Create a new message instance.
     */
    public function __construct($tournamentName, $playerName, $accessCode)
    {
        $this->tournamentName = $tournamentName;
        $this->playerName = $playerName;
        $this->accessCode = $accessCode;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu Código de Acceso - ' . $this->tournamentName,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.tournament_access_code',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
