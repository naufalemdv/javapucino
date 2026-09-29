<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Material;
use App\Models\StockMovement;
use App\Services\AuditLogger;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $data  = $this->validated($request);
        $stock = (float) $data['stock'];
        $cost  = $data['unit_cost'] !== null ? (float) $data['unit_cost'] : null;

        $material = DB::transaction(function () use ($data, $stock, $cost) {
            $material = Material::create([...$data, 'stock' => 0]);

            if ($stock > 0) {
                $movement = StockService::moveMaterial($material, $stock, 'adjustment', 'Stok awal bahan', $cost);

                // Stok awal dianggap pembelian hanya bila harga satuan diisi
                if ($cost !== null && $cost > 0) {
                    Expense::createFromRestock($movement, $material, $cost);
                }
            }

            return $material;
        });

        AuditLogger::log('create', 'Bahan "'.$material->name.'" ditambahkan', $material);

        return redirect()->route('admin.materials.index')->with('success', 'Bahan tersimpan.');
    }

    public function edit(Material $material)
    {
        return view('admin.materials.form', compact('material'));
    }

    public function update(Request $request, Material $material)
    {
        $data = $this->validated($request);
        $old  = $material->toArray();
        $diff = (float) $data['stock'] - (float) $material->stock;
        unset($data['stock']);

        $material->update($data);

        if (abs($diff) > 0.001) {
            StockService::moveMaterial($material, $diff, 'adjustment', 'Penyesuaian stok bahan dari CMS');
        }

        AuditLogger::log('update', 'Bahan "'.$material->name.'" diperbarui', $material, $old, $material->fresh()->toArray());

        return redirect()->route('admin.materials.index')->with('success', 'Perubahan bahan tersimpan.');
    }

    /**
     * Restock bahan. Satu aksi menulis stok + pengeluaran dalam satu transaksi
     * agar nilai uang dan jumlah stok tidak pernah berpisah.
     */
    public function restock(Request $request, Material $material)
    {
        $data = $request->validate([
            'qty'       => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'note'      => ['nullable', 'string', 'max:255'],
        ], [], [
            'qty'       => 'jumlah masuk',
            'unit_cost' => 'harga satuan',
        ]);

        $qty  = (float) $data['qty'];
        $cost = (float) $data['unit_cost'];

        DB::transaction(function () use ($material, $qty, $cost, $data) {
            $movement = StockService::moveMaterial(
                $material,
                $qty,
                'restock',
                $data['note'] ?? 'Restock bahan',
                $cost,
            );

            Expense::createFromRestock($movement, $material, $cost);

            // Simpan harga terakhir sebagai nilai default form berikutnya
            $material->forceFill(['unit_cost' => $cost])->save();
        });

        $total = round($qty * $cost);

        AuditLogger::log(
            'restock',
            'Restock '.$material->name.' +'.angka($qty).' '.$material->unit.' · '.rupiah($total),
            $material,
        );

        return redirect()->route('admin.materials.index')
            ->with('success', 'Stok '.$material->name.' bertambah '.angka($qty).' '.$material->unit.' · '.rupiah($total).' tercatat di Pengeluaran.');
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
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ], [], [
            'name'      => 'nama bahan',
            'unit'      => 'satuan',
            'stock'     => 'stok',
            'min_stock' => 'batas minimum',
            'unit_cost' => 'harga satuan',
        ]);
    }
}