<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserAccountNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $event)
    {
    }

    public function envelope(): Envelope
    {
        $subject = $this->event === 'created'
            ? 'Akun Mei Bali Ops Anda telah dibuat'
            : 'Kata sandi Mei Bali Ops Anda telah diperbarui';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(text: 'emails.user-account-notification');
    }
}