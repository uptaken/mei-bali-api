@php
	$idr = fn ($n) => 'Rp '.number_format($n, 0, ',', '.');
@endphp

<table>
	<thead>
		<tr>
			<th>No. Invoice</th>
			<th>Order</th>
			<th>Client</th>
			<th class="r">Total</th>
			<th class="r">Sisa</th>
			<th>Status</th>
		</tr>
	</thead>
	<tbody>
		@forelse ($invoices as $invoice)
			<tr>
				<td class="mono">{{ $invoice->nomor }}</td>
				<td>{{ $invoice->order?->kode ?? '-' }}</td>
				<td>{{ $invoice->client?->nama ?? '-' }}</td>
				<td class="r">{{ $idr($invoice->total) }}</td>
				<td class="r">{{ $idr($invoice->sisa) }}</td>
				<td>
					@if ($invoice->status->value === 'Sudah Ditagihkan' && $invoice->sisa === 0)
						<span class="pill ok">Lunas</span>
					@elseif ($invoice->status->value === 'Sudah Ditagihkan')
						<span class="pill warn">Sudah Ditagihkan</span>
					@else
						<span class="pill info">{{ $invoice->status->value }}</span>
					@endif
				</td>
			</tr>
		@empty
			<tr><td colspan="6" class="note">Tidak ada invoice pada periode ini.</td></tr>
		@endforelse
	</tbody>
</table>