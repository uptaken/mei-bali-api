<?php

namespace App\Support;

use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Kirim email pemberitahuan tanpa menggagalkan aksi utama: bila SMTP bermasalah, kegagalan hanya dicatat di log. */
class Notify
{
    public static function mail(string $to, Mailable $mailable): void
    {
        try {
            Mail::to($to)->send($mailable);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
