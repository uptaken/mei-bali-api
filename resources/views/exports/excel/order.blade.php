@php
	$tipeLabel = ['tour' => 'Tour', 'ticket' => 'Ticket', 'check_in' => 'Check In', 'check_out' => 'Check Out', 'transfer' => 'Transfer'];
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
			<th>Total (Rp.)</th>
			<th>Dibuat oleh</th>
		</tr>
	</thead>
	<tbody>
		@forelse ($arr as $temp)
			@php
				$tipe = $temp->tipe->value === 'layanan' ? ($temp->sub_tipe?->value ?? 'check_in') : $temp->tipe->value;
				$tamu = $temp->guests->pluck('nama')->filter()->implode(', ');
				$kota = $temp->tipe->value === 'tour' ? (($temp->kota_termasuk[0] ?? null) ?: $temp->destinasi) : ($temp->kota ?: $temp->destinasi);
				$mulai = $temp->tanggal_mulai ?? $temp->tanggal_pemakaian;
			@endphp
			<tr>
				<td>{{ $tipeLabel[$tipe] ?? $tipe }}</td>
				<td>{{ $temp->kode }}</td>
				<td>{{ $temp->client?->kode }}</td>
				<td>{{ $temp->kode_group }}</td>
				<td>{{ $tamu }}</td>
				<td>{{ $kota }}</td>
				<td>{{ $temp->nama_order }}</td>
				<td>{{ $temp->created_at?->format('d/m/Y H:i') }}</td>
				<td>{{ $mulai?->format('d/m/Y') }}</td>
				<td>{{ $temp->jam_mulai }}</td>
				<td>{{ $temp->tipe->value === 'tour' ? $temp->durasi_hari : '' }}</td>
				<td>{{ $temp->status->value }}</td>
				<td>{{ $temp->total }}</td>
				<td>{{ $temp->createdBy?->nama }}</td>
			</tr>
		@empty
			<tr><td colspan="14">Tidak ada order yang sesuai filter.</td></tr>
		@endforelse
	</tbody>
</table>
