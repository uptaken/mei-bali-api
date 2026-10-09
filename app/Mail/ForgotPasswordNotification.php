<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $resetUrl, public int $expiresInMinutes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset password akun Mei Bali Ops Anda');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.forgot_password');
    }
}
