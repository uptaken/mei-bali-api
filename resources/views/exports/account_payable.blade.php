@extends('exports.base')

@section('style')
	@include('exports.partials.styles')
@endsection

@section('body_class', 'pdf-body')

@php
	$idr = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
	$tipeLabel = ['transportasi' => 'Transportasi', 'tour' => 'Tour', 'ticket' => 'Tiket', 'addon' => 'Add-On', 'layanan' => 'Layanan'];
@endphp

@section('content')
<main class="page">
	@include('exports.partials.header', [
		'eyebrow' => 'Laporan',
		'title' => 'Account Payable',
		'dateLine' => 'Periode '.$periode.' · dicetak '.now()->locale('id')->translatedFormat('d M Y').' oleh '.$printedBy,
	])

	<div class="kpis">
		<div class="kpi"><label>Perlu Dibayar</label><b>{{ $idr($summary['perluDibayar']) }}</b></div>
		<div class="kpi"><label>Sudah Dibayar</label><b>{{ $idr($summary['sudahDibayar']) }}</b></div>
		<div class="kpi"><label>Supplier / Driver</label><b>{{ $summary['supplierCount'] }}</b></div>
		<div class="kpi"><label>Lewat 7 Hari</label><b>{{ $summary['overdueCount'] }}</b></div>
	</div>

	<h2>Daftar Tagihan</h2>
	<table>
		<thead><tr><th>Order</th><th>Supplier / Driver</th><th>Tipe</th><th>Tgl Order</th><th class="r">Modal</th><th>Status</th></tr></thead>
		<tbody>
			@forelse ($groups as $group)
				<tr>
					<td class="mono">{{ $group['kode'] }}</td>
					<td>{{ $group['supplierNama'] }}</td>
					<td>{{ $tipeLabel[$group['tipeTagihan']] ?? $group['tipeTagihan'] }}</td>
					<td>{{ $group['tanggalOrder'] ? \Carbon\Carbon::parse($group['tanggalOrder'])->locale('id')->translatedFormat('d M Y') : '-' }}</td>
					<td class="r">{{ $idr($group['modal']) }}</td>
					<td>
						@if ($group['cancelled'])
							<span class="pill warn">Dibatalkan</span>
						@else
							<span class="pill {{ $group['status'] === 'Bayar' ? 'ok' : 'bad' }}">{{ $group['status'] }}</span>
						@endif
					</td>
				</tr>
			@empty
				<tr><td colspan="6" class="note">Tidak ada tagihan pada periode ini.</td></tr>
			@endforelse
		</tbody>
		<tfoot><tr><td colspan="4">Total</td><td class="r">{{ $idr($groups->sum('modal')) }}</td><td></td></tr></tfoot>
	</table>
	<p class="note mt-[12px]">Tagihan dihitung otomatis dari biaya order. Untuk menandai lunas, gunakan menu Tagihan yang Perlu Dibayarkan.</p>

	@include('exports.partials.footer', ['footer' => 'Mei Bali Ops · Account Payable'])
</main>
@endsection
