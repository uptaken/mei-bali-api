@extends('exports.base')

@section('style')
	@include('exports.partials.styles')
@endsection

@section('body_class', 'pdf-body')

@section('content')
<main class="page">
	@include('exports.partials.header', [
		'tag' => 'Template 2 · Itinerary (Check In / Check Out / Transfer)',
		'brand' => 'Tours',
		'eyebrow' => 'Konfirmasi Layanan · Transfer',
		'title' => $order->kode,
		'dateLine' => 'Diterbitkan '.$order->created_at->isoFormat('DD MMMM YYYY'),
	])

	<h2>Informasi Umum</h2>
	<div class="grid grid3">
		<div class="f"><label>Client</label><div>{{ $order->client->nama }}</div></div>
		<div class="f"><label>Nama Tamu</label><div>{{ !empty($order->layananDetail) ? $order->layananDetail->nama_tamu : '-' }}</div></div>
		<div class="f"><label>Jumlah Pax</label><div>{{ $order->dewasa + $order->anak }} pax</div></div>
		<div class="f"><label>Tanggal</label><div>{{ \Carbon\Carbon::parse($order->tanggal_mulai)->isoFormat('DD MMMM YYYY') }}</div></div>
		<div class="f"><label>Jam Jemput</label><div>{{ \Carbon\Carbon::parse($order->jam_mulai)->isoFormat('HH:mm') }}</div></div>
		<div class="f"><label>Kota</label><div>{{ !empty($order->kota) ? $order->kota : implode(',', json_decode($order->kota_termasuk, true)) }}</div></div>
	</div>

	<h2>Detail Layanan</h2>
	<table>
		<tbody>
			@if(!empty($order->layananDetail) && !empty($order->layananDetail->pick_up_point))
				<tr>
					<td class="w-[36%]"><strong>Pick Up Point</strong></td>
					<td>{{ $order->layananDetail->pick_up_point }}</td>
				</tr>
			@endif
			@if(!empty($order->layananDetail) && !empty($order->layananDetail->drop_off_point))
				<tr>
					<td><strong>Drop Off Point</strong></td>
					<td>The Apurva Kempinski, Nusa Dua</td>
				</tr>
			@endif
			@if(!empty($order->layananDetail) && !empty($order->layananDetail->info_flight))
				<tr>
					<td><strong>Info Flight</strong></td>
					<td>SQ 938 · Terminal Internasional</td>
				</tr>
			@endif
		</tbody>
	</table>
	<div class="box cream mt-[14px]">
		<strong>Catatan:</strong> Driver akan menunggu di area kedatangan membawa papan nama. Hubungi +62 361 000 000 bila ada perubahan jadwal.
	</div>

	@include('exports.partials.footer', ['footer' => 'Mei Bali Ops · Layanan '.$order->kode])
</main>
@endsection