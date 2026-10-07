<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\OrderSubType;
use App\Enums\OrderType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode',
        'tipe',
        'sub_tipe',
        'client_id',
        'nama_order',
        'kode_group',
        'status',
        'total',
        'tanggal_mulai',
        'jam_mulai',
        'durasi_hari',
        'destinasi',
        'bahasa_id',
        'kota',
        'kota_termasuk',
        'paket_id',
        'dewasa',
        'anak',
        'catatan',
        'vehicle_id',
        'supplier_id',
        'biaya_transport_modal',
        'catatan_penugasan',
        'wa_sent_at',
        'tanggal_pemakaian',
        'pending_cancellation',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'tipe' => OrderType::class,
            'sub_tipe' => OrderSubType::class,
            'status' => OrderStatus::class,
            'total' => 'integer',
            'durasi_hari' => 'integer',
            'kota_termasuk' => 'array',
            'dewasa' => 'integer',
            'anak' => 'integer',
            'biaya_transport_modal' => 'integer',
            'wa_sent_at' => 'datetime',
            // 'tanggal_mulai' => 'date',
            'tanggal_pemakaian' => 'date',
            'pending_cancellation' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

		public function supplier(): BelongsTo
		{
				return $this->belongsTo(Supplier::class);
		}

		public function vehicle(): BelongsTo
		{
				return $this->belongsTo(Vehicle::class);
		}

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(OrderAssignment::class)->orderBy('hari');
    }

    public function guests(): HasMany
    {
        return $this->hasMany(OrderGuest::class);
    }

    public function itineraryDays(): HasMany
    {
        return $this->hasMany(OrderItineraryDay::class)->orderBy('hari');
    }

    public function addOns(): HasMany
    {
        return $this->hasMany(OrderAddOn::class);
    }

    public function ticketRows(): HasMany
    {
        return $this->hasMany(OrderTicketRow::class);
    }

    public function layananDetail(): HasOne
    {
        return $this->hasOne(OrderLayananDetail::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** How many day-rows Operational & Reservasi should show — multi-day only for Tour. */
    public function assignmentDayCount(): int
    {
        if ($this->tipe !== OrderType::Tour) {
            return 1;
        }

        return max(1, $this->itineraryDays()->count() ?: ($this->durasi_hari ?? 1));
    }

    /**
     * Mirrors legModal() from src/lib/orderDraft.ts: Tour = itinerary Biaya, Layanan = the order's
     * own Biaya (Modal), Ticket = cart modal. Assumes itineraryDays.activities / ticketRows /
     * layananDetail are already eager-loaded.
     */
    public function legModalValue(): int
    {
        return match ($this->tipe) {
            OrderType::Tour => $this->itineraryDays->flatMap->activities->sum('biaya'),
            OrderType::Ticket => $this->ticketRows->sum(fn ($r) => $r->qty * $r->modal_satuan),
            OrderType::Layanan => $this->layananDetail?->biaya_modal ?? 0,
        };
    }
}
