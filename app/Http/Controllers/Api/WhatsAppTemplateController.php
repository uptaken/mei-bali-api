<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\Request;

class WhatsAppTemplateController extends Controller
{
    public function index()
    {
        return WhatsAppTemplate::orderBy('nama')->get();
    }

    public function store(Request $request)
    {
        return response()->json(WhatsAppTemplate::create($this->validated($request)), 201);
    }

    public function show(WhatsAppTemplate $whatsappTemplate)
    {
        return $whatsappTemplate;
    }

    public function update(Request $request, WhatsAppTemplate $whatsappTemplate)
    {
        $whatsappTemplate->update($this->validated($request, true));
        return $whatsappTemplate;
    }

    public function destroy(WhatsAppTemplate $whatsappTemplate)
    {
        $whatsappTemplate->delete();
        return response()->json(status: 204);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        return $request->validate([
            'nama' => [$required, 'string'],
            'kategori' => ['nullable', 'string'],
            'isi' => [$required, 'string'],
            'variabel' => ['nullable', 'array'],
        ]);
    }
}
