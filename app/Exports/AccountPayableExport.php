<?php
namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromView;

class AccountPayableExport implements FromView
{
	private $view;
	private $arr;

	public function __construct($view, $arr){
		$this->view = $view;
		$this->arr = $arr;
	}

	public function view(): View
	{
		return view($this->view, $this->arr);
	}
}