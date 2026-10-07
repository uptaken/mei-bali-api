<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PayablesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ports src/pages/payables/PayableList.tsx + PayableDetail.tsx. Rows are always derived, never stored. */
class PayableController extends Controller
{
    public function __construct(private readonly PayablesService $payables) {}

    /** GET /api/payables */
    public function index(Request $request): JsonResponse
    {
        $groups = $this->payables->derive();

        $tipe = $request->query('tipe_tagihan');
        $status = $request->query('status');
        $supplierId = $request->query('supplier_id');
        $q = trim((string) $request->query('q', ''));

        $filtered = $groups
            ->when($tipe, fn ($c) => $c->where('tipeTagihan', $tipe))
            ->when($status, fn ($c) => $c->where('status', $status))
            ->when($supplierId, fn ($c) => $c->where('supplierId', $supplierId))
            ->when($q !== '', fn ($c) => $c->filter(fn ($g) => str_contains(mb_strtolower($g['kode'].' '.$g['namaOrder'].' '.$g['namaTamu'].' '.$g['supplierNama']), mb_strtolower($q))))
            ->values();

        $live = $filtered->reject(fn ($g) => $g['cancelled']);
        $belum = $live->where('status', 'Belum Bayar');
        $sudah = $live->where('status', 'Bayar');

        return response()->json([
            'data' => $filtered,
            'summary' => [
                'perluDibayar' => $belum->sum('modal'),
                'perluCount' => $belum->count(),
                'sudahDibayar' => $sudah->sum('modal'),
                'sudahCount' => $sudah->count(),
                'supplierCount' => $live->map(fn ($g) => $g['tipeTagihan'].':'.($g['supplierId'] ?? mb_strtolower($g['supplierNama'])))->unique()->count(),
                'overdueCount' => $belum->filter(fn ($g) => $this->payables->daysOutstanding($g) > 7)->count(),
            ],
        ]);
    }

    /** GET /api/payables/{id} — {id} is the derived Tagihan id, e.g. "12~transportasi~3". */
    public function show(string $id): JsonResponse
    {
        $group = $this->payables->derive()->firstWhere('id', $id);
        abort_if(! $group, 404, 'Tagihan tidak ditemukan.');

        return response()->json($group);
    }

    /** POST /api/payables/{id}/mark-paid */
    public function markPaid(string $id): JsonResponse
    {
        $group = $this->payables->derive()->firstWhere('id', $id);
        abort_if(! $group, 404, 'Tagihan tidak ditemukan.');

        $this->payables->markPaid($group);

        return response()->json($this->payables->derive()->firstWhere('id', $id));
    }

    /** POST /api/payables/mark-paid-bulk — body: {"ids": ["1~tour~x", ...]} */
    public function markPaidBulk(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array']])['ids'];
        $all = $this->payables->derive();

        foreach ($ids as $id) {
            $group = $all->firstWhere('id', $id);
            if ($group) {
                $this->payables->markPaid($group);
            }
        }

        return response()->json(['message' => 'Tagihan terpilih ditandai lunas.']);
    }
}
