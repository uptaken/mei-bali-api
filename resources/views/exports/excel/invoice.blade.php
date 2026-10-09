@php
	$idr = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
	$tipeLabel = ['transportasi' => 'Transportasi', 'tour' => 'Tour', 'ticket' => 'Tiket', 'addon' => 'Add-On', 'layanan' => 'Layanan'];
@endphp

<table>
	<thead>
		<tr>
			<th>No Invoice</th>
			<th>Tanggal Dibuat</th>
			<th>Kode Order</th>
			<th>Client</th>
			<th class="r">Total Invoice (Rp.)</th>
			<th class="r">Total Modal (Rp.)</th>
			<th class="r">Margin (Rp.)</th>
			<th class="r">Sudah Dibayar (Rp.)</th>
			<th class="r">Sisa (Rp.)</th>
			<th class="r">Status</th>
		</tr>
	</thead>
	<tbody>
		@forelse ($arr as $temp)
			<tr>
				<td class="mono">{{ $temp->nomor }}</td>
				<td>{{ $temp->tanggal_dibuat->isoFormat('DD/MM/YYYY') }}</td>
				<td>{{ $temp->order->kode }}</td>
				<td>{{ $temp->client->nama }}</td>
				<td>{{ $idr($temp->total) }}</td>
				<td>{{ $idr($temp->modal) }}</td>
				<td>{{ $idr($temp->total - $temp->modal) }}</td>
				<td>{{ $idr($temp->total - $temp->sisa) }}</td>
				<td>{{ $idr($temp->sisa) }}</td>
				<td>
					@if ($temp->order->status === 'Dibatalkan')
						<span class="pill warn">Dibatalkan</span>
					@else
						<span class="pill {{ $temp->order->status === 'Bayar' ? 'ok' : 'bad' }}">{{ $temp->order->status }}</span>
					@endif
				</td>
			</tr>
		@empty
			<tr><td colspan="6" class="note">Tidak ada tagihan pada periode ini.</td></tr>
		@endforelse
	</tbody>
</table>