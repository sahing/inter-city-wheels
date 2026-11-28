<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingTypeCarRate;
use App\Models\CarType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CarTypeAdminController extends Controller
{
    private const MAX_IMAGE_BYTES = 30720; // 30 KB strict target

    private const DEFAULT_IMAGE_URL = 'https://images.unsplash.com/photo-1549399542-7e3f8b79c341?auto=format&fit=crop&w=400&q=80';
    /**
     * List all car types / fleet categories.
     */
    public function index(Request $request)
    {
        $query = CarType::orderBy('order_index')->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('category_key', 'like', "%{$s}%")
                    ->orWhere('models', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        return response()->json([
            'success' => true,
            'car_types' => $query->get(),
        ]);
    }

    /**
     * Store a new car type.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'category_key' => 'required|string|max:60|unique:car_types,category_key',
            'badge' => 'nullable|string|max:60',
            'models' => 'required|string|max:255',
            'seats' => 'required|max:40',
            'luggage' => 'nullable|max:40',
            'rate_per_km' => 'required|numeric|min:1',
            'extra_km_rate' => 'nullable|numeric|min:0',
            'base_fare_min' => 'nullable|numeric|min:0',
            'image_url' => 'nullable|string|max:500',
            'order_index' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $categoryKey = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $validated['category_key']));

        $carType = CarType::create([
            'name' => trim($validated['name']),
            'category_key' => $categoryKey,
            'badge' => $validated['badge'] ? trim($validated['badge']) : 'Popular',
            'models' => trim($validated['models']),
            'seats' => trim($validated['seats']),
            'luggage' => trim($validated['luggage']),
            'rate_per_km' => (float) $validated['rate_per_km'],
            'extra_km_rate' => isset($validated['extra_km_rate']) ? (float) $validated['extra_km_rate'] : (float) $validated['rate_per_km'],
            'base_fare_min' => (float) ($validated['base_fare_min'] ?? 800),
            'image_url' => ! empty($validated['image_url']) ? trim($validated['image_url']) : self::DEFAULT_IMAGE_URL,
            'order_index' => (int) ($validated['order_index'] ?? 0),
            'is_active' => $request->has('is_active') ? (bool) $request->is_active : true,
        ]);

        $this->syncOneWayBookingTypeRate($carType);

        return response()->json([
            'success' => true,
            'message' => "Car Type '{$carType->name}' added successfully!",
            'car_type' => $carType,
        ], 201);
    }

    /**
     * Show single car type.
     */
    public function show($id)
    {
        $carType = CarType::findOrFail($id);

        return response()->json([
            'success' => true,
            'car_type' => $carType,
        ]);
    }

    /**
     * Update an existing car type.
     */
    public function update(Request $request, $id)
    {
        $carType = CarType::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'category_key' => 'sometimes|required|string|max:60|unique:car_types,category_key,'.$id,
            'badge' => 'nullable|string|max:60',
            'models' => 'sometimes|required|string|max:255',
            'seats' => 'sometimes|required|max:40',
            'luggage' => 'nullable|max:40',
            'rate_per_km' => 'sometimes|required|numeric|min:1',
            'extra_km_rate' => 'nullable|numeric|min:0',
            'base_fare_min' => 'nullable|numeric|min:0',
            'image_url' => 'nullable|string|max:500',
            'order_index' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['category_key'])) {
            $validated['category_key'] = strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $validated['category_key']));
        }

        $carType->update($validated);
        $this->syncOneWayBookingTypeRate($carType);

        return response()->json([
            'success' => true,
            'message' => "Car Type '{$carType->name}' updated successfully!",
            'car_type' => $carType,
        ]);
    }

    /**
     * Toggle active/inactive status.
     */
    public function toggleStatus($id)
    {
        $carType = CarType::findOrFail($id);
        $carType->is_active = ! $carType->is_active;
        $carType->save();
        $this->syncOneWayBookingTypeRate($carType);

        return response()->json([
            'success' => true,
            'message' => "Car Type '{$carType->name}' is now ".($carType->is_active ? 'Active' : 'Inactive'),
            'car_type' => $carType,
        ]);
    }

    /**
     * Bulk update per-km rates, extra km rates, min base fares, and active flags.
     */
    public function bulkUpdateRates(Request $request)
    {
        $validated = $request->validate([
            'rates' => 'required|array|min:1',
            'rates.*.id' => 'required|integer|exists:car_types,id',
            'rates.*.rate_per_km' => 'required|numeric|min:1',
            'rates.*.extra_km_rate' => 'nullable|numeric|min:0',
            'rates.*.base_fare_min' => 'nullable|numeric|min:0',
            'rates.*.extra_hour_rate' => 'nullable|numeric|min:0',
            'rates.*.is_active' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['rates'] as $row) {
                $carType = CarType::findOrFail($row['id']);
                $ratePerKm = (float) $row['rate_per_km'];
                $carType->rate_per_km = $ratePerKm;
                $carType->extra_km_rate = isset($row['extra_km_rate'])
                    ? (float) $row['extra_km_rate']
                    : $ratePerKm;
                if (array_key_exists('base_fare_min', $row) && $row['base_fare_min'] !== null) {
                    $carType->base_fare_min = (float) $row['base_fare_min'];
                }
                if (array_key_exists('is_active', $row)) {
                    $carType->is_active = (bool) $row['is_active'];
                }
                $carType->save();
                $extraHr = array_key_exists('extra_hour_rate', $row) && $row['extra_hour_rate'] !== null
                    ? (float) $row['extra_hour_rate']
                    : null;
                $this->syncOneWayBookingTypeRate($carType, $extraHr);
            }
        });

        $updatedCount = count($validated['rates']);

        return response()->json([
            'success' => true,
            'message' => "Updated rates for {$updatedCount} car type(s) successfully!",
            'car_types' => CarType::orderBy('order_index')->get(),
            'booking_type_rates' => Schema::hasTable('booking_type_car_rates') ? BookingTypeCarRate::all() : [],
        ]);
    }

    private function syncOneWayBookingTypeRate(CarType $carType, ?float $extraHourRate = null): void
    {
        if (! Schema::hasTable('booking_type_car_rates')) {
            return;
        }

        $attrs = [
            'base_fare' => (float) ($carType->base_fare_min ?? 0),
            'rate_per_km' => (float) ($carType->rate_per_km ?? 0),
            'extra_km_rate' => (float) ($carType->extra_km_rate ?? $carType->rate_per_km ?? 0),
            'is_active' => (bool) $carType->is_active,
        ];

        if ($extraHourRate !== null) {
            $attrs['extra_hour_rate'] = $extraHourRate;
        }

        BookingTypeCarRate::updateOrCreate(
            [
                'booking_type' => 'oneway',
                'car_type_id' => $carType->id,
            ],
            $attrs
        );
    }

    /**
     * Upload a vehicle image file for a car type, cropping and compressing it <= 30 KB.
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|file|mimes:png,jpg,jpeg,webp,gif|max:5120',
            'crop_x' => 'nullable|numeric|min:0',
            'crop_y' => 'nullable|numeric|min:0',
            'crop_w' => 'nullable|numeric|min:1',
            'crop_h' => 'nullable|numeric|min:1',
        ]);

        $file = $request->file('image');
        $dir = public_path('uploads/car-types');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $baseName = 'car-type-'.time().'-'.substr(bin2hex(random_bytes(4)), 0, 8);
        $hasCrop = $request->filled('crop_w') && $request->filled('crop_h');
        $origSize = (int) ($file->getSize() ?: 0);
        $origExt = strtolower($file->getClientOriginalExtension() ?: '');

        // Fast-path: browser already cropped & compressed cleanly <= 30 KB
        if (! $hasCrop && $origSize > 0 && $origSize <= self::MAX_IMAGE_BYTES && in_array($origExt, ['webp', 'jpg', 'jpeg', 'png'], true)) {
            $filename = $baseName.'.'.$origExt;
            $file->move($dir, $filename);
            $savedPath = $dir.'/'.$filename;
            $info = @getimagesize($savedPath);

            return response()->json([
                'success' => true,
                'image_url' => '/uploads/car-types/'.$filename,
                'size_bytes' => filesize($savedPath) ?: $origSize,
                'size_kb' => round((filesize($savedPath) ?: $origSize) / 1024, 2),
                'width' => $info[0] ?? null,
                'height' => $info[1] ?? null,
                'message' => 'Car type image uploaded under 30 KB!',
            ]);
        }

        $cropRect = $hasCrop ? [
            'x' => (float) $request->input('crop_x', 0),
            'y' => (float) $request->input('crop_y', 0),
            'w' => (float) $request->input('crop_w'),
            'h' => (float) $request->input('crop_h'),
        ] : null;

        try {
            $saved = $this->cropAndCompressUnderLimit(
                $file->getRealPath(),
                $dir.'/'.$baseName,
                self::MAX_IMAGE_BYTES,
                $cropRect
            );
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $filename = basename($saved['path']);

        return response()->json([
            'success' => true,
            'image_url' => '/uploads/car-types/'.$filename,
            'size_bytes' => $saved['size_bytes'],
            'size_kb' => round($saved['size_bytes'] / 1024, 2),
            'width' => $saved['width'],
            'height' => $saved['height'],
            'message' => 'Car type image cropped & optimized to '.round($saved['size_bytes'] / 1024, 1).' KB!',
        ]);
    }

    /**
     * Crop and iteratively downscale/compress an image via GD until <= maxBytes.
     *
     * @param  array{x: float, y: float, w: float, h: float}|null  $cropRect
     * @return array{path: string, size_bytes: int, width: int, height: int}
     */
    private function cropAndCompressUnderLimit(string $sourcePath, string $destBasePath, int $maxBytes, ?array $cropRect): array
    {
        $raw = @file_get_contents($sourcePath);
        if ($raw === false) {
            throw new \RuntimeException('Could not read uploaded image file.');
        }

        $src = @imagecreatefromstring($raw);
        if (! $src) {
            throw new \RuntimeException('Unsupported or corrupted image file.');
        }

        try {
            $srcW = imagesx($src);
            $srcH = imagesy($src);

            $sx = $cropRect ? max(0, min($srcW - 1, (int) round($cropRect['x']))) : 0;
            $sy = $cropRect ? max(0, min($srcH - 1, (int) round($cropRect['y']))) : 0;
            $sw = $cropRect ? max(1, min($srcW - $sx, (int) round($cropRect['w']))) : $srcW;
            $sh = $cropRect ? max(1, min($srcH - $sy, (int) round($cropRect['h']))) : $srcH;

            $useWebp = function_exists('imagewebp');
            $ext = $useWebp ? 'webp' : 'jpg';
            $destPath = $destBasePath.'.'.$ext;

            foreach ([480, 380, 320, 260, 200, 160] as $maxWidth) {
                $targetW = min($maxWidth, $sw);
                $targetH = max(1, (int) round($targetW * ($sh / $sw)));

                $canvas = imagecreatetruecolor($targetW, $targetH);
                $white = imagecolorallocate($canvas, 255, 255, 255);
                imagefill($canvas, 0, 0, $white);
                imagecopyresampled($canvas, $src, 0, 0, $sx, $sy, $targetW, $targetH, $sw, $sh);

                foreach ([82, 70, 58, 46, 34, 24, 15] as $quality) {
                    if ($useWebp) {
                        imagewebp($canvas, $destPath, $quality);
                    } else {
                        imagejpeg($canvas, $destPath, $quality);
                    }
                    clearstatcache(true, $destPath);
                    $size = @filesize($destPath);
                    if ($size !== false && $size <= $maxBytes) {
                        imagedestroy($canvas);

                        return [
                            'path' => $destPath,
                            'size_bytes' => $size,
                            'width' => $targetW,
                            'height' => $targetH,
                        ];
                    }
                }

                imagedestroy($canvas);
            }

            clearstatcache(true, $destPath);
            $finalSize = (int) (@filesize($destPath) ?: 0);
            if ($finalSize > 0 && $finalSize <= $maxBytes) {
                return [
                    'path' => $destPath,
                    'size_bytes' => $finalSize,
                    'width' => 160,
                    'height' => max(1, (int) round(160 * ($sh / $sw))),
                ];
            }

            @unlink($destPath);
            throw new \RuntimeException('Could not compress image under 30 KB cleanly.');
        } finally {
            imagedestroy($src);
        }
    }

    /**
     * Delete car type.
     */
    public function destroy($id)
    {
        $carType = CarType::findOrFail($id);
        $name = $carType->name;
        $carType->delete();

        return response()->json([
            'success' => true,
            'message' => "Car Type '{$name}' deleted successfully!",
        ]);
    }
}
