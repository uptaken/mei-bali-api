<?php

namespace App\Http\Controllers\Concerns;

/**
 * Master data (client, supplier, mobil, produk, ...) yang masih dipakai order tidak boleh dihapus:
 * order, invoice, dan tagihan lama kehilangan rujukannya (atau, untuk client, database menolak dengan
 * error 500). Controller memanggil ini sebelum delete; hasilnya 422 dengan pesan yang bisa ditampilkan.
 */
trait RefusesDeleteWhenUsed
{
    /**
     * @param  string  $what   jenis master, mis. "Supplier"
     * @param  string  $name   nama yang tampil di pesan
     * @param  array<string, int>  $usage  label pemakaian => jumlahnya, mis. ['order' => 3, 'invoice' => 3]
     */
    protected function refuseIfUsed(string $what, ?string $name, array $usage): void
    {
        $used = collect($usage)->filter()->map(fn (int $count, string $label) => "{$count} {$label}")->values();

        abort_if(
            $used->isNotEmpty(),
            422,
            trim("{$what} {$name}").' masih dipakai di '.$used->implode(' dan ').', jadi tidak bisa dihapus.',
        );
    }
}
