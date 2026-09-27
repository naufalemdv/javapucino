<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /** FR-18 */
    public function index(Request $request)
    {
        $products = Product::with('category')
            ->when($request->integer('kategori'), fn ($q, $id) => $q->where('category_id', $id))
            ->search($request->string('q')->toString())
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.products.index', [
            'products'   => $products,
            'categories' => Category::ordered()->get(),
            'catId'      => $request->integer('kategori'),
            'q'          => $request->string('q')->toString(),
            'total'      => Product::count(),
        ]);
    }

    public function create()
    {
        return view('admin.products.form', [
            'product'    => new Product(['is_active' => true, 'stock' => 0]),
            'categories' => Category::ordered()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $product = Product::create($data);

        if ($product->stock > 0) {
            StockService::moveProduct($product->fill(['stock' => 0]), $data['stock'], 'adjustment', null, 'Stok awal produk baru');
        }

        AuditLogger::log('create', 'Produk "'.$product->name.'" ditambahkan', $product, null, $product->toArray());

        return redirect()->route('admin.products.index')->with('success', 'Menu berhasil disimpan.');
    }

    public function edit(Product $product)
    {
        return view('admin.products.form', [
            'product'    => $product,
            'categories' => Category::ordered()->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);
        $old  = $product->toArray();

        // Gambar lama dihapus bila diganti atau dihapus admin
        if (array_key_exists('image_path', $data) && $product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $newStock = (int) $data['stock'];
        $diff     = $newStock - (int) $product->stock;
        unset($data['stock']);

        $product->update($data);

        // BR-12: setiap perubahan stok tercatat
        if ($diff !== 0) {
            StockService::moveProduct($product, $diff, 'adjustment', null, 'Penyesuaian stok dari CMS');
        }

        AuditLogger::log('update', 'Produk "'.$product->name.'" diperbarui', $product, $old, $product->fresh()->toArray());

        return redirect()->route('admin.products.index')->with('success', 'Perubahan menu tersimpan.');
    }

    /** Soft delete (BR-09). */
    public function destroy(Product $product)
    {
        $product->delete();

        AuditLogger::log('delete', 'Produk "'.$product->name.'" dihapus (soft delete)', $product);

        return back()->with('success', 'Menu dihapus. Riwayat transaksi tetap utuh.');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'sku'            => ['nullable', 'string', 'max:30', 'unique:products,sku'.($product ? ','.$product->id : '')],
            'category_id'    => ['required', 'exists:categories,id'],
            'image'          => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'price'          => ['required', 'numeric', 'min:0'],
            'hpp'            => ['required', 'numeric', 'min:0'],
            'stock'          => ['required', 'integer', 'min:0'],
            'is_best_seller' => ['nullable', 'boolean'],
            'is_active'      => ['required', 'boolean'],
        ], [], [
            'name' => 'nama menu', 'category_id' => 'kategori', 'price' => 'harga jual',
            'hpp' => 'HPP', 'stock' => 'stok', 'image' => 'gambar menu',
        ]);

        $data['is_best_seller'] = $request->boolean('is_best_seller');

        unset($data['image']);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        } elseif ($request->boolean('remove_image')) {
            $data['image_path'] = null;
        }

        return $data;
    }
}
