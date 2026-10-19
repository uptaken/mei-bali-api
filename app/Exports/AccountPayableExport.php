<?php
namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel dari view blade (tabel HTML). Kolom uang dikirim sebagai angka asli lalu diberi format ribuan,
 * jadi bisa dijumlah dan diurutkan di Excel; `$moneyColumns` berisi huruf kolom, mis. ['E', 'F'].
 */
class AccountPayableExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithStyles
{
	public function __construct(private string $view, private array $arr, private array $moneyColumns = []){}

	public function view(): View
	{
		return view($this->view, $this->arr);
	}

	public function columnFormats(): array
	{
		return collect($this->moneyColumns)->mapWithKeys(fn ($col) => [$col => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1])->all();
	}

	public function styles(Worksheet $sheet): array
	{
		return [1 => ['font' => ['bold' => true]]];
	}
}
