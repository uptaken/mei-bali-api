@props(['url'])
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
  <tr>
    <td style="border-radius:10px; background:linear-gradient(135deg,#fb919a,#ee727e);">
      <a href="{{ $url }}" target="_blank" style="display:inline-block; padding:12px 24px; font-size:14px; font-weight:700; color:#ffffff; text-decoration:none;">
        {{ $slot }}
      </a>
    </td>
  </tr>
</table>
<p style="margin:0 0 8px 0; font-size:13px; line-height:20px; color:#8b8178;">
  Jika tombol di atas tidak berfungsi, salin dan tempel tautan berikut ke browser Anda:<br>
  <a href="{{ $url }}" style="color:#ee727e; word-break:break-all;">{{ $url }}</a>
</p>
