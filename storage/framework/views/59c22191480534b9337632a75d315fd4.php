<?php $__env->startSection('style'); ?>
	<?php echo $__env->make('exports.partials.styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('body_class', 'pdf-body'); ?>

<?php
	$startDate = $order->tanggal_mulai
		? \Carbon\Carbon::parse($order->tanggal_mulai)
		: null;
	$dayCount = $order->durasi_hari ?: max(1, $order->itineraryDays->count());
?>

<?php $__env->startSection('content'); ?>
	<main class="page">
		<?php echo $__env->make('exports.partials.header', [
			'tag' => 'Template 1 · Itinerary (Tour) · tanpa Modal/Biaya',
			'brand' => 'Tours',
			'eyebrow' => 'Itinerary Perjalanan',
			'title' => $order->kode,
			'dateLine' => 'Diterbitkan '.now()->locale('id')->translatedFormat('d F Y'),
		], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

		<h2>Informasi Perjalanan</h2>
		<div class="grid grid3">
			<div class="f"><label>Client</label><div><?php echo e($order->client?->nama ?? '-'); ?></div></div>
			<div class="f"><label>Nama Order</label><div><?php echo e($order->nama_order ?: '-'); ?></div></div>
			<div class="f"><label>Kode Group</label><div><?php echo e($order->kode_group ?: '-'); ?></div></div>
			<div class="f">
				<label>Tanggal Mulai</label>
				<div>
					<?php echo e($startDate?->locale('id')->translatedFormat('d F Y') ?? '-'); ?><?php echo e($order->jam_mulai ? ' · '.$order->jam_mulai : ''); ?>

				</div>
			</div>
			<div class="f"><label>Durasi</label><div><?php echo e($dayCount); ?> Hari</div></div>
			<div class="f"><label>Jumlah Peserta</label><div><?php echo e($order->dewasa ?? 0); ?> Dewasa, <?php echo e($order->anak ?? 0); ?> Anak</div></div>
		</div>

		<?php if($order->catatan): ?>
			<div class="box cream mt-3">
				<strong>Catatan untuk tamu:</strong> <?php echo e($order->catatan); ?>

			</div>
		<?php endif; ?>

		<h2>Rincian Itinerary</h2>
		<?php if($order->itineraryDays->isEmpty()): ?>
			<p class="note">Belum ada rincian itinerary.</p>
		<?php else: ?>
			<?php $__currentLoopData = $order->itineraryDays; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
				<?php
					$dayDate = $startDate?->copy()->addDays($day->hari - 1);
					$firstActivity = $day->activities->first(fn ($activity) => filled($activity->aktivitas));
				?>
				<article class="day break-inside-avoid">
					<div class="dh">
						<span>Hari <?php echo e($day->hari); ?> — <?php echo e($firstActivity?->aktivitas ?: 'Itinerary'); ?></span>
						<span><?php echo e($dayDate?->locale('id')->translatedFormat('l, d M Y') ?? '-'); ?></span>
					</div>
					<div class="db">
						<div class="grid">
							<div class="f">
								<label>Penjemputan</label>
								<div><?php echo e($day->waktu_penjemputan ?: '--:--'); ?> · <?php echo e($day->tempat_penjemputan ?: '-'); ?></div>
							</div>
							<div class="f">
								<label>Drop Akhir</label>
								<div><?php echo e($day->waktu_drop_akhir ?: '--:--'); ?> · <?php echo e($day->tempat_drop_akhir ?: '-'); ?></div>
							</div>
						</div>

						<?php if($day->activities->isNotEmpty()): ?>
							<ul class="tl">
								<?php $__currentLoopData = $day->activities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $activity): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
									<?php if(filled($activity->aktivitas)): ?>
										<li><?php echo e($activity->aktivitas); ?></li>
									<?php endif; ?>
								<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
							</ul>
						<?php else: ?>
							<p class="note mt-2">Belum ada aktivitas untuk hari ini.</p>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
		<?php endif; ?>

		<?php echo $__env->make('exports.partials.footer', ['footer' => 'Mei Bali Ops · Itinerary '.$order->kode], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
	</main>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('exports.base', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /mnt/blockstorage/quantumtri/mei-bali/api/resources/views/exports/itinerary_tour.blade.php ENDPATH**/ ?>