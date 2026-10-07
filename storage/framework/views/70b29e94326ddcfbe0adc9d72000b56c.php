<?php if($event === 'created'): ?>
Halo <?php echo e($user->nama); ?>,

Akun Mei Bali Ops Anda telah dibuat dengan alamat email ini.
Silakan hubungi administrator untuk menerima informasi login Anda.

Jika Anda tidak mengharapkan email ini, silakan hubungi administrator Mei Bali Ops.
<?php else: ?>
Halo <?php echo e($user->nama); ?>,

Kata sandi akun Mei Bali Ops Anda telah diperbarui.

Jika Anda tidak melakukan perubahan ini, segera hubungi administrator Mei Bali Ops.
<?php endif; ?><?php /**PATH /Users/joshuabuwono/project/mei-bali/backend/resources/views/emails/user-account-notification.blade.php ENDPATH**/ ?>