@php
	$idr = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
	$tipeLabel = ['transportasi' => 'Transportasi', 'tour' => 'Tour', 'ticket' => 'Tiket', 'addon' => 'Add-On', 'layanan' => 'Layanan'];
@endphp

<table>
	<thead>
		<tr>
			<th>Tipe</th>
			<th>Order ID</th>
			<th>Kode Client</th>
			<th>Kode Group</th>
			<th>Nama Tamu</th>
			<th>Kota</th>
			<th>Nama Order</th>
			<th>Tanggal Dibuat</th>
			<th>Tanggal Mulai</th>
			<th>Jam Mulai</th>
			<th>Durasi (hari)</th>
			<th>Status</th>
			<th class="r">Total Modal (Rp.)</th>
			<th class="r">Dibuat oleh</th>
		</tr>
	</thead>
	<tbody>
		@forelse ($arr as $temp)
			<tr>
				<td class="mono">{{ $temp->tipe }}</td>
				<td>{{ $temp->kode }}</td>
				<td>{{ $temp->client->kode }}</td>
				<td>{{ $temp->kode_group }}</td>
				<td>{{ $temp->kota }}</td>
				<td>{{ $temp->kota }}</td>
				<td>{{ $temp->nama_order }}</td>
				<td>{{ !empty($temp->tanggal_dibuat) ? $temp->tanggal_dibuat->isoFormat('DD/MM/YYYY') : '-' }}</td>
				<td>{{ !empty($temp->tanggal_mulai) ? \Carbon\Carbon::parse($temp->tanggal_mulai)->isoFormat('DD/MM/YYYY') : '-' }}</td>
				<td>{{ $temp->jam_mulai }}</td>
				<td>{{ $temp->durasi_hari }}</td>
				<td>
					@if ($temp->status === 'Dibatalkan')
						<span class="pill warn">Dibatalkan</span>
					@else
						<span class="pill {{ $temp->status === 'Bayar' ? 'ok' : 'bad' }}">{{ $temp->status }}</span>
					@endif
				</td>
				<td>{{ $idr($temp->biaya_transport_modal + $temp->total_modal_addons + ($temp->total_modal_satuan_ticketrows * $temp->total_qty_ticketrows)) }}</td>
				<td>{{ $temp->createdBy->nama }}</td>

			</tr>
		@empty
			<tr><td colspan="6" class="note">Tidak ada tagihan pada periode ini.</td></tr>
		@endforelse
	</tbody>
</table>