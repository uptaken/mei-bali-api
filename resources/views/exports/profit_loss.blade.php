@extends('exports.base')

@section('style')
	@include('exports.partials.styles')
@endsection

@section('body_class', 'pdf-body')

@section('content')
<main class="page">
	@include('exports.partials.header', [
		'tag' => 'Template 5 · Laporan Profit & Loss',
		'eyebrow' => 'Laporan',
		'title' => 'Profit & Loss',
		'dateLine' => 'Jun 2026 s/d Agu 2026 · dicetak 1 Sep 2026 oleh Ayu Kartika',
	])

	<div class="kpis">
		<div class="kpi"><label>Pendapatan</label><b>Rp 98.400.000</b></div>
		<div class="kpi"><label>Modal</label><b>Rp 61.200.000</b></div>
		<div class="kpi"><label>Laba Bersih</label><b>Rp 37.200.000</b></div>
		<div class="kpi"><label>Margin</label><b>37,8%</b></div>
	</div>

	<h2>Ringkasan per Bulan</h2>
	<table>
		<thead><tr><th>Bulan</th><th class="r">Pendapatan</th><th class="r">Modal</th><th class="r">Laba Bersih</th><th class="r">Margin</th><th>Status</th></tr></thead>
		<tbody>
			<tr><td>Juni 2026</td><td class="r">Rp 28.600.000</td><td class="r">Rp 18.900.000</td><td class="r">Rp 9.700.000</td><td class="r">33,9%</td><td><span class="pill ok">Untung</span></td></tr>
			<tr><td>Juli 2026</td><td class="r">Rp 31.800.000</td><td class="r">Rp 19.100.000</td><td class="r">Rp 12.700.000</td><td class="r">39,9%</td><td><span class="pill ok">Untung</span></td></tr>
			<tr><td>Agustus 2026</td><td class="r">Rp 38.000.000</td><td class="r">Rp 23.200.000</td><td class="r">Rp 14.800.000</td><td class="r">38,9%</td><td><span class="pill ok">Untung</span></td></tr>
		</tbody>
		<tfoot><tr><td>Total</td><td class="r">Rp 98.400.000</td><td class="r">Rp 61.200.000</td><td class="r">Rp 37.200.000</td><td class="r">37,8%</td><td></td></tr></tfoot>
	</table>
	<p class="note mt-[12px]">Pendapatan dihitung dari invoice yang sudah dibuat; Modal dari biaya order terkait. Order yang dibatalkan tidak dihitung.</p>

	@include('exports.partials.footer', ['footer' => 'Mei Bali Ops · Profit & Loss'])
</main>
@endsection