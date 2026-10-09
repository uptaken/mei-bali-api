<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationNotification extends Mailable
{
		use Queueable, SerializesModels;

		public function __construct(public User $user, public string $setPasswordUrl, public int $expiresInMinutes)
		{
		}

		public function envelope(): Envelope
		{
				$subject = 'Registrasi Berhasil';

				return new Envelope(subject: $subject);
		}

		public function content(): Content
		{
				return new Content(view: 'emails.register', with: ['loginUrl' => config('app.frontend_url').'/login']);
		}
}