<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WhatsAppTemplate;
use App\Services\OrderService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = collect([
					['nama' => 'QuantumTri', 'email' => 'quantumtri.test@gmail.com', 'role' => UserRole::SuperAdmin, 'password' => '12345',],
					['nama' => 'Admin', 'email' => 'admin@admin.com', 'role' => UserRole::SuperAdmin, 'password' => '12345',],
            ['nama' => 'Made Wirawan', 'email' => 'made.wirawan@meibali.com', 'role' => UserRole::Operator, 'password' => '12345',],
            ['nama' => 'Ayu Kartika', 'email' => 'ayu.kartika@meibali.com', 'role' => UserRole::SuperAdmin, 'password' => '12345',],
            ['nama' => 'Sinta Wulandari', 'email' => 'sinta.wulandari@meibali.com', 'role' => UserRole::Admin, 'password' => '12345',],
        ])->map(fn ($u) => User::create([...$u, 'role' => $u['role']->value, ]));

        $superAdmin = $users->firstWhere('role', UserRole::SuperAdmin->value);

//         $clients = collect([
//             ['kode' => 'CLI-01', 'nama' => 'Sunrise Tour Beijing', 'tipe' => 'Agen', 'kontak' => 'Li Wei', 'telepon' => '+86 10 5566 7788', 'email' => 'booking@sunrisetour.cn', 'kota' => 'Beijing', 'negara' => 'Tiongkok'],
//             ['kode' => 'CLI-02', 'nama' => 'Wanderlust Travel Sydney', 'tipe' => 'Agen', 'kontak' => 'Emma Clarke', 'telepon' => '+61 2 9000 1122', 'email' => 'ops@wanderlustsydney.com.au', 'kota' => 'Sydney', 'negara' => 'Australia'],
//             ['kode' => 'CLI-03', 'nama' => 'PT Nusantara Incentive', 'tipe' => 'Perusahaan', 'kontak' => 'Rani Kusuma', 'telepon' => '+62 21 5140 8899', 'email' => 'rani@nusantara-incentive.co.id', 'kota' => 'Surabaya', 'negara' => 'Indonesia'],
//             ['kode' => 'CLI-04', 'nama' => 'De Jong Family', 'tipe' => 'Individu', 'kontak' => 'Pieter de Jong', 'telepon' => '+31 6 1234 5678', 'email' => 'pieter.dejong@example.nl', 'kota' => 'Amsterdam', 'negara' => 'Belanda'],
//             ['kode' => 'CLI-05', 'nama' => 'Lion City Holidays', 'tipe' => 'Agen', 'kontak' => 'Marcus Tan', 'telepon' => '+65 6222 3344', 'email' => 'marcus@lioncityholidays.sg', 'kota' => 'Singapore', 'negara' => 'Singapura'],
//         ])->map(fn ($c) => Client::create($c));
//
//         $suppliers = collect([
//             ['kode' => 'SUP-01', 'nama' => 'Wayan Sudarsana', 'telepon' => '+62 812 3811 2200', 'tipe_mobil' => 'Toyota Innova Reborn'],
//             ['kode' => 'SUP-02', 'nama' => 'Komang Ariawan', 'telepon' => '+62 813 3755 8842', 'tipe_mobil' => 'Toyota Hiace Commuter'],
//             ['kode' => 'SUP-03', 'nama' => 'Slamet Riyadi', 'telepon' => '+62 812 3390 1177', 'tipe_mobil' => 'Toyota Avanza'],
//             ['kode' => 'SUP-04', 'nama' => 'Hendra Wijaya', 'telepon' => '+62 819 3122 4455', 'tipe_mobil' => 'Isuzu Elf Long'],
//             ['kode' => 'SUP-05', 'nama' => 'Putu Genta Trans', 'telepon' => '+62 811 3980 6621', 'tipe_mobil' => 'Mercedes-Benz Bus Medium'],
//         ])->map(fn ($s) => Supplier::create($s));
//
//         $vehicles = collect([
//             ['nama' => 'Toyota Avanza', 'kapasitas' => 5],
//             ['nama' => 'Toyota Innova Reborn', 'kapasitas' => 6],
//             ['nama' => 'Toyota Hiace Commuter', 'kapasitas' => 14],
//             ['nama' => 'Isuzu Elf Long', 'kapasitas' => 19],
//             ['nama' => 'Mercedes-Benz Bus Medium', 'kapasitas' => 31],
//         ])->map(fn ($v) => Vehicle::create($v));
//
//         $category = ProductCategory::create(['nama' => 'Add-On Umum', 'deskripsi' => 'Dokumentasi, makan siang, tiket atraksi, dsb.']);
//         $products = collect([
//             ['nama' => 'Dokumentasi Drone + Foto', 'harga_jual' => 500000, 'modal' => 300000],
//             ['nama' => 'Paket Makan Siang Lokal', 'harga_jual' => 120000, 'modal' => 70000],
//             ['nama' => 'Tiket Pertunjukan Kecak Uluwatu', 'harga_jual' => 200000, 'modal' => 150000],
//         ])->map(fn ($p) => Product::create([...$p, 'product_category_id' => $category->id]));

        foreach ([
            ['nama' => 'Konfirmasi Supplier', 'kategori' => 'Operasional', 'isi' => 'Halo {{nama_supplier}}, mohon konfirmasi ketersediaan untuk order {{kode_order}} pada {{tanggal}} pukul {{jam}}. Titik jemput: {{tempat_jemput}}. Jumlah tamu: {{jumlah_tamu}}. Mobil: {{tipe_mobil}}. Terima kasih.', 'variabel' => ['nama_supplier', 'kode_order', 'tanggal', 'jam', 'tempat_jemput', 'jumlah_tamu', 'tipe_mobil']],
            ['nama' => 'Reminder Pembayaran', 'kategori' => 'Finance', 'isi' => 'Yth. {{nama_client}}, invoice {{no_invoice}} untuk order {{kode_order}} sebesar {{total}} akan jatuh tempo {{jatuh_tempo}}. Sisa tagihan: {{sisa}}. Mohon konfirmasi setelah transfer. Terima kasih.', 'variabel' => ['nama_client', 'no_invoice', 'kode_order', 'total', 'jatuh_tempo', 'sisa']],
        ] as $t) {
            WhatsAppTemplate::create($t);
        }

        // $this->seedDemoOrders($clients, $suppliers, $vehicles, $products, $superAdmin);
    }

    private function seedDemoOrders($clients, $suppliers, $vehicles, $products, User $actor): void
    {
        $orderService = app(OrderService::class);

        // A Tour with a 2-vehicle day (Request: "1 mobil tidak cukup"), itinerary, and an add-on.
        $orderService->create([
            'tipe' => 'tour',
            'clientId' => $clients->firstWhere('kode', 'CLI-03')->id,
            'namaOrder' => 'Incentive Group — Nusa Penida',
            'kodeGroup' => 'GRP-NUSAPENIDA-0905',
            'tanggalMulai' => now()->addDays(14)->toDateString(),
            'jamMulai' => '07:00',
            'destinasi' => 'Nusa Penida (West & East)',
            'kotaTermasuk' => ['Sanur', 'Nusa Penida'],
            'dewasa' => 18,
            'anak' => 0,
            'catatan' => 'Grup korporat 18 pax. Rooming list & manifest fast boat dikirim H-3.',
            'tamu' => [
                ['nama' => 'Budi Santoso', 'kategori' => 'Dewasa'],
                ['nama' => 'Siti Aminah', 'kategori' => 'Dewasa'],
            ],
            'itinerary' => [
                [
                    'hari' => 1, 'tempatPenjemputan' => 'Hotel area Sanur', 'tempatDropAkhir' => 'Penginapan Nusa Penida',
                    'aktivitas' => [
                        ['aktivitas' => 'Fast boat Sanur–Nusa Penida (grup)', 'biaya' => 9000000, 'supplierTur' => 'CV Nusa Penida Boat'],
                        ['aktivitas' => 'West tour: Kelingking, Angel Billabong, Broken Beach', 'biaya' => 4500000],
                    ],
                ],
                [
                    'hari' => 2, 'tempatPenjemputan' => 'Penginapan Nusa Penida', 'tempatDropAkhir' => 'Hotel area Sanur',
                    'aktivitas' => [
                        ['aktivitas' => 'East tour: Diamond Beach, Atuh', 'biaya' => 4500000],
                        ['aktivitas' => 'Snorkeling Manta Point + fast boat kembali', 'biaya' => 6800000],
                    ],
                ],
            ],
            // Day 1 needs two vehicles for 18 pax — exactly the "1 mobil tidak cukup" scenario.
            'assignments' => [
                ['hari' => 1, 'vehicleId' => $vehicles->firstWhere('nama', 'Toyota Innova Reborn')->id, 'supplierId' => $suppliers->firstWhere('kode', 'SUP-01')->id, 'biayaTransportModal' => 800000],
                ['hari' => 1, 'vehicleId' => $vehicles->firstWhere('nama', 'Isuzu Elf Long')->id, 'supplierId' => $suppliers->firstWhere('kode', 'SUP-04')->id, 'biayaTransportModal' => 900000],
                ['hari' => 2, 'vehicleId' => null, 'supplierId' => null, 'biayaTransportModal' => 0],
            ],
            'addOns' => [
                ['produkId' => $products->firstWhere('nama', 'Dokumentasi Drone + Foto')->id, 'qty' => 1, 'modal' => 300000],
                ['produkId' => $products->firstWhere('nama', 'Paket Makan Siang Lokal')->id, 'qty' => 18, 'modal' => 70000, 'catatan' => 'Makan siang hari 1 & 2'],
            ],
        ], $actor);

        // A Check In with a supplier attached to the base Biaya (Modal) — Layanan Tagihan bucket.
        $orderService->create([
            'tipe' => 'layanan',
            'subTipe' => 'check_in',
            'clientId' => $clients->firstWhere('kode', 'CLI-01')->id,
            'namaOrder' => 'Check In — James Whitfield',
            'kodeGroup' => 'GRP-CHK-0818',
            'tanggalMulai' => now()->addDays(3)->toDateString(),
            'jamMulai' => '14:30',
            'kota' => 'Kuta',
            'dewasa' => 2,
            'anak' => 0,
            'catatan' => 'Jemput kedatangan internasional, antar ke hotel Seminyak.',
            'tamu' => [
                ['nama' => 'James Whitfield', 'kategori' => 'Dewasa'],
                ['nama' => 'Olivia Whitfield', 'kategori' => 'Dewasa'],
            ],
            'layananDetail' => [
                'namaTamu' => 'James Whitfield',
                'hotelDropOff' => 'The Legian Seminyak',
                'infoFlight' => 'QF43 SYD–DPS, ETA 14:05, Terminal Internasional',
                'jumlahPax' => 2,
                'biayaModal' => 350000,
                'supplierNama' => 'CV Bali Ground Handling',
            ],
        ], $actor);
    }
}
