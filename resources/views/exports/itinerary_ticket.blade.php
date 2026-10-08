@extends('exports.base')

@section('style')
	@include('exports.partials.styles')
@endsection

@section('body_class', 'pdf-body')

@section('content')
<main class="page">
	@include('exports.partials.header', [
		'tag' => 'Template 3 · Itinerary (Ticket)',
		'brand' => 'Tours',
		'eyebrow' => 'Ringkasan Tiket',
		'title' => $order->kode,
		'dateLine' => 'Diterbitkan '.$order->created_at->isoFormat('DD MMMM YYYY'),
	])

	<h2>Informasi Umum</h2>
	<div class="grid grid3">
		<div class="f"><label>Client</label><div>{{ $order->client->nama }}</div></div>
		<div class="f"><label>Nama Order</label><div>{{ $order->nama_order }}</div></div>
		<div class="f"><label>Tanggal Pemakaian</label><div>{{ \Carbon\Carbon::parse($order->tanggal_mulai)->isoFormat('DD MMMM YYYY') }}</div></div>
	</div>

	<h2>Rincian Tiket</h2>
	<table>
		<thead><tr><th>Nama Tiket</th><th class="r">Jumlah</th></tr></thead>
		<tbody>
			@php
				$total = 0;
			@endphp
			@foreach($order->ticketRows as $ticket)
				<tr>
					<td>{{ $ticket->nama_tiket }}</td>
					<td class="r">{{ $ticket->qty }}</td>
				</tr>
				@php
					$total += $ticket->qty;
				@endphp
			@endforeach
		</tbody>
		<tfoot><tr><td>Total Tiket</td><td class="r">{{ $total }}</td></tr></tfoot>
	</table>

	<h2>Nama Tamu</h2>
	<table>
		<thead><tr><th>#</th><th>Nama</th><th>Kategori</th></tr></thead>
		<tbody>
			@foreach($order->guests as $key => $guest)
				<tr>
					<td>{{ $key + 1 }}</td>
					<td>{{ $guest->nama }}</td>
					<td>{{ $guest->kategori }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>

	@include('exports.partials.footer', ['footer' => 'Mei Bali Ops · Tiket '.$order->kode])
</main>
@endsection