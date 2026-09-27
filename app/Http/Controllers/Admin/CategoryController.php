<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /** FR-19 */
    public function index()
    {
        return view('admin.categories.index', [
            'categories' => Category::withCount('products')->ordered()->get(),
        ]);
    }

    public function create()
    {
        return view('admin.categories.form', [
            'category' => new Category(['icon' => '☕', 'is_active' => true, 'sort_order' => Category::count() + 1]),
        ]);
    }

    public function store(Request $request)
    {
        $category = Category::create($this->validated($request));

        AuditLogger::log('create', 'Kategori "'.$category->name.'" ditambahkan', $category);

        return redirect()->route('admin.categories.index')->with('success', 'Kategori tersimpan.');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $old = $category->toArray();
        $category->update($this->validated($request, $category));

        AuditLogger::log('update', 'Kategori "'.$category->name.'" diperbarui', $category, $old, $category->toArray());

        return redirect()->route('admin.categories.index')->with('success', 'Perubahan kategori tersimpan.');
    }

    /** BR-10: kategori dengan produk aktif tidak dapat dihapus. */
    public function destroy(Category $category)
    {
        if ($category->products()->where('is_active', true)->exists()) {
            return back()->with('error', 'Kategori masih memiliki menu aktif (BR-10). Pindahkan atau nonaktifkan menunya dulu.');
        }

        $category->delete();

        AuditLogger::log('delete', 'Kategori "'.$category->name.'" dihapus', $category);

        return back()->with('success', 'Kategori dihapus.');
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name'       => ['required', 'string', 'max:80', 'unique:categories,name'.($category ? ','.$category->id : '')],
            'icon'       => ['nullable', 'string', 'max:20'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'is_active'  => ['required', 'boolean'],
        ], [], ['name' => 'nama kategori', 'sort_order' => 'urutan tampil']);
    }
}
