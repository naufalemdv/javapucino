<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\StockMovement;
use App\Services\AuditLogger;
use App\Services\StockService;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    /** FR-20 */
    public function index()
    {
        return view('admin.materials.index', [
            'materials' => Material::orderBy('name')->get(),
            'movements' => StockMovement::with(['product', 'material'])->latest()->limit(14)->get(),
        ]);
    }

    public function create()
    {
        return view('admin.materials.form', ['material' => new Material(['unit' => 'gram'])]);
    }

    public function store(Request $request)
    {
        $data     = $this->validated($request);
        $stock    = (float) $data['stock'];
        $material = Material::create([...$data, 'stock' => 0]);

        if ($stock > 0) {
            StockService::moveMaterial($material, $stock, 'adjustment', 'Stok awal bahan');
        }

        AuditLogger::log('create', 'Bahan "'.$material->name.'" ditambahkan', $material);

        return redirect()->route('admin.materials.index')->with('success', 'Bahan tersimpan.');
    }

    public function edit(Material $material)
    {
        return view('admin.materials.form', compact('material'));
    }

    public function update(Request $request, Material $material)
    {
        $data  = $this->validated($request);
        $old   = $material->toArray();
        $diff  = (float) $data['stock'] - (float) $material->stock;
        unset($data['stock']);

        $material->update($data);

        if (abs($diff) > 0.001) {
            StockService::moveMaterial($material, $diff, 'adjustment', 'Penyesuaian stok bahan dari CMS');
        }

        AuditLogger::log('update', 'Bahan "'.$material->name.'" diperbarui', $material, $old, $material->fresh()->toArray());

        return redirect()->route('admin.materials.index')->with('success', 'Perubahan bahan tersimpan.');
    }

    /** Restock bahan. */
    public function restock(Request $request, Material $material)
    {
        $data = $request->validate([
            'qty'  => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['qty' => 'jumlah masuk']);

        StockService::moveMaterial($material, (float) $data['qty'], 'restock', $data['note'] ?? 'Restock bahan');

        AuditLogger::log('restock', 'Restock '.$material->name.' +'.angka($data['qty']).' '.$material->unit, $material);

        return redirect()->route('admin.materials.index')
            ->with('success', 'Stok '.$material->name.' bertambah '.angka($data['qty']).' '.$material->unit.'.');
    }

    public function destroy(Material $material)
    {
        $material->delete();

        AuditLogger::log('delete', 'Bahan "'.$material->name.'" dihapus', $material);

        return back()->with('success', 'Bahan dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'unit'      => ['required', 'in:gram,ml,liter,kg,pcs'],
            'stock'     => ['required', 'numeric', 'min:0'],
            'min_stock' => ['required', 'numeric', 'min:0'],
        ], [], ['name' => 'nama bahan', 'unit' => 'satuan', 'stock' => 'stok', 'min_stock' => 'batas minimum']);
    }
}
