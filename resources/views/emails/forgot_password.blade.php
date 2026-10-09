<x-mail-layout title="Mei Bali Ops — Reset Password">
<h1 style="margin:0 0 12px 0; font-size:20px; font-weight:800; color:#2b2622;">Reset password Anda</h1>

<p style="margin:0 0 16px 0; font-size:14px; line-height:22px; color:#5b5349;">
  Halo {{ $user->nama }}, kami menerima permintaan untuk mereset password akun Mei Bali Ops Anda
  (<strong style="color:#2b2622;">{{ $user->email }}</strong>). Klik tombol di bawah untuk membuat password baru.
</p>

<x-mail-button :url="$resetUrl">Reset Password</x-mail-button>

<div style="background-color:#fce7d8; border:1px solid #f7d9c4; border-radius:10px; padding:14px 16px; margin:16px 0 8px 0;">
  <p style="margin:0; font-size:13px; line-height:19px; color:#773208;">
    Tautan ini berlaku selama <strong>{{ $expiresInMinutes }} menit</strong>. Setelah itu, minta tautan reset yang baru.
  </p>
</div>

<p style="margin:16px 0 0 0; font-size:13px; line-height:20px; color:#8b8178;">
  Bukan Anda yang meminta ini? Abaikan email ini — password Anda tidak akan berubah.
</p>
</x-mail-layout>
