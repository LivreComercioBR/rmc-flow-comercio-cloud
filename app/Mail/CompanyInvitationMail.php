<?php

namespace App\Mail;

use App\Models\CompanyInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CompanyInvitation $invitation, public string $url) {}
    public function envelope(): Envelope { return new Envelope(subject: 'Convite para acessar o RMC Flow Comércio Cloud'); }
    public function content(): Content { return new Content(view: 'emails.company-invitation'); }
}
