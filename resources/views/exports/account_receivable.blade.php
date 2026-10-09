@extends('exports.base')

@section('style')
	@include('exports.partials.styles')
@endsection

@section('body_class', 'pdf-body')

@php
	$idr = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
@endphp

@section('content')
<main class="page">
	@include('exports.partials.header', [
		'eyebrow' => 'Laporan',
		'title' => 'Account Receivable',
		'dateLine' => 'Periode '.$periode.' · dicetak '.now()->locale('id')->translatedFormat('d M Y').' oleh '.$printedBy,
	])

	<div class="kpis">
		<div class="kpi"><label>Total Invoice</label><b>{{ $idr($invoices->sum('total')) }}</b></div>
		<div class="kpi"><label>Sudah Dibayar</label><b>{{ $idr($invoices->sum(fn ($i) => $i->total - $i->sisa)) }}</b></div>
		<div class="kpi"><label>Sisa Piutang</label><b>{{ $idr($piutang) }}</b></div>
		<div class="kpi"><label>Jumlah Invoice</label><b>{{ $invoices->count() }}</b></div>
	</div>

	<h2>Daftar Invoice</h2>
	<table>
		<thead><tr><th>No. Invoice</th><th>Order</th><th>Client</th><th class="r">Total</th><th class="r">Sisa</th><th>Status</th></tr></thead>
		<tbody>
			@forelse ($invoices as $invoice)
				<tr>
					<td class="mono">{{ $invoice->nomor }}</td>
					<td>{{ $invoice->order?->kode ?? '-' }}</td>
					<td>{{ $invoice->client?->nama ?? '-' }}</td>
					<td class="r">{{ $idr($invoice->total) }}</td>
					<td class="r">{{ $idr($invoice->sisa) }}</td>
					<td>
						@if ($invoice->status->value === 'Sudah Ditagihkan' && $invoice->sisa === 0)
							<span class="pill ok">Lunas</span>
						@elseif ($invoice->status->value === 'Sudah Ditagihkan')
							<span class="pill warn">Sudah Ditagihkan</span>
						@else
							<span class="pill info">{{ $invoice->status->value }}</span>
						@endif
					</td>
				</tr>
			@empty
				<tr><td colspan="6" class="note">Tidak ada invoice pada periode ini.</td></tr>
			@endforelse
		</tbody>
		<tfoot><tr><td colspan="3">Total</td><td class="r">{{ $idr($invoices->sum('total')) }}</td><td class="r">{{ $idr($invoices->sum('sisa')) }}</td><td></td></tr></tfoot>
	</table>

	@include('exports.partials.footer', ['footer' => 'Mei Bali Ops · Account Receivable'])
</main>
@endsection
