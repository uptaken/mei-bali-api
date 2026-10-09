<header class="hd">
	<div>
		<div class="logo">
			<div class="mark" aria-hidden="true">
				<svg class="size-4" viewBox="0 0 24 24" fill="none">
					<path d="M12 3c0 6-2 9-6 10m6-10c0 6 2 9 6 10M6 13c2 2 4 3 6 3s6-1 6-3M4 17c2 1 5 2 8 2s6-1 8-2" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</div>
			<div class="word">Mei&nbsp;Bali<small><?php echo e($brand ?? 'Ops'); ?></small></div>
		</div>
		<div class="co">
			<?php if(($brand ?? 'Ops') === 'Tours'): ?>
				<?php echo e(implode(' · ', array_filter([config('company.name'), config('company.address')]))); ?><br>
				<?php echo e(implode(' · ', array_filter([config('company.email'), config('company.phone')]))); ?>

			<?php else: ?>
				Laporan Keuangan Internal
			<?php endif; ?>
		</div>
	</div>
	<div class="doc">
		<div class="t"><?php echo e($eyebrow); ?></div>
		<div class="n"><?php echo e($title); ?></div>
		<div class="s"><?php echo e($dateLine); ?></div>
	</div>
</header>
<?php /**PATH /mnt/blockstorage/quantumtri/mei-bali/api/resources/views/exports/partials/header.blade.php ENDPATH**/ ?>