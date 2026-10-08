<?php $__env->startSection('style'); ?>
	<?php echo $__env->make('exports.partials.styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('body_class', 'pdf-body'); ?>

<?php $__env->startSection('content'); ?>
<main class="page">
	<?php echo $__env->make('exports.partials.header', [
		'tag' => 'Template 7 · Laporan Account Payable',
		'eyebrow' => 'Laporan',
		'title' => 'Account Payable',
		'dateLine' => 'Periode Agustus 2026 · dicetak 1 Sep 2026 oleh Ayu Kartika',
	], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

	<div class="kpis">
		<div class="kpi"><label>Perlu Dibayar</label><b>Rp 9.400.000</b></div>
		<div class="kpi"><label>Sudah Dibayar</label><b>Rp 21.300.000</b></div>
		<div class="kpi"><label>Supplier / Driver</label><b>4</b></div>
		<div class="kpi"><label>Lewat 7 Hari</label><b>2</b></div>
	</div>

	<h2>Daftar Tagihan</h2>
	<table>
		<thead><tr><th>Order</th><th>Supplier / Driver</th><th>Tipe</th><th>Tgl Order</th><th class="r">Modal</th><th>Status</th></tr></thead>
		<tbody>
			<tr><td class="mono">ORD-0101</td><td>Made Wirawan (Driver)</td><td>Transportasi</td><td>15 Agu 2026</td><td class="r">Rp 4.500.000</td><td><span class="pill bad">Belum Bayar</span></td></tr>
			<tr><td class="mono">ORD-0101</td><td>Bali Adventure Tours</td><td>Tour</td><td>15 Agu 2026</td><td class="r">Rp 3.200.000</td><td><span class="pill ok">Bayar</span></td></tr>
			<tr><td class="mono">ORD-0109</td><td>Kadek Surya (Driver)</td><td>Transportasi</td><td>18 Agu 2026</td><td class="r">Rp 2.800.000</td><td><span class="pill bad">Belum Bayar</span></td></tr>
			<tr><td class="mono">ORD-0125</td><td>Bali Zoo</td><td>Tiket</td><td>25 Agu 2026</td><td class="r">Rp 2.100.000</td><td><span class="pill ok">Bayar</span></td></tr>
			<tr><td class="mono">ORD-0118</td><td>Made Wirawan (Driver)</td><td>Layanan</td><td>20 Agu 2026</td><td class="r">Rp 2.100.000</td><td><span class="pill warn">Dibatalkan</span></td></tr>
		</tbody>
		<tfoot><tr><td colspan="4">Total</td><td class="r">Rp 14.700.000</td><td></td></tr></tfoot>
	</table>
	<p class="note mt-[12px]">Tagihan dihitung otomatis dari biaya order. Untuk menandai lunas, gunakan menu Tagihan yang Perlu Dibayarkan.</p>

	<?php echo $__env->make('exports.partials.footer', ['footer' => 'Mei Bali Ops · Account Payable'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</main>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('exports.base', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /mnt/blockstorage/quantumtri/mei-bali/api/resources/views/exports/account_payable.blade.php ENDPATH**/ ?>