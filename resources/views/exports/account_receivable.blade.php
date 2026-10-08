@extends('exports.base')

@section('style')
	@include('exports.partials.styles')
@endsection

@section('body_class', 'pdf-body')

@section('content')
<main class="page">
	@include('exports.partials.header', [
		'tag' => 'Template 6 · Laporan Account Receivable',
		'eyebrow' => 'Laporan',
		'title' => 'Account Receivable',
		'dateLine' => 'Periode Agustus 2026 · dicetak 1 Sep 2026 oleh Ayu Kartika',
	])

	<div class="kpis">
		<div class="kpi"><label>Total Invoice</label><b>Rp 52.300.000</b></div>
		<div class="kpi"><label>Sudah Dibayar</label><b>Rp 31.700.000</b></div>
		<div class="kpi"><label>Sisa Piutang</label><b>Rp 20.600.000</b></div>
		<div class="kpi"><label>Jumlah Invoice</label><b>5</b></div>
	</div>

	<h2>Daftar Invoice</h2>
	<table>
		<thead><tr><th>No. Invoice</th><th>Order</th><th>Client</th><th class="r">Total</th><th class="r">Sisa</th><th>Status</th></tr></thead>
		<tbody>
			<tr><td class="mono">INV-2026-0101</td><td>ORD-0101</td><td>PT Nusantara Travel</td><td class="r">Rp 12.600.000</td><td class="r">Rp 7.600.000</td><td><span class="pill warn">Sudah Ditagihkan</span></td></tr>
			<tr><td class="mono">INV-2026-0102</td><td>ORD-0104</td><td>CV Island Trip</td><td class="r">Rp 9.800.000</td><td class="r">Rp 0</td><td><span class="pill ok">Lunas</span></td></tr>
			<tr><td class="mono">INV-2026-0103</td><td>ORD-0109</td><td>PT Nusantara Travel</td><td class="r">Rp 14.200.000</td><td class="r">Rp 14.200.000</td><td><span class="pill info">Belum Ditagihkan</span></td></tr>
			<tr><td class="mono">INV-2026-0104</td><td>ORD-0112</td><td>Bali Escape Co.</td><td class="r">Rp 8.400.000</td><td class="r">Rp 0</td><td><span class="pill ok">Lunas</span></td></tr>
			<tr><td class="mono">INV-2026-0105</td><td>ORD-0118</td><td>PT Nusantara Travel</td><td class="r">Rp 7.300.000</td><td class="r">Rp 0</td><td><span class="pill ok">Lunas</span></td></tr>
		</tbody>
		<tfoot><tr><td colspan="3">Total</td><td class="r">Rp 52.300.000</td><td class="r">Rp 21.800.000</td><td></td></tr></tfoot>
	</table>

	@include('exports.partials.footer', ['footer' => 'Mei Bali Ops · Account Receivable'])
</main>
@endsection