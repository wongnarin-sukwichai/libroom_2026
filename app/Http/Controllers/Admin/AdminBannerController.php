<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminBannerController extends Controller
{
    public function index()
    {
        return response()->json(
            Banner::orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5MB
        ]);

        $path = $request->file('image')->store('banners', 'public');

        $maxOrder = Banner::max('sort_order') ?? 0;

        $banner = Banner::create([
            'image_path' => $path,
            'sort_order' => $maxOrder + 1,
            'status'     => '0',
        ]);

        return response()->json($banner);
    }

    /** ใช้ตั้งลำดับใหม่ทั้งชุด (ลากเรียง) หรือ toggle เปิด/ปิดทีละอัน */
    public function update(Request $request, Banner $banner)
    {
        $data = $request->validate([
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'status'     => ['sometimes', 'in:0,1'],
        ]);

        $banner->update($data);

        return response()->json($banner);
    }

    /** จัดเรียงลำดับใหม่ทั้งชุดในทีเดียว ส่ง ids ตามลำดับที่ต้องการ */
    public function reorder(Request $request)
    {
        $data = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer', 'exists:banners,id'],
        ]);

        foreach ($data['ids'] as $i => $id) {
            Banner::where('id', $id)->update(['sort_order' => $i]);
        }

        return response()->json(['message' => 'เรียงลำดับแล้ว']);
    }

    public function destroy(Banner $banner)
    {
        Storage::disk('public')->delete($banner->image_path);
        $banner->delete();

        return response()->json(['message' => 'ลบแล้ว']);
    }
}
