<?php

namespace App\Mail;

use App\Models\Tiket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketVerificationAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $ticket;

    public $subKategori;

    // Create a new message instance
    public function __construct(Tiket $ticket, $subKategori = null)
    {
        $this->ticket = $ticket;
        $this->subKategori = $subKategori;
    }

    // Get the message envelope properties header definitions
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[S2P] Pengesahan Kategori Diperlukan: Tiket #'.$this->ticket->id_tiket,
        );
    }

    // Get the message content definition template paths
    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket_verification',
            with: [
                'subKategori' => $this->subKategori,
            ]
        );
    }
}
