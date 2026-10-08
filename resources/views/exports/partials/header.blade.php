<div class="tag">{{ $tag }}</div>
<header class="hd">
	<div>
		<div class="logo">
			<div class="mark" aria-hidden="true">
				<svg class="size-4" viewBox="0 0 24 24" fill="none">
					<path d="M12 3c0 6-2 9-6 10m6-10c0 6 2 9 6 10M6 13c2 2 4 3 6 3s6-1 6-3M4 17c2 1 5 2 8 2s6-1 8-2" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
				</svg>
			</div>
			<div class="word">Mei&nbsp;Bali<small>{{ $brand ?? 'Ops' }}</small></div>
		</div>
		<div class="co">
			@if (($brand ?? 'Ops') === 'Tours')
				PT Mei Bali Wisata · Jl. Contoh No. 1, Denpasar, Bali<br>
				info@meibali.com · +62 361 000 000
			@else
				Laporan Keuangan Internal
			@endif
		</div>
	</div>
	<div class="doc">
		<div class="t">{{ $eyebrow }}</div>
		<div class="n">{{ $title }}</div>
		<div class="s">{{ $dateLine }}</div>
	</div>
</header>
