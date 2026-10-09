@php
	$idr = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
	$margin = fn ($laba, $pendapatan) => $pendapatan > 0 ? number_format($laba / $pendapatan * 100, 1, ',', '.').'%' : '—';
	$pendapatan = array_sum(array_column($months, 'pendapatan'));
	$modal = array_sum(array_column($months, 'modal'));
	$laba = $pendapatan - $modal;
@endphp

<table>
	<thead>
		<tr>
			<th>Bulan</th>
			<th class="r">Pendapatan</th>
			<th class="r">Modal</th>
			<th class="r">Laba Bersih</th>
			<th class="r">Margin</th>
			<th>Status</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($months as $month)
			<tr>
				<td>{{ $month['label'] }}</td>
				<td class="r">{{ $idr($month['pendapatan']) }}</td>
				<td class="r">{{ $idr($month['modal']) }}</td>
				<td class="r">{{ $idr($month['laba']) }}</td>
				<td class="r">{{ $margin($month['laba'], $month['pendapatan']) }}</td>
				<td>
					<span class="pill {{ $month['laba'] > 0 ? 'ok' : ($month['laba'] < 0 ? 'bad' : 'info') }}">{{ $month['laba'] > 0 ? 'Untung' : ($month['laba'] < 0 ? 'Rugi' : 'Impas') }}</span>
				</td>
			</tr>
		@endforeach
	</tbody>
</table>