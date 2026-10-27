<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExcelExportTest extends TestCase
{
    use RefreshDatabase;

    private function rows($response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        return IOFactory::load($path)->getActiveSheet()->toArray(null, true, false);
    }

    public function test_excel_exports_every_row_with_filters_and_real_numbers(): void
    {
        $admin = User::factory()->create(['role' => 'Admin']);
        $client = Client::create(['kode' => 'CLI-91', 'nama' => 'Excel']);
        foreach (range(1, 25) as $i) {
            $this->actingAs($admin, 'sanctum')->postJson('/api/orders', [
                'tipe' => 'ticket', 'clientId' => $client->id, 'namaOrder' => "Order {$i}", 'kodeGroup' => "G-{$i}", 'tanggalPemakaian' => '2026-12-06',
                'tamu' => [['nama' => 'Budi', 'kategori' => 'Dewasa']],
                'ticketRows' => [['namaTiket' => 'Zoo', 'qty' => 2, 'modalSatuan' => 100000]],
            ])->assertCreated();
        }

        $orders = $this->rows($this->actingAs($admin, 'sanctum')->get('/api/export/order/excel'));
        $this->assertCount(26, $orders, '25 order + 1 header, tidak terpotong di 20');
        $this->assertSame('Budi', $orders[1][4]);
        $this->assertSame('Ticket', $orders[1][0]);
        $this->assertSame('Siap Kirim WA', $orders[1][11]);
        $this->assertEquals(200000, $orders[1][12]);
        $this->assertNotEmpty($orders[1][7]);

        $filtered = $this->rows($this->actingAs($admin, 'sanctum')->get('/api/export/order/excel?q=Order 7'));
        $this->assertCount(2, $filtered);

        $first = Invoice::orderBy('id')->first();
        $one = $this->rows($this->actingAs($admin, 'sanctum')->get('/api/export/invoice/excel?id='.$first->id));
        $this->assertCount(2, $one);
        $this->assertSame('Belum Ditagihkan', $one[1][9]);
        $this->assertEquals($first->total, $one[1][4]);
        $this->assertCount(26, $this->rows($this->actingAs($admin, 'sanctum')->get('/api/export/invoice/excel')));

        foreach (['account-payable', 'account-receivable', 'profit-loss'] as $name) {
            $this->assertNotEmpty($this->rows($this->actingAs($admin, 'sanctum')->get("/api/export/{$name}/excel")), $name);
        }

        $this->actingAs($admin, 'sanctum')->patchJson('/api/orders/'.\App\Models\Order::orderBy('id')->value('id'), ['catatan' => 'Tamu alergi kacang'])->assertOk();
        $this->assertCount(2, $this->rows($this->actingAs($admin, 'sanctum')->get('/api/export/order/excel?q=alergi')));
        $this->assertCount(1, $this->actingAs($admin, 'sanctum')->getJson('/api/orders?q=alergi')->json('data'));
    }
}
