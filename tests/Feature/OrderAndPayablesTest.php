<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAndPayablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_tour_order_with_two_vehicles_on_one_day_and_it_produces_two_separate_tagihan(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::create(['kode' => 'CLI-01', 'nama' => 'Test Client']);
        $supplierA = Supplier::create(['kode' => 'SUP-01', 'nama' => 'Wayan', 'telepon' => '081200000001']);
        $supplierB = Supplier::create(['kode' => 'SUP-02', 'nama' => 'Hendra', 'telepon' => '081200000002']);
        $vehicleA = Vehicle::create(['nama' => 'Toyota Avanza', 'kapasitas' => 5]);
        $vehicleB = Vehicle::create(['nama' => 'Isuzu Elf Long', 'kapasitas' => 19]);

        $payload = [
            'tipe' => 'tour',
            'clientId' => $client->id,
            'namaOrder' => 'Test Tour',
            'kodeGroup' => 'GRP-TEST',
            'tanggalMulai' => now()->addDays(5)->toDateString(),
            'itinerary' => [
                ['hari' => 1, 'aktivitas' => [['aktivitas' => 'Snorkeling', 'biaya' => 1000000]]],
            ],
            'assignments' => [
                ['hari' => 1, 'vehicleId' => $vehicleA->id, 'supplierId' => $supplierA->id, 'biayaTransportModal' => 800000],
                ['hari' => 1, 'vehicleId' => $vehicleB->id, 'supplierId' => $supplierB->id, 'biayaTransportModal' => 900000],
            ],
        ];

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/orders', $payload);

        $response->assertCreated();
        $orderId = $response->json('id');

        // One invoice line per vehicle, disambiguated with "#1"/"#2" since both share Hari 1.
        $lines = $response->json('invoices.0.lines');
        $transportLines = collect($lines)->filter(fn ($l) => str_starts_with($l['deskripsi'], 'Transport'));
        $this->assertCount(2, $transportLines);

        $payables = $this->actingAs($admin, 'sanctum')->getJson('/api/payables')->json('data');
        // Each Tagihan carries its Order's start date — it feeds the Tanggal Order column and the overdue count.
        $this->assertNotNull(collect($payables)->firstWhere('orderId', $orderId)['tanggalOrder']);
        $transportTagihan = collect($payables)->where('orderId', $orderId)->where('tipeTagihan', 'transportasi');

        // Two different suppliers on the same day → two separate Tagihan, not one merged row.
        $this->assertCount(2, $transportTagihan);
    }

    public function test_creating_an_order_keeps_guests_and_every_nested_ticket_field(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::create(['kode' => 'CLI-02', 'nama' => 'Ticket Client']);

        $payload = [
            'tipe' => 'ticket',
            'clientId' => $client->id,
            'namaOrder' => 'Test Ticket',
            'kodeGroup' => 'GRP-TICKET',
            'tanggalPemakaian' => '2026-11-10',
            'tamu' => [['nama' => 'Budi Santoso', 'kategori' => 'Dewasa']],
            'ticketRows' => [
                ['namaTiket' => 'Bali Zoo', 'qty' => 3, 'modalSatuan' => 100000, 'supplierNama' => 'Bali Zoo Ticketing'],
            ],
        ];

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/orders', $payload);

        $response->assertCreated();
        $this->assertSame('Budi Santoso', $response->json('guests.0.nama'));
        // Date-only columns must come back as plain dates, not UTC timestamps that shift a day in Asia/Makassar.
        $this->assertSame('2026-11-10', $response->json('tanggal_pemakaian'));
        $this->assertSame(3, $response->json('ticket_rows.0.qty'));
        $this->assertSame(100000, $response->json('ticket_rows.0.modal_satuan'));
        // total = qty x modalSatuan, which is only right when qty and modalSatuan survive validation
        $this->assertSame(300000, (int) $response->json('total'));
    }

    public function test_creating_a_tour_keeps_every_invoice_line(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::create(['kode' => 'CLI-03', 'nama' => 'Tour Client']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/orders', [
            'tipe' => 'tour',
            'clientId' => $client->id,
            'namaOrder' => 'Two Activity Tour',
            'kodeGroup' => 'GRP-TWO',
            'itinerary' => [[
                'hari' => 1,
                'aktivitas' => [
                    ['aktivitas' => 'Snorkeling', 'biaya' => 1000000],
                    ['aktivitas' => 'Lunch', 'biaya' => 250000],
                ],
            ]],
        ]);

        $response->assertCreated();
        $this->assertCount(2, $response->json('invoices.0.lines'));
        $this->assertSame([0, 1], array_column($response->json('itinerary_days.0.activities'), 'urutan'));
    }

    public function test_editing_an_order_keeps_its_status_creator_and_invoice_prices(): void
    {
        $creator = User::factory()->create(['role' => UserRole::Admin->value]);
        $editor = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::create(['kode' => 'CLI-04', 'nama' => 'Edit Client']);

        $payload = [
            'tipe' => 'ticket',
            'clientId' => $client->id,
            'namaOrder' => 'Before',
            'kodeGroup' => 'GRP-EDIT',
            'ticketRows' => [['namaTiket' => 'Bali Zoo', 'qty' => 2, 'modalSatuan' => 100000]],
        ];

        $created = $this->actingAs($creator, 'sanctum')->postJson('/api/orders', $payload)->assertCreated();
        $orderId = $created->json('id');

        // State that edits must not disturb: a later status, and a price typed onto the invoice.
        \App\Models\Order::findOrFail($orderId)->update(['status' => 'Berlangsung']);
        \App\Models\InvoiceLine::query()->update(['harga_jual' => 500000]);

        $payload['namaOrder'] = 'After';
        $payload['ticketRows'][0]['id'] = $created->json('ticket_rows.0.id');
        $payload['ticketRows'][0]['qty'] = 3;

        $this->actingAs($editor, 'sanctum')->patchJson("/api/orders/{$orderId}", $payload)->assertOk();

        $order = \App\Models\Order::with(['invoices.lines', 'events'])->findOrFail($orderId);
        $this->assertSame('After', $order->nama_order);
        $this->assertSame('Berlangsung', $order->status->value);
        $this->assertSame($creator->id, $order->created_by);
        $this->assertSame(300000, $order->total);
        $this->assertSame(500000, $order->invoices->first()->lines->first()->harga_jual);
        $this->assertSame(3, $order->invoices->first()->lines->first()->qty);
        $this->assertSame(1, $order->events->where('kind', 'created')->count());
    }

    public function test_an_admin_only_requests_cancellation_and_a_super_admin_can_approve_or_reject_it(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin->value]);
        $client = Client::create(['kode' => 'CLI-05', 'nama' => 'Cancel Client']);

        $orderId = $this->actingAs($admin, 'sanctum')->postJson('/api/orders', [
            'tipe' => 'ticket', 'clientId' => $client->id, 'namaOrder' => 'To Cancel', 'kodeGroup' => 'GRP-CANCEL',
            'ticketRows' => [['namaTiket' => 'Bali Zoo', 'qty' => 1, 'modalSatuan' => 100000]],
        ])->assertCreated()->json('id');

        $requested = $this->actingAs($admin, 'sanctum')->postJson("/api/orders/{$orderId}/cancel", ['alasan' => 'Client batal'])->assertOk();
        $this->assertTrue($requested->json('pending_cancellation'));

        // The frontend rebuilds its Butuh Approval queue from these events, so the list must carry them.
        $listed = $this->actingAs($superAdmin, 'sanctum')->getJson('/api/orders')->assertOk();
        $request = collect($listed->json('data.0.events'))->firstWhere('kind', 'cancel_requested');
        $this->assertSame('Client batal', $request['detail']);
        $this->assertSame($admin->id, $request['actor_id']);
        $this->assertSame('Siap Kirim WA', $requested->json('status'));

        $this->actingAs($superAdmin, 'sanctum')->postJson("/api/orders/{$orderId}/reject-cancellation")
            ->assertOk()->assertJsonPath('pending_cancellation', false);

        $this->actingAs($admin, 'sanctum')->postJson("/api/orders/{$orderId}/cancel")->assertOk();
        $cancelled = $this->actingAs($superAdmin, 'sanctum')->postJson("/api/orders/{$orderId}/cancel")->assertOk();
        $this->assertSame('Dibatalkan', $cancelled->json('status'));

        $this->actingAs($superAdmin, 'sanctum')->postJson("/api/orders/{$orderId}/cancel")->assertStatus(422);
    }

    public function test_renaming_the_guest_of_a_layanan_order_updates_its_main_invoice_line(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::create(['kode' => 'CLI-06', 'nama' => 'Layanan Client']);

        $payload = [
            'tipe' => 'layanan', 'subTipe' => 'transfer', 'clientId' => $client->id, 'namaOrder' => 'Transfer uji', 'kodeGroup' => 'GRP-TRF',
            'layananDetail' => ['namaTamu' => 'John Smith', 'pickUpPoint' => 'Bandara', 'dropOffPoint' => 'Hotel', 'jumlahPax' => 2, 'biayaModal' => 300000],
        ];

        $orderId = $this->actingAs($admin, 'sanctum')->postJson('/api/orders', $payload)->assertCreated()->json('id');
        \App\Models\InvoiceLine::query()->update(['harga_jual' => 450000]);

        $payload['layananDetail']['namaTamu'] = 'Jane Smith';
        $this->actingAs($admin, 'sanctum')->patchJson("/api/orders/{$orderId}", $payload)->assertOk();

        $lines = \App\Models\Order::findOrFail($orderId)->invoices->first()->lines;
        $this->assertCount(1, $lines);
        $this->assertSame('Transfer — Jane Smith', $lines->first()->deskripsi);
        $this->assertSame(450000, $lines->first()->harga_jual);
    }

    public function test_invoice_pricing_billing_payment_and_excel_log_each_have_their_own_endpoint(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::create(['kode' => 'CLI-07', 'nama' => 'Invoice Client']);

        $order = $this->actingAs($admin, 'sanctum')->postJson('/api/orders', [
            'tipe' => 'ticket', 'clientId' => $client->id, 'namaOrder' => 'Invoice flow', 'kodeGroup' => 'GRP-INV',
            'ticketRows' => [['namaTiket' => 'Bali Zoo', 'qty' => 1, 'modalSatuan' => 100000]],
        ])->assertCreated();
        $invoiceId = $order->json('invoices.0.id');
        $lineId = $order->json('invoices.0.lines.0.id');

        // Nothing can be billed before every line has a Harga Jual.
        $this->actingAs($admin, 'sanctum')->postJson("/api/invoices/{$invoiceId}/mark-billed")->assertStatus(422);

        // Pricing the lines recomputes total/sisa and leaves the billing status alone.
        $priced = $this->actingAs($admin, 'sanctum')->patchJson("/api/invoices/{$invoiceId}/lines", [
            'lines' => [['id' => $lineId, 'deskripsi' => 'Bali Zoo', 'qty' => 1, 'hargaJual' => 150000]],
        ])->assertOk();
        $this->assertSame(150000, $priced->json('total'));
        $this->assertSame(150000, $priced->json('sisa'));
        $this->assertSame('Belum Ditagihkan', $priced->json('status'));

        $billed = $this->actingAs($admin, 'sanctum')->postJson("/api/invoices/{$invoiceId}/mark-billed")->assertOk();
        $this->assertSame('Sudah Ditagihkan', $billed->json('status'));
        $this->assertNotNull($billed->json('tanggal_ditagihkan'));

        $paid = $this->actingAs($admin, 'sanctum')->postJson("/api/invoices/{$invoiceId}/payments", ['amount' => 50000])->assertOk();
        $this->assertSame(100000, $paid->json('sisa'));

        $logged = $this->actingAs($admin, 'sanctum')->postJson("/api/invoices/{$invoiceId}/excel-downloaded")->assertOk();
        $this->assertContains('excel', array_column($logged->json('events'), 'kind'));
    }

    public function test_assigning_a_supplier_to_an_existing_order_is_logged_as_assigned_on_order_and_invoice(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::create(['kode' => 'CLI-08', 'nama' => 'Assign Client']);
        $supplier = Supplier::create(['kode' => 'SUP-09', 'nama' => 'Wayan', 'telepon' => '081200000009']);
        $vehicle = Vehicle::create(['nama' => 'Toyota Avanza', 'kapasitas' => 5]);

        $payload = [
            'tipe' => 'tour', 'clientId' => $client->id, 'namaOrder' => 'Assign tour', 'kodeGroup' => 'GRP-ASSIGN',
            'itinerary' => [['hari' => 1, 'aktivitas' => [['aktivitas' => 'Snorkeling', 'biaya' => 100000]]]],
        ];
        $created = $this->actingAs($admin, 'sanctum')->postJson('/api/orders', $payload)->assertCreated();
        $this->assertSame('Menunggu Supplier/Driver', $created->json('status'));

        $payload['assignments'] = [['hari' => 1, 'vehicleId' => $vehicle->id, 'supplierId' => $supplier->id, 'biayaTransportModal' => 300000]];
        $this->actingAs($admin, 'sanctum')->patchJson('/api/orders/'.$created->json('id'), $payload)->assertOk()
            ->assertJsonPath('status', 'Siap Kirim WA')
            ->assertJsonPath('total', 400000);

        $order = \App\Models\Order::with(['events', 'invoices.events'])->findOrFail($created->json('id'));
        $this->assertContains('assigned', $order->events->pluck('kind')->all());
        $this->assertContains('assigned', $order->invoices->first()->events->pluck('kind')->all());
    }

    public function test_whatsapp_session_control_and_pdf_export_require_a_login(): void
    {
        $this->getJson('/api/wa/status')->assertUnauthorized();
        $this->getJson('/api/export/invoice/pdf?id=1')->assertUnauthorized();
    }

    public function test_whatsapp_numbers_are_sent_in_international_form(): void
    {
        $this->assertSame('6281234567890@c.us', \App\Jobs\SendWhatsAppMessageJob::chatId('0812-3456 7890'));
        $this->assertSame('6281234567890@c.us', \App\Jobs\SendWhatsAppMessageJob::chatId('+62 812 3456 7890'));
        $this->assertSame('61412345678@c.us', \App\Jobs\SendWhatsAppMessageJob::chatId('+61 412 345 678'));
    }

    public function test_confirming_a_supplier_queues_the_whatsapp_message_and_returns_the_whole_order(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $client = Client::create(['kode' => 'CLI-09', 'nama' => 'WA Client']);
        $supplier = Supplier::create(['kode' => 'SUP-10', 'nama' => 'Wayan', 'telepon' => '081200000010']);
        $vehicle = Vehicle::create(['nama' => 'Toyota Avanza', 'kapasitas' => 5]);
        \App\Models\WhatsAppTemplate::create([
            'nama' => 'Konfirmasi Supplier', 'kategori' => 'Order', 'isi' => 'Halo {{nama_supplier}}, order {{kode_order}}', 'variabel' => [],
        ]);

        $orderId = $this->actingAs($admin, 'sanctum')->postJson('/api/orders', [
            'tipe' => 'tour', 'clientId' => $client->id, 'namaOrder' => 'WA tour', 'kodeGroup' => 'GRP-WA',
            'itinerary' => [['hari' => 1, 'aktivitas' => [['aktivitas' => 'Snorkeling', 'biaya' => 100000]]]],
            'assignments' => [['hari' => 1, 'vehicleId' => $vehicle->id, 'supplierId' => $supplier->id, 'biayaTransportModal' => 200000]],
        ])->assertCreated()->json('id');

        \Illuminate\Support\Facades\Bus::fake();

        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/orders/{$orderId}/send-wa-supplier")->assertOk();

        \Illuminate\Support\Facades\Bus::assertDispatched(
            \App\Jobs\SendWhatsAppMessageJob::class,
            fn ($job) => $job->phone === '081200000010' && str_contains($job->message, 'Halo Wayan'),
        );
        $this->assertSame('Info Terkirim', $response->json('order.status'));
        $this->assertNotEmpty($response->json('order.assignments'));
        $this->assertContains('wa', array_column($response->json('order.events'), 'kind'));
    }

    public function test_the_whatsapp_job_logs_what_the_gateway_answered_and_retries_a_refusal(): void
    {
        $gateway = \Mockery::mock(\App\Http\Controllers\Helper\CurlHelper::class);
        $gateway->shouldReceive('request')->once()->andReturn(['success' => true]);
        $gateway->shouldReceive('request')->once()->andReturn(['success' => false, 'error' => 'session_not_connected']);

        (new \App\Jobs\SendWhatsAppMessageJob('081234567890', 'Halo'))->handle($gateway);
        $this->assertSame('sent', \App\Models\WhatsAppMessage::firstOrFail()->status);

        $job = new \App\Jobs\SendWhatsAppMessageJob('081234567890', 'Halo lagi');
        try {
            $job->handle($gateway);
            $this->fail('A refused message must throw so the queue retries it.');
        } catch (\RuntimeException $e) {
            $job->failed($e);
        }

        $failed = \App\Models\WhatsAppMessage::where('message', 'Halo lagi')->firstOrFail();
        $this->assertSame('failed', $failed->status);
        $this->assertSame('081234567890', $failed->to_phone);
    }

    public function test_the_pdf_exports_print_real_figures_in_the_layout_of_their_type(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value, 'nama' => 'Kasir Uji']);
        $client = Client::create(['kode' => 'CLI-10', 'nama' => 'PDF Client']);
        $supplier = Supplier::create(['kode' => 'SUP-11', 'nama' => 'Wayan PDF', 'telepon' => '081200000011']);
        $vehicle = Vehicle::create(['nama' => 'Toyota Avanza', 'kapasitas' => 5]);
        $today = now()->toDateString();
        $create = fn (array $payload) => $this->actingAs($admin, 'sanctum')->postJson('/api/orders', $payload + [
            'clientId' => $client->id, 'kodeGroup' => 'GRP-PDF', 'tanggalMulai' => $today,
        ])->assertCreated();

        $tour = $create(['tipe' => 'tour', 'namaOrder' => 'Tour PDF',
            'itinerary' => [['hari' => 1, 'tempatPenjemputan' => 'Hotel Sanur', 'aktivitas' => [['aktivitas' => 'Snorkeling', 'biaya' => 100000]]]],
            'assignments' => [['hari' => 1, 'vehicleId' => $vehicle->id, 'supplierId' => $supplier->id, 'biayaTransportModal' => 300000]]]);
        $layanan = $create(['tipe' => 'layanan', 'subTipe' => 'transfer', 'namaOrder' => 'Transfer PDF',
            'layananDetail' => ['namaTamu' => 'Tamu PDF', 'pickUpPoint' => 'Bandara PDF', 'dropOffPoint' => 'Hotel PDF', 'jumlahPax' => 2, 'biayaModal' => 0]]);
        $ticket = $create(['tipe' => 'ticket', 'namaOrder' => 'Tiket PDF', 'tanggalPemakaian' => $today,
            'ticketRows' => [['namaTiket' => 'Bali Zoo PDF', 'qty' => 2, 'modalSatuan' => 100000]]]);

        // Calls the controller directly: the fake PDF builder records the rendered HTML only when asked for the PDF content.
        $export = function (string $method, array $query, string $view) use ($admin) {
            \Spatie\LaravelPdf\Facades\Pdf::fake();
            $request = \Illuminate\Http\Request::create('/', 'GET', $query);
            $request->setUserResolver(fn () => $admin);

            app(\App\Http\Controllers\ExportController::class)->{$method}($request)->generatePdfContent();
            \Spatie\LaravelPdf\Facades\Pdf::assertViewIs($view);
        };

        // An Order is printed in the layout of its type, with its own details.
        $export('order_pdf', ['id' => $tour->json('id')], 'exports.itinerary_tour');
        \Spatie\LaravelPdf\Facades\Pdf::assertSee(['Snorkeling', 'Hotel Sanur']);
        $export('order_pdf', ['id' => $layanan->json('id')], 'exports.itinerary_service');
        \Spatie\LaravelPdf\Facades\Pdf::assertSee(['Konfirmasi Layanan · Transfer', 'Tamu PDF', 'Bandara PDF', 'Hotel PDF']);
        $export('order_pdf', ['id' => $ticket->json('id')], 'exports.itinerary_ticket');
        \Spatie\LaravelPdf\Facades\Pdf::assertSee(['Bali Zoo PDF']);

        // Reports carry what is in the database, never the sample rows of the design template.
        $export('account_payable_pdf', [], 'exports.account_payable');
        \Spatie\LaravelPdf\Facades\Pdf::assertSee(['Wayan PDF', 'Rp 300.000', 'oleh Kasir Uji']);
        \Spatie\LaravelPdf\Facades\Pdf::assertDontSee(['Made Wirawan (Driver)', 'ORD-0101']);

        \App\Models\InvoiceLine::query()->update(['harga_jual' => 250000]);
        \App\Models\Invoice::query()->update(['status' => 'Sudah Ditagihkan', 'total' => 250000, 'sisa' => 250000]);

        $export('account_receivable_pdf', [], 'exports.account_receivable');
        \Spatie\LaravelPdf\Facades\Pdf::assertSee(['Sisa Piutang', 'PDF Client', 'Rp 250.000']);
        \Spatie\LaravelPdf\Facades\Pdf::assertDontSee(['INV-2026-0101']);

        $export('profit_loss_pdf', [], 'exports.profit_loss');
        \Spatie\LaravelPdf\Facades\Pdf::assertSee([now()->locale('id')->translatedFormat('F Y'), 'Pendapatan', 'Untung']);
        \Spatie\LaravelPdf\Facades\Pdf::assertDontSee(['Rp 98.400.000']);
    }

    public function test_whatsapp_qr_reports_an_unconfigured_gateway_instead_of_crashing(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]), 'sanctum');

        $this->getJson('/api/wa/qr/image')->assertStatus(503);
    }
}
