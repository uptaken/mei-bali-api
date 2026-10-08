@extends('exports.base')

@section('style')
	@include('exports.partials.styles')
@endsection

@section('body_class', 'pdf-body')

@section('content')
<main class="page">
	@include('exports.partials.header', [
		'tag' => 'Template 4 · Invoice (Excel di aplikasi — versi PDF)',
		'brand' => 'Tours',
		'eyebrow' => 'Invoice',
		'title' => $invoice->nomor,
		'dateLine' => 'Tanggal '.$invoice->created_at->isoFormat('DD MMMM YYYY'),
	])

	<div class="grid mt-[14px]">
		<div class="box">
			<div class="f"><label>Ditagihkan kepada</label><div>{{ $invoice->client->nama }}</div></div>
			<div class="note">{{ $invoice->client->telepon }}</div>
		</div>
		<div class="box">
			<div class="grid">
				<div class="f"><label>Order</label><div class="mono">{{ $invoice->order->kode }}</div></div>
				{{-- <div class="f"><label>Jatuh Tempo</label><div>29 Agustus 2026</div></div> --}}
			</div>
		</div>
	</div>

	<h2>Rincian</h2>
	<table>
		<thead>
			<tr>
				<th>Deskripsi</th>
				<th class="r">Qty</th>
				<th class="r">Harga Satuan</th>
				<th class="r">Jumlah</th>
			</tr>
		</thead>
		<tbody>
			@php
				$total = 0;
			@endphp
			@foreach($invoice->lines as $line)
				<tr>
					<td>{{ $line->deskripsi }}</td>
					<td class="r">{{ $line->qty }}</td>
					<td class="r">Rp {{ number_format($line->harga_jual, 0, ',', '.') }}</td>
					<td class="r">Rp {{ number_format($line->qty * $line->harga_jual, 0, ',', '.') }}</td>
				</tr>

				@php
					$total += $line->qty * $line->harga_jual;
				@endphp
			@endforeach
		</tbody>
		<tfoot>
			<tr>
				<td colspan="3" class="r">Total</td>
				<td class="r">Rp {{ number_format($total, 0, ',', '.') }}</td>
			</tr>
		</tfoot>
	</table>
	<div class="mt-2 flex justify-end">
		<table class="w-[55%]">
			<tbody>
				<tr><td>Sudah Dibayar</td><td class="r">Rp {{ number_format($invoice->total - $invoice->sisa, 0, ',', '.') }}</td></tr>
				<tr><td><strong>Sisa Tagihan</strong></td><td class="r"><strong>Rp {{ number_format($invoice->sisa, 0, ',', '.') }}</strong></td></tr>
			</tbody>
		</table>
	</div>

	<h2>Pembayaran</h2>
	<div class="box">Transfer ke <strong>Bank BCA 000-000-0000</strong> a.n. PT Mei Bali Wisata. Cantumkan nomor invoice pada berita transfer.</div>
	<div class="sign">
		<div>Hormat kami,<br><br><br>{{ $invoice->order->createdBy->nama }}</div>
		<div>Diterima oleh,<br><br><br>(__________________)</div>
	</div>

	@include('exports.partials.footer', ['footer' => 'Mei Bali Ops · '.$invoice->nomor])
</main>
@endsection