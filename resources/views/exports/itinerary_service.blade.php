@extends('exports.base')

@section('style')
	@include('exports.partials.styles')
@endsection

@section('body_class', 'pdf-body')

@php
	$detail = $order->layananDetail;
	$subTipe = ['check_in' => 'Check In', 'check_out' => 'Check Out', 'transfer' => 'Transfer'][$order->sub_tipe?->value] ?? 'Layanan';
	$vehicle = $order->assignments->first()?->vehicle;
	$startDate = $order->tanggal_mulai ? \Carbon\Carbon::parse($order->tanggal_mulai) : null;
	$pickupTime = $detail?->jam_jemput ?: $order->jam_mulai;
	$rows = array_filter([
		'Pick Up Point' => $detail?->pick_up_point,
		'Drop Off Point' => $detail?->drop_off_point,
		'Hotel / Drop Off' => $detail?->hotel_drop_off,
		'Info Flight' => $detail?->info_flight,
		'Kendaraan' => $vehicle ? $vehicle->nama.' + Driver' : null,
	], 'filled');
@endphp

@section('content')
<main class="page">
	@include('exports.partials.header', [
		'brand' => 'Tours',
		'eyebrow' => 'Konfirmasi Layanan · '.$subTipe,
		'title' => $order->kode,
		'dateLine' => 'Diterbitkan '.now()->locale('id')->translatedFormat('d F Y'),
	])

	<h2>Informasi Umum</h2>
	<div class="grid grid3">
		<div class="f"><label>Client</label><div>{{ $order->client?->nama ?? '-' }}</div></div>
		<div class="f"><label>Nama Tamu</label><div>{{ $detail?->nama_tamu ?: '-' }}</div></div>
		<div class="f"><label>Jumlah Pax</label><div>{{ $detail?->jumlah_pax ?: $order->dewasa + $order->anak }} pax</div></div>
		<div class="f"><label>Tanggal</label><div>{{ $startDate?->locale('id')->translatedFormat('d F Y') ?? '-' }}</div></div>
		<div class="f"><label>Jam Jemput</label><div>{{ $pickupTime ?: '-' }}</div></div>
		<div class="f"><label>Kota</label><div>{{ $order->kota ?: implode(', ', $order->kota_termasuk ?? []) ?: '-' }}</div></div>
	</div>

	<h2>Detail Layanan</h2>
	<table>
		<tbody>
			@forelse ($rows as $label => $value)
				<tr>
					<td class="w-[36%]"><strong>{{ $label }}</strong></td>
					<td>{{ $value }}</td>
				</tr>
			@empty
				<tr><td class="note">Belum ada detail layanan.</td></tr>
			@endforelse
		</tbody>
	</table>

	@if ($order->catatan || config('company.phone'))
		<div class="box cream mt-[14px]">
			@if ($order->catatan)
				<strong>Catatan:</strong> {{ $order->catatan }}<br>
			@endif
			@if (config('company.phone'))
				Hubungi {{ config('company.phone') }} bila ada perubahan jadwal.
			@endif
		</div>
	@endif

	@include('exports.partials.footer', ['footer' => 'Mei Bali Ops · Layanan '.$order->kode])
</main>
@endsection
