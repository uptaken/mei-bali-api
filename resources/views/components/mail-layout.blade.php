@props(['title' => 'Mei Bali Ops'])
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f8f7f6; font-family:'Plus Jakarta Sans', -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8f7f6; padding:32px 16px;">
  <tr>
    <td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #ececec;">

        {{-- Logo lockup --}}
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
          <td style="padding:0 32px;"><div style="height:1px; background-color:#ececec;"></div></td>
        </tr>

        {{-- Body --}}
        <tr>
          <td style="padding:28px 32px 8px 32px;">
            {{ $slot }}
          </td>
        </tr>

        {{-- Footer --}}
        <tr>
          <td style="padding:24px 32px 28px 32px;">
            <div style="height:1px; background-color:#ececec; margin-bottom:20px;"></div>
            <p style="margin:0; font-size:12px; line-height:18px; color:#a79c92;">
              Email ini dikirim otomatis oleh sistem Mei Bali Ops. Jika Anda tidak merasa melakukan permintaan ini, abaikan saja email ini.
            </p>
            <p style="margin:8px 0 0 0; font-size:12px; color:#a79c92;">&copy; {{ date('Y') }} Mei Bali Ops. Seluruh hak cipta dilindungi.</p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>
</body>
</html>
