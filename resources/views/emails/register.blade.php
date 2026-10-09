<x-mail-layout title="Mei Bali Ops — Registrasi Berhasil">
<h1 style="margin:0 0 12px 0; font-size:20px; font-weight:800; color:#2b2622;">Selamat datang, {{ $user->nama }}! &#128075;</h1>

<p style="margin:0 0 16px 0; font-size:14px; line-height:22px; color:#5b5349;">
  Akun Anda sebagai <strong style="color:#2b2622;">{{ $user->role->value }}</strong> di Mei Bali Ops sudah aktif.
  Setelah membuat kata sandi, Anda dapat masuk di {{ $loginUrl }} dan mulai mengelola order, client, dan operasional tur.
</p>

<x-mail-field label="Email akun">{{ $user->email }}</x-mail-field>
<p style="margin:12px 0 0 0; font-size:13px; line-height:20px; color:#5b5349;">
  Buat kata sandi Anda sendiri lewat tombol di bawah. Tautan ini berlaku {{ $expiresInMinutes }} menit; jika sudah kedaluwarsa,
  pilih "Lupa kata sandi?" di halaman masuk.
</p>

<x-mail-button :url="$setPasswordUrl">Buat Kata Sandi</x-mail-button>
</x-mail-layout>
