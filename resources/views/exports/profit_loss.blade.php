@extends('exports.base')

@section('style')
	@include('exports.partials.styles')
@endsection

@section('body_class', 'pdf-body')

@php
	$idr = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
	$margin = fn ($laba, $pendapatan) => $pendapatan > 0 ? number_format($laba / $pendapatan * 100, 1, ',', '.').'%' : '—';
	$pendapatan = array_sum(array_column($months, 'pendapatan'));
	$modal = array_sum(array_column($months, 'modal'));
	$laba = $pendapatan - $modal;
@endphp

@section('content')
<main class="page">
	@include('exports.partials.header', [
		'eyebrow' => 'Laporan',
		'title' => 'Profit & Loss',
		'dateLine' => $periode.' · dicetak '.now()->locale('id')->translatedFormat('d M Y').' oleh '.$printedBy,
	])

	<div class="kpis">
		<div class="kpi"><label>Pendapatan</label><b>{{ $idr($pendapatan) }}</b></div>
		<div class="kpi"><label>Modal</label><b>{{ $idr($modal) }}</b></div>
		<div class="kpi"><label>Laba Bersih</label><b>{{ $idr($laba) }}</b></div>
		<div class="kpi"><label>Margin</label><b>{{ $margin($laba, $pendapatan) }}</b></div>
	</div>

	<h2>Ringkasan per Bulan</h2>
	<table>
		<thead><tr><th>Bulan</th><th class="r">Pendapatan</th><th class="r">Modal</th><th class="r">Laba Bersih</th><th class="r">Margin</th><th>Status</th></tr></thead>
		<tbody>
			@foreach ($months as $month)
				<tr>
					<td>{{ $month['label'] }}</td>
					<td class="r">{{ $idr($month['pendapatan']) }}</td>
					<td class="r">{{ $idr($month['modal']) }}</td>
					<td class="r">{{ $idr($month['laba']) }}</td>
					<td class="r">{{ $margin($month['laba'], $month['pendapatan']) }}</td>
					<td>
						<span class="pill {{ $month['laba'] > 0 ? 'ok' : ($month['laba'] < 0 ? 'bad' : 'info') }}">{{ $month['laba'] > 0 ? 'Untung' : ($month['laba'] < 0 ? 'Rugi' : 'Impas') }}</span>
					</td>
				</tr>
			@endforeach
		</tbody>
		<tfoot><tr><td>Total</td><td class="r">{{ $idr($pendapatan) }}</td><td class="r">{{ $idr($modal) }}</td><td class="r">{{ $idr($laba) }}</td><td class="r">{{ $margin($laba, $pendapatan) }}</td><td></td></tr></tfoot>
	</table>
	<p class="note mt-[12px]">Pendapatan dihitung dari invoice yang sudah dibuat; Modal dari biaya order terkait. Order yang dibatalkan tidak dihitung.</p>

	@include('exports.partials.footer', ['footer' => 'Mei Bali Ops · Profit & Loss'])
</main>
@endsection
