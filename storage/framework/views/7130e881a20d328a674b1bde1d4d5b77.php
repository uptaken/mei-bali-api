<?php $__env->startSection('style'); ?>
	<?php echo $__env->make('exports.partials.styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('body_class', 'pdf-body'); ?>

<?php
	$idr = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
	$tipeLabel = ['transportasi' => 'Transportasi', 'tour' => 'Tour', 'ticket' => 'Tiket', 'addon' => 'Add-On', 'layanan' => 'Layanan'];
?>

<?php $__env->startSection('content'); ?>
<main class="page">
	<?php echo $__env->make('exports.partials.header', [
		'eyebrow' => 'Laporan',
		'title' => 'Account Payable',
		'dateLine' => 'Periode '.$periode.' · dicetak '.now()->locale('id')->translatedFormat('d M Y').' oleh '.$printedBy,
	], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

	<div class="kpis">
		<div class="kpi"><label>Perlu Dibayar</label><b><?php echo e($idr($summary['perluDibayar'])); ?></b></div>
		<div class="kpi"><label>Sudah Dibayar</label><b><?php echo e($idr($summary['sudahDibayar'])); ?></b></div>
		<div class="kpi"><label>Supplier / Driver</label><b><?php echo e($summary['supplierCount']); ?></b></div>
		<div class="kpi"><label>Lewat 7 Hari</label><b><?php echo e($summary['overdueCount']); ?></b></div>
	</div>

	<h2>Daftar Tagihan</h2>
	<table>
		<thead><tr><th>Order</th><th>Supplier / Driver</th><th>Tipe</th><th>Tgl Order</th><th class="r">Modal</th><th>Status</th></tr></thead>
		<tbody>
			<?php $__empty_1 = true; $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
				<tr>
					<td class="mono"><?php echo e($group['kode']); ?></td>
					<td><?php echo e($group['supplierNama']); ?></td>
					<td><?php echo e($tipeLabel[$group['tipeTagihan']] ?? $group['tipeTagihan']); ?></td>
					<td><?php echo e($group['tanggalOrder'] ? \Carbon\Carbon::parse($group['tanggalOrder'])->locale('id')->translatedFormat('d M Y') : '-'); ?></td>
					<td class="r"><?php echo e($idr($group['modal'])); ?></td>
					<td>
						<?php if($group['cancelled']): ?>
							<span class="pill warn">Dibatalkan</span>
						<?php else: ?>
							<span class="pill <?php echo e($group['status'] === 'Bayar' ? 'ok' : 'bad'); ?>"><?php echo e($group['status']); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
				<tr><td colspan="6" class="note">Tidak ada tagihan pada periode ini.</td></tr>
			<?php endif; ?>
		</tbody>
		<tfoot><tr><td colspan="4">Total</td><td class="r"><?php echo e($idr($groups->sum('modal'))); ?></td><td></td></tr></tfoot>
	</table>
	<p class="note mt-[12px]">Tagihan dihitung otomatis dari biaya order. Untuk menandai lunas, gunakan menu Tagihan yang Perlu Dibayarkan.</p>

	<?php echo $__env->make('exports.partials.footer', ['footer' => 'Mei Bali Ops · Account Payable'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</main>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('exports.base', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /mnt/blockstorage/quantumtri/mei-bali/api/resources/views/exports/account_payable.blade.php ENDPATH**/ ?>