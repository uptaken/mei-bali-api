<table>
	<thead>
		<tr>
			<th>No Invoice</th>
			<th>Tanggal Dibuat</th>
			<th>Kode Order</th>
			<th>Client</th>
			<th>Total Invoice (Rp.)</th>
			<th>Total Modal (Rp.)</th>
			<th>Margin (Rp.)</th>
			<th>Sudah Dibayar (Rp.)</th>
			<th>Sisa (Rp.)</th>
			<th>Status Tagihan</th>
			<th>Status Order</th>
		</tr>
	</thead>
	<tbody>
		@forelse ($arr as $temp)
			@php
				$modal = $temp->lines->sum(fn ($l) => $l->qty * $l->modal);
				$billed = $temp->status->value === 'Sudah Ditagihkan';
			@endphp
			<tr>
				<td>{{ $temp->nomor }}</td>
				<td>{{ $temp->tanggal_dibuat?->format('d/m/Y') }}</td>
				<td>{{ $temp->order?->kode }}</td>
				<td>{{ $temp->client?->nama }}</td>
				<td>{{ $temp->total }}</td>
				<td>{{ $modal }}</td>
				<td>{{ $temp->total - $modal }}</td>
				<td>{{ $temp->total - $temp->sisa }}</td>
				<td>{{ $temp->sisa }}</td>
				<td>{{ $billed && $temp->sisa === 0 ? 'Lunas' : $temp->status->value }}</td>
				<td>{{ $temp->order?->status->value }}</td>
			</tr>
		@empty
			<tr><td colspan="11">Tidak ada invoice yang sesuai filter.</td></tr>
		@endforelse
	</tbody>
</table>
