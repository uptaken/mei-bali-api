<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    // Eloquent's convention-based table name for "WhatsAppTemplate" would be "whats_app_templates" —
    // explicit here to match the migration's "whatsapp_templates".
    protected $table = 'whatsapp_templates';

    protected $fillable = ['nama', 'kategori', 'isi', 'variabel'];

    protected function casts(): array
    {
        return ['variabel' => 'array'];
    }

    /** Fills {{placeholders}} from $map; anything not in $map is left as-is, same as the frontend. */
    public function render(array $map): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($matches) use ($map) {
            return $map[$matches[1]] ?? $matches[0];
        }, $this->isi);
    }
}
