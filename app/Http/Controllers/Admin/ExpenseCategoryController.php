<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        return view('admin.expenses.categories', [
            'categories' => ExpenseCategory::withCount('expenses')->ordered()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $category = ExpenseCategory::create($data + ['type' => 'operational']);

        AuditLogger::log('create', 'Kategori pengeluaran "'.$category->name.'" ditambahkan', $category);

        return back()->with('success', 'Kategori tersimpan.');
    }

    public function update(Request $request, ExpenseCategory $category)
    {
        $old = $category->toArray();
        $category->update($this->validated($request));

        AuditLogger::log('update', 'Kategori pengeluaran "'.$category->name.'" diperbarui', $category, $old, $category->fresh()->toArray());

        return back()->with('success', 'Perubahan tersimpan.');
    }

    public function destroy(ExpenseCategory $category)
    {
        // BR-18: kategori bawaan & kategori yang masih terpakai tidak bisa dihapus
        abort_if($category->is_locked, 403, 'Kategori bawaan tidak dapat dihapus.');

        if ($category->expenses()->exists()) {
            return back()->with('error', 'Kategori masih dipakai oleh pengeluaran dan tidak dapat dihapus.');
        }

        $category->delete();
        AuditLogger::log('delete', 'Kategori pengeluaran "'.$category->name.'" dihapus', $category);

        return back()->with('success', 'Kategori dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'       => ['required', 'string', 'max:60'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active'  => ['nullable', 'boolean'],
        ], [], ['name' => 'nama kategori', 'sort_order' => 'urutan']);
    }
}