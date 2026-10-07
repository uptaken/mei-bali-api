@extends('exports.base')

@php
	$startDate = $order->tanggal_mulai
		? \Carbon\Carbon::parse($order->tanggal_mulai)
		: null;
	$dayCount = $order->durasi_hari ?: max(1, $order->itineraryDays->count());
@endphp

@section('style')
	<style>
		* {
			box-sizing: border-box;
		}

		body {
			margin: 0;
			color: #242424;
			font-family: Arial, Helvetica, sans-serif;
			font-size: 12px;
			line-height: 1.45;
		}

		.page {
			padding: 22px 24px;
		}

		.template-label {
			margin: 0 0 35px;
			color: #a99b92;
			font-size: 10px;
			letter-spacing: 2px;
			text-align: right;
			text-transform: uppercase;
		}

		.header {
			display: flex;
			justify-content: space-between;
			align-items: flex-start;
			gap: 24px;
			border-bottom: 3px solid #f36f7d;
			padding-bottom: 17px;
		}

		.brand {
			display: flex;
			align-items: center;
			gap: 12px;
			margin-bottom: 7px;
		}

		.brand-mark {
			display: flex;
			width: 46px;
			height: 46px;
			align-items: center;
			justify-content: center;
			border-radius: 14px;
			background: #f47c88;
		}

		.brand-name {
			margin: 0;
			color: #252525;
			font-size: 23px;
			font-weight: 700;
			letter-spacing: -1px;
		}

		.brand-name span {
			color: #97877e;
			font-size: 10px;
			letter-spacing: 2px;
		}

		.company-details,
		.order-date {
			color: #81766f;
			font-size: 12px;
		}

		.document-title {
			min-width: 230px;
			text-align: right;
		}

		.document-title .eyebrow {
			margin: 0 0 8px;
			color: #f36f7d;
			font-size: 12px;
			font-weight: 700;
			letter-spacing: 3px;
		}

		.order-code {
			margin: 0;
			font-size: 28px;
			line-height: 1.15;
		}

		section {
			margin-top: 22px;
		}

		.section-title {
			margin: 0 0 12px;
			color: #873900;
			font-size: 13px;
			font-weight: 700;
			letter-spacing: 2px;
		}

		.facts {
			display: grid;
			grid-template-columns: repeat(3, 1fr);
			gap: 14px 28px;
		}

		.fact-label,
		.location-label {
			display: block;
			margin-bottom: 2px;
			color: #a99b92;
			font-size: 11px;
			font-weight: 700;
			letter-spacing: 1px;
			text-transform: uppercase;
		}

		.fact-value,
		.location-value {
			font-size: 15px;
			font-weight: 700;
		}

		.note {
			border: 1px solid #f8d1b9;
			border-radius: 11px;
			background: #fff0e5;
			color: #873900;
			padding: 12px 16px;
			font-size: 14px;
		}

		.itinerary-day {
			margin-bottom: 12px;
			overflow: hidden;
			border: 1px solid #e7e7e7;
			border-radius: 11px;
			page-break-inside: avoid;
		}

		.day-heading {
			display: flex;
			justify-content: space-between;
			gap: 16px;
			background: linear-gradient(90deg, #ffeadc, #fffaf6);
			color: #873900;
			padding: 9px 14px;
			font-size: 15px;
			font-weight: 700;
		}

		.day-content {
			padding: 12px 14px 13px;
		}

		.locations {
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 18px;
			margin-bottom: 11px;
		}

		.activities {
			margin: 0;
			padding: 0;
			list-style: none;
		}

		.activities li {
			position: relative;
			margin-top: 6px;
			padding-left: 18px;
			font-size: 14px;
		}

		.activities li::before {
			position: absolute;
			top: 6px;
			left: 0;
			width: 8px;
			height: 8px;
			border-radius: 50%;
			background: #f36f7d;
			content: "";
		}

		.empty-itinerary {
			color: #81766f;
			font-style: italic;
		}

		@media print {
			.page {
				padding: 0;
			}
		}
	</style>
@endsection

@section('content')
	<main class="page">
		<p class="template-label">Template 1 · Itinerary (Tour) · Tanpa Modal/Biaya</p>

		<header class="header">
			<div>
				<div class="brand">
					<div class="brand-mark" aria-hidden="true">
						<svg width="23" height="23" viewBox="0 0 24 24" fill="none">
							<path d="M12 3c0 6-2 9-6 10m6-10c0 6 2 9 6 10M6 13c2 2 4 3 6 3s4-1 6-3M4 17c2 1 5 2 8 2s6-1 8-2" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
						</svg>
					</div>
					<h1 class="brand-name">Mei Bali <span>TOURS</span></h1>
				</div>
				<div class="company-details">
					PT Mei Bali Wisata · Jl. Contoh No. 1, Denpasar, Bali<br>
					info@meibali.com · +62 361 000 000
				</div>
			</div>

			<div class="document-title">
				<p class="eyebrow">Itinerary Perjalanan</p>
				<h2 class="order-code">{{ $order->kode }}</h2>
				<div class="order-date">Diterbitkan {{ now()->locale('id')->translatedFormat('d F Y') }}</div>
			</div>
		</header>

		<section>
			<h2 class="section-title">Informasi Perjalanan</h2>
			<div class="facts">
				<div>
					<span class="fact-label">Client</span>
					<div class="fact-value">{{ $order->client?->nama ?? '-' }}</div>
				</div>
				<div>
					<span class="fact-label">Nama Order</span>
					<div class="fact-value">{{ $order->nama_order }}</div>
				</div>
				<div>
					<span class="fact-label">Kode Group</span>
					<div class="fact-value">{{ $order->kode_group }}</div>
				</div>
				<div>
					<span class="fact-label">Tanggal Mulai</span>
					<div class="fact-value">
						{{ $startDate?->locale('id')->translatedFormat('d F Y') ?? '-' }}{{ $order->jam_mulai ? ' · '.$order->jam_mulai : '' }}
					</div>
				</div>
				<div>
					<span class="fact-label">Durasi</span>
					<div class="fact-value">{{ $dayCount }} Hari</div>
				</div>
				<div>
					<span class="fact-label">Jumlah Peserta</span>
					<div class="fact-value">{{ $order->dewasa }} Dewasa, {{ $order->anak }} Anak</div>
				</div>
			</div>
		</section>

		@if ($order->catatan)
			<section class="note">
				<strong>Catatan untuk tamu:</strong> {{ $order->catatan }}
			</section>
		@endif

		<section>
			<h2 class="section-title">Rincian Itinerary</h2>
			@if ($order->itineraryDays->isEmpty())
				<p class="empty-itinerary">Belum ada rincian itinerary.</p>
			@else
				@foreach ($order->itineraryDays as $day)
					@php
						$dayDate = $startDate?->copy()->addDays($day->hari - 1);
					@endphp
					<article class="itinerary-day">
						<div class="day-heading">
							<span>Hari {{ $day->hari }} — {{ $day->activities->first()?->aktivitas ?: 'Itinerary' }}</span>
							<span>{{ $dayDate?->locale('id')->translatedFormat('l, d M Y') ?? '-' }}</span>
						</div>
						<div class="day-content">
							<div class="locations">
								<div>
									<span class="location-label">Penjemputan</span>
									<div class="location-value">{{ $day->waktu_penjemputan ?: '--:--' }} · {{ $day->tempat_penjemputan ?: '-' }}</div>
								</div>
								<div>
									<span class="location-label">Drop Akhir</span>
									<div class="location-value">{{ $day->waktu_drop_akhir ?: '--:--' }} · {{ $day->tempat_drop_akhir ?: '-' }}</div>
								</div>
							</div>
							@if ($day->activities->isNotEmpty())
								<ul class="activities">
									@foreach ($day->activities as $activity)
										@if ($activity->aktivitas)
											<li>{{ $activity->aktivitas }}</li>
										@endif
									@endforeach
								</ul>
							@else
								<p class="empty-itinerary">Belum ada aktivitas untuk hari ini.</p>
							@endif
						</div>
					</article>
				@endforeach
			@endif
		</section>
	</main>
@endsection
