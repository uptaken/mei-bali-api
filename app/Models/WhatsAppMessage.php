<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Audit log of every WhatsApp message this app has attempted to send, whatever the outcome. */
class WhatsAppMessage extends Model
{
    // Eloquent's convention-based table name for "WhatsAppMessage" would be "whats_app_messages" —
    // explicit here to match the migration's "whatsapp_messages".
    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'related_type',
        'related_id',
        'sent_by',
        'to_phone',
        'to_name',
        'message',
        'status',
        'provider_response',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_response' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
