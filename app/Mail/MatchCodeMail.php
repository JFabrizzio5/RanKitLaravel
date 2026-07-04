<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MatchCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $tournamentName;
    public $playerName;
    public $matchCode;

    /**
     * Create a new message instance.
     */
    public function __construct($tournamentName, $playerName, $matchCode)
    {
        $this->tournamentName = $tournamentName;
        $this->playerName = $playerName;
        $this->matchCode = $matchCode;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Código de Partida: ' . $this->matchCode . ' - ' . $this->tournamentName,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.match_code',
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
