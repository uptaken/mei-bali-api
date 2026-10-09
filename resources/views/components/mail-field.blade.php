@props(['label'])
<div style="background-color:#f8f7f6; border:1px solid #ececec; border-radius:10px; padding:14px 16px; margin:0 0 8px 0;">
  <p style="margin:0; font-size:12px; color:#8b8178;">{{ $label }}</p>
  <p style="margin:2px 0 0 0; font-size:14px; font-weight:600; color:#2b2622;">{{ $slot }}</p>
</div>
