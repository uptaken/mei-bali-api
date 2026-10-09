@php
	$idr = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
	$tipeLabel = ['transportasi' => 'Transportasi', 'tour' => 'Tour', 'ticket' => 'Tiket', 'addon' => 'Add-On', 'layanan' => 'Layanan'];
@endphp

<table>
	<thead>
		<tr>
			<th>Order</th>
			<th>Supplier / Driver</th>
			<th>Tipe</th>
			<th>Tgl Order</th>
			<th class="r">Modal</th>
			<th>Status</th>
		</tr>
	</thead>
	<tbody>
		@forelse ($groups as $group)
			<tr>
				<td class="mono">{{ $group['kode'] }}</td>
				<td>{{ $group['supplierNama'] }}</td>
				<td>{{ $tipeLabel[$group['tipeTagihan']] ?? $group['tipeTagihan'] }}</td>
				<td>{{ $group['tanggalOrder'] ? \Carbon\Carbon::parse($group['tanggalOrder'])->locale('id')->translatedFormat('d M Y') : '-' }}</td>
				<td class="r">{{ $idr($group['modal']) }}</td>
				<td>
					@if ($group['cancelled'])
						<span class="pill warn">Dibatalkan</span>
					@else
						<span class="pill {{ $group['status'] === 'Bayar' ? 'ok' : 'bad' }}">{{ $group['status'] }}</span>
					@endif
				</td>
			</tr>
		@empty
			<tr><td colspan="6" class="note">Tidak ada tagihan pada periode ini.</td></tr>
		@endforelse
	</tbody>
</table>