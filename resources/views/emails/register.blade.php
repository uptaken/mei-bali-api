<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mei Bali Ops — Registrasi Berhasil</title>
<style>
	body { margin:0; background:#e9e6e2; font-family:'Plus Jakarta Sans', -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; }
	.section-label {
		max-width:520px; margin:0 auto; padding:32px 16px 0 16px;
		font-size:11px; font-weight:800; letter-spacing:1.6px; text-transform:uppercase; color:#8b8178;
	}
</style>
<script type="text/javascript" nonce="fd4818dd654b4e85bff6993111a" src="//local.adguard.org?ts=1790930847590&amp;type=content-script&amp;dmn=cdn.discordapp.com&amp;url=https%3A%2F%2Fcdn.discordapp.com%2Fattachments%2F1545338408174493756%2F1554915694850736279%2Fall-email-templates.html%3Fbackend%3Db2%26ex%3D6ac099d6%26is%3D6abf4856%26hm%3Dd2e53d786bf19114b66d31e61bac104e7c8c56233f1035b54599f49197f50490%26&amp;app=com.vivaldi.Vivaldi&amp;css=3&amp;js=1&amp;rel=1&amp;rji=1&amp;sbe=0"></script></head>
<body>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8f7f6; padding:32px 16px;">
	<tr>
		<td align="center">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #ececec;">


				<tr>
					<td style="padding:28px 32px 20px 32px;">
						<table role="presentation" cellpadding="0" cellspacing="0">
							<tr>
								<td style="width:40px; height:40px; border-radius:10px; background:linear-gradient(135deg,#fb919a,#ee727e); text-align:center; vertical-align:middle;">
									<span style="font-size:20px; line-height:40px; color:#ffffff;">&#127807;</span>
								</td>
								<td style="padding-left:10px; vertical-align:middle;">
									<span style="font-size:18px; font-weight:800; color:#2b2622; letter-spacing:-0.2px;">Mei&nbsp;Bali</span>
									<span style="margin-left:6px; font-size:10px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase; color:#8b8178;">Ops</span>
								</td>
							</tr>
						</table>
					</td>
				</tr>


				<tr>
					<td style="padding:0 32px;">
						<div style="height:1px; background-color:#ececec;"></div>
					</td>
				</tr>


				<tr>
					<td style="padding:28px 32px 8px 32px;">


<h1 style="margin:0 0 12px 0; font-size:20px; font-weight:800; color:#2b2622;">
		Selamat datang, {{ $user->nama }}! &#128075;
</h1>

<p style="margin:0 0 16px 0; font-size:14px; line-height:22px; color:#5b5349;">
		Akun Anda sebagai <strong style="color:#2b2622;">{{ $user->role }}</strong> di Mei Bali Ops sudah aktif.
		Gunakan tombol di bawah ini untuk masuk dan mulai mengelola order, client, dan operasional tur.
</p>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
	<tr>
		<td style="border-radius:10px; background:linear-gradient(135deg,#fb919a,#ee727e);">
			<a href="{{ url('/login') }}" target="_blank"
				 style="display:inline-block; padding:12px 24px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">
				Masuk ke Mei Bali Ops
			</a>
		</td>
	</tr>
</table>

<div style="background-color:#f8f7f6; border:1px solid #ececec; border-radius:10px; padding:14px 16px; margin:0 0 8px 0;">
	<p style="margin:0; font-size:12px; color:#8b8178;">Email akun</p>
	<p style="margin:2px 0 0 0; font-size:14px; font-weight:600; color:#2b2622;">{{ $user->email }}</p>
</div>

<div style="background-color:#f8f7f6; border:1px solid #ececec; border-radius:10px; padding:14px 16px; margin:0 0 8px 0;">
	<p style="margin:0; font-size:12px; color:#8b8178;">Password akun</p>
	<p style="margin:2px 0 0 0; font-size:14px; font-weight:600; color:#2b2622;">{{ $password }}</p>
</div>

<p style="margin:20px 0 0 0; font-size:13px; line-height:20px; color:#8b8178;">
		Jika tombol di atas tidak berfungsi, salin dan tempel tautan berikut ke browser Anda:<br>
		<a href="{{ url('/login') }}" style="color:#ee727e; word-break:break-all;">{{ url('/login') }}</a>
</p>



					</td>
				</tr>


				<tr>
					<td style="padding:24px 32px 28px 32px;">
						<div style="height:1px; background-color:#ececec; margin-bottom:20px;"></div>
						<p style="margin:0; font-size:12px; line-height:18px; color:#a79c92;">
							Email ini dikirim otomatis oleh sistem Mei Bali Ops. Jika Anda tidak merasa melakukan permintaan ini, abaikan saja email ini.
						</p>
						<p style="margin:8px 0 0 0; font-size:12px; color:#a79c92;">
							&copy; 2026 Mei Bali Ops. Seluruh hak cipta dilindungi.
						</p>
					</td>
				</tr>

			</table>
		</td>
	</tr>
</table>


</body>
</html>
