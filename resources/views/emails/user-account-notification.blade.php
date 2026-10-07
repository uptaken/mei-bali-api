@if ($event === 'created')
Halo {{ $user->nama }},

Akun Mei Bali Ops Anda telah dibuat dengan alamat email ini.
Silakan hubungi administrator untuk menerima informasi login Anda.

Jika Anda tidak mengharapkan email ini, silakan hubungi administrator Mei Bali Ops.
@else
Halo {{ $user->nama }},

Kata sandi akun Mei Bali Ops Anda telah diperbarui.

Jika Anda tidak melakukan perubahan ini, segera hubungi administrator Mei Bali Ops.
@endif