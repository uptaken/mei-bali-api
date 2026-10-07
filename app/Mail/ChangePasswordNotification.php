<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChangePasswordNotification extends Mailable
{
		use Queueable, SerializesModels;

		public function __construct(public User $user)
		{
		}

		public function envelope(): Envelope
		{
				$subject = 'Kata sandi Mei Bali Ops Anda telah diperbarui';

				return new Envelope(subject: $subject);
		}

		public function content(): Content
		{
				return new Content(view: 'emails.change_password');
		}
}