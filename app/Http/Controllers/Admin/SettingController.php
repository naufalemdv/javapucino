<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /** FR-26 */
    public function index()
    {
        return view('admin.settings', ['s' => setting(), 'slides' => board_slides()]);
    }

    public function update(Request $request)
{
    $data = $request->validate([
        'store_name' => ['required', 'string', 'max:100'],
        'store_address' => ['nullable', 'string', 'max:255'],
        'store_phone' => ['nullable', 'string', 'max:40'],
        'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        'paper_width' => ['required', 'in:58mm,80mm'],
        'qris_nmid' => ['nullable', 'string', 'max:50'],
        'qris_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        'receipt_footer' => ['nullable', 'string', 'max:255'],
        'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:999'],
        'board_slide_seconds' => ['required', 'integer', 'min:3', 'max:120'],
    ], [], [
        'store_name' => 'nama toko', 'tax_percent' => 'pajak',
        'paper_width' => 'lebar kertas', 'low_stock_threshold' => 'ambang stok menipis',
        'board_slide_seconds' => 'durasi per gambar', 'qris_image' => 'gambar QRIS',
    ]);

    // Berkas QRIS disimpan terpisah, bukan lewat loop di bawah.
    $qrisFile = $request->file('qris_image');
    unset($data['qris_image']);

    $old = setting();

    foreach ($data as $key => $value) {
        Setting::put($key, $value ?? '');
    }

    if ($qrisFile) {
        $oldPath = $old['qris_image'] ?? null;
        $path = $qrisFile->store('qris', 'public');
        Setting::put('qris_image', $path);

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        $data['qris_image'] = $path;
    }

    Setting::flush();

    AuditLogger::log('update', 'Pengaturan toko diperbarui', null, $old, $data);

    return back()->with('success', 'Pengaturan tersimpan.');
    }   

    /** Tambah gambar slide layar pelanggan (bisa beberapa sekaligus). */
    public function slideStore(Request $request)
    {
        $request->validate([
            'images'   => ['required', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [], ['images' => 'gambar', 'images.*' => 'gambar']);

        $slides = board_slides();
        foreach ($request->file('images') as $file) {
            $slides[] = $file->store('board', 'public');
        }
        $this->saveSlides($slides);

        AuditLogger::log('update', count($request->file('images')).' gambar layar pelanggan ditambahkan');

        return back()->with('success', 'Gambar layar pelanggan ditambahkan.');
    }

    /** Geser urutan slide: arah -1 (naik) atau 1 (turun). */
    public function slideMove(Request $request, int $index)
    {
        $dir    = $request->integer('dir') < 0 ? -1 : 1;
        $slides = board_slides();
        $target = $index + $dir;

        if (isset($slides[$index], $slides[$target])) {
            [$slides[$index], $slides[$target]] = [$slides[$target], $slides[$index]];
            $this->saveSlides($slides);
        }

        return back();
    }

    public function slideDestroy(int $index)
    {
        $slides = board_slides();

        if (isset($slides[$index])) {
            Storage::disk('public')->delete($slides[$index]);
            unset($slides[$index]);
            $this->saveSlides(array_values($slides));

            AuditLogger::log('delete', 'Gambar layar pelanggan dihapus');
        }

        return back()->with('success', 'Gambar dihapus.');
    }

    private function saveSlides(array $slides): void
    {
        Setting::put('board_slides', json_encode(array_values($slides)));
        Setting::flush();
    }
}
