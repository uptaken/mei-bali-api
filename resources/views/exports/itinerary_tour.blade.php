@extends('exports.base')

@section('style')
	@include('exports.partials.styles')
@endsection

@section('body_class', 'pdf-body')

@php
	$startDate = $order->tanggal_mulai
		? \Carbon\Carbon::parse($order->tanggal_mulai)
		: null;
	$dayCount = $order->durasi_hari ?: max(1, $order->itineraryDays->count());
@endphp

@section('content')
	<main class="page">
		@include('exports.partials.header', [
			'brand' => 'Tours',
			'eyebrow' => 'Itinerary Perjalanan',
			'title' => $order->kode,
			'dateLine' => 'Diterbitkan '.now()->locale('id')->translatedFormat('d F Y'),
		])

		<h2>Informasi Perjalanan</h2>
		<div class="grid grid3">
			<div class="f"><label>Client</label><div>{{ $order->client?->nama ?? '-' }}</div></div>
			<div class="f"><label>Nama Order</label><div>{{ $order->nama_order ?: '-' }}</div></div>
			<div class="f"><label>Kode Group</label><div>{{ $order->kode_group ?: '-' }}</div></div>
			<div class="f">
				<label>Tanggal Mulai</label>
				<div>
					{{ $startDate?->locale('id')->translatedFormat('d F Y') ?? '-' }}{{ $order->jam_mulai ? ' · '.$order->jam_mulai : '' }}
				</div>
			</div>
			<div class="f"><label>Durasi</label><div>{{ $dayCount }} Hari</div></div>
			<div class="f"><label>Jumlah Peserta</label><div>{{ $order->dewasa ?? 0 }} Dewasa, {{ $order->anak ?? 0 }} Anak</div></div>
		</div>

		@if ($order->catatan)
			<div class="box cream mt-3">
				<strong>Catatan untuk tamu:</strong> {{ $order->catatan }}
			</div>
		@endif

		<h2>Rincian Itinerary</h2>
		@if ($order->itineraryDays->isEmpty())
			<p class="note">Belum ada rincian itinerary.</p>
		@else
			@foreach ($order->itineraryDays as $day)
				@php
					$dayDate = $startDate?->copy()->addDays($day->hari - 1);
					$firstActivity = $day->activities->first(fn ($activity) => filled($activity->aktivitas));
				@endphp
				<article class="day break-inside-avoid">
					<div class="dh">
						<span>Hari {{ $day->hari }} — {{ $firstActivity?->aktivitas ?: 'Itinerary' }}</span>
						<span>{{ $dayDate?->locale('id')->translatedFormat('l, d M Y') ?? '-' }}</span>
					</div>
					<div class="db">
						<div class="grid">
							<div class="f">
								<label>Penjemputan</label>
								<div>{{ $day->waktu_penjemputan ?: '--:--' }} · {{ $day->tempat_penjemputan ?: '-' }}</div>
							</div>
							<div class="f">
								<label>Drop Akhir</label>
								<div>{{ $day->waktu_drop_akhir ?: '--:--' }} · {{ $day->tempat_drop_akhir ?: '-' }}</div>
							</div>
						</div>

						@if ($day->activities->isNotEmpty())
							<ul class="tl">
								@foreach ($day->activities as $activity)
									@if (filled($activity->aktivitas))
										<li>{{ $activity->aktivitas }}</li>
									@endif
								@endforeach
							</ul>
						@else
							<p class="note mt-2">Belum ada aktivitas untuk hari ini.</p>
						@endif
					</div>
				</article>
			@endforeach
		@endif

		@include('exports.partials.footer', ['footer' => 'Mei Bali Ops · Itinerary '.$order->kode])
	</main>
@endsection