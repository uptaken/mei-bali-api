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
		<p class="m-0 mb-[35px] text-right text-[#a99b92] text-[10px] tracking-[2px] uppercase">Template 1 · Itinerary (Tour) · Tanpa Modal/Biaya</p>

		<header class="flex items-start justify-between gap-6 border-b-[3px] border-[#f36f7d] pb-[17px]">
			<div>
				<div class="mb-[7px] flex items-center gap-3">
					<div class="flex size-[46px] items-center justify-center rounded-[14px] bg-[#f47c88]" aria-hidden="true">
						<svg width="23" height="23" viewBox="0 0 24 24" fill="none">
							<path d="M12 3c0 6-2 9-6 10m6-10c0 6 2 9 6 10M6 13c2 2 4 3 6 3s4-1 6-3M4 17c2 1 5 2 8 2s6-1 8-2" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
					<h1 class="m-0 text-[#252525] text-[23px] font-bold tracking-[-1px]">Mei Bali <span class="text-[#97877e] text-[10px] tracking-[2px]">TOURS</span></h1>
				</div>
				<div class="text-[#81766f] text-[12px]">
					PT Mei Bali Wisata · Jl. Contoh No. 1, Denpasar, Bali<br>
					info@meibali.com · +62 361 000 000
				</div>
			</div>

			<div class="min-w-[230px] text-right">
				<p class="m-0 mb-2 text-[#f36f7d] text-[12px] font-bold tracking-[3px]">Itinerary Perjalanan</p>
				<h2 class="m-0 text-[28px] leading-[1.15]">{{ $order->kode }}</h2>
				<div class="text-[#81766f] text-[12px]">Diterbitkan {{ now()->locale('id')->translatedFormat('d F Y') }}</div>
			</div>
		</header>

		<section class="mt-[22px]">
			<h2 class="m-0 mb-3 text-[#873900] text-[13px] font-bold tracking-[2px]">Informasi Perjalanan</h2>
			<div class="grid grid-cols-3 gap-x-7 gap-y-[14px]">
				<div>
					<span class="mb-0.5 block text-[#a99b92] text-[11px] font-bold tracking-[1px] uppercase">Client</span>
					<div class="text-[15px] font-bold">{{ $order->client?->nama ?? '-' }}</div>
				</div>
				<div>
					<span class="mb-0.5 block text-[#a99b92] text-[11px] font-bold tracking-[1px] uppercase">Nama Order</span>
					<div class="text-[15px] font-bold">{{ $order->nama_order }}</div>
				</div>
				<div>
					<span class="mb-0.5 block text-[#a99b92] text-[11px] font-bold tracking-[1px] uppercase">Kode Group</span>
					<div class="text-[15px] font-bold">{{ $order->kode_group }}</div>
				</div>
				<div>
					<span class="mb-0.5 block text-[#a99b92] text-[11px] font-bold tracking-[1px] uppercase">Tanggal Mulai</span>
					<div class="text-[15px] font-bold">
						{{ $startDate?->locale('id')->translatedFormat('d F Y') ?? '-' }}{{ $order->jam_mulai ? ' · '.$order->jam_mulai : '' }}
					</div>
				</div>
				<div>
					<span class="mb-0.5 block text-[#a99b92] text-[11px] font-bold tracking-[1px] uppercase">Durasi</span>
					<div class="text-[15px] font-bold">{{ $dayCount }} Hari</div>
				</div>
				<div>
					<span class="mb-0.5 block text-[#a99b92] text-[11px] font-bold tracking-[1px] uppercase">Jumlah Peserta</span>
					<div class="text-[15px] font-bold">{{ $order->dewasa }} Dewasa, {{ $order->anak }} Anak</div>
				</div>
			</div>
		</section>

		@if ($order->catatan)
			<section class="mt-[22px] rounded-[11px] border border-[#f8d1b9] bg-[#fff0e5] px-4 py-3 text-[#873900] text-[14px]">
				<strong>Catatan untuk tamu:</strong> {{ $order->catatan }}
			</section>
		@endif

		<section class="mt-[22px]">
			<h2 class="m-0 mb-3 text-[#873900] text-[13px] font-bold tracking-[2px]">Rincian Itinerary</h2>
			@if ($order->itineraryDays->isEmpty())
				<p class="text-[#81766f] italic">Belum ada rincian itinerary.</p>
			@else
				@foreach ($order->itineraryDays as $day)
					@php
						$dayDate = $startDate?->copy()->addDays($day->hari - 1);
					@endphp
					<article class="mb-3 overflow-hidden rounded-[11px] border border-[#e7e7e7] [break-inside:avoid-page]">
						<div class="flex justify-between gap-4 bg-linear-to-r from-[#ffeadc] to-[#fffaf6] px-[14px] py-[9px] text-[#873900] text-[15px] font-bold">
							<span>Hari {{ $day->hari }} — {{ $day->activities->first()?->aktivitas ?: 'Itinerary' }}</span>
							<span>{{ $dayDate?->locale('id')->translatedFormat('l, d M Y') ?? '-' }}</span>
						</div>
						<div class="px-[14px] pt-3 pb-[13px]">
							<div class="mb-[11px] grid grid-cols-2 gap-[18px]">
								<div>
									<span class="mb-0.5 block text-[#a99b92] text-[11px] font-bold tracking-[1px] uppercase">Penjemputan</span>
									<div class="text-[15px] font-bold">{{ $day->waktu_penjemputan ?: '--:--' }} · {{ $day->tempat_penjemputan ?: '-' }}</div>
								</div>
								<div>
									<span class="mb-0.5 block text-[#a99b92] text-[11px] font-bold tracking-[1px] uppercase">Drop Akhir</span>
									<div class="text-[15px] font-bold">{{ $day->waktu_drop_akhir ?: '--:--' }} · {{ $day->tempat_drop_akhir ?: '-' }}</div>
								</div>
							</div>
							@if ($day->activities->isNotEmpty())
								<ul class="m-0 list-none p-0">
									@foreach ($day->activities as $activity)
										@if ($activity->aktivitas)
											<li class="relative mt-[6px] pl-[18px] text-[14px] before:absolute before:top-[6px] before:left-0 before:size-2 before:rounded-full before:bg-[#f36f7d] before:content-['']">{{ $activity->aktivitas }}</li>
										@endif
									@endforeach
								</ul>
							@else
								<p class="text-[#81766f] italic">Belum ada aktivitas untuk hari ini.</p>
							@endif
						</div>
					</article>
				@endforeach
			@endif
		</section>
	</main>
@endsection
