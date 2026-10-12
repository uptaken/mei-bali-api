<?php

namespace App\Models\Concerns;

/**
 * Master yang bisa dinonaktifkan (kolom `archived_at`). Baris tetap ada dan tetap ikut di daftar,
 * dengan `aktif: false`, sehingga order lama bisa menampilkan namanya; pilihan di form order baru
 * menyaring yang tidak aktif.
 */
trait Archivable
{
    public function initializeArchivable(): void
    {
        $this->append('aktif');
    }

    public function getAktifAttribute(): bool
    {
        return $this->archived_at === null;
    }

    public function setActive(bool $active): void
    {
        $this->archived_at = $active ? null : now();
        $this->save();
    }
}
