<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // Toutes les catégories racines (bulles)
    public function index()
    {
        $categories = Category::whereNull('parent_id')->get();
        $albums = Category::whereNotNull('parent_id')->with('parent')->get();
        
        foreach ($albums as $album) {
            $dir = "photos/{$album->parent->slug}/{$album->slug}";

            $files = Storage::disk('public')->files($dir);
            $first = $files[0] ?? null;

            $album['cover'] = $first ? asset('storage/' . $first) : null;
            $album['parent_slug'] = $album->parent->slug;
            $album['parent_name'] = $album->parent->name;

            unset($album->parent);
        }
        return response()->json(['categories' => $categories, 'albums' => $albums]);
    }

    // Une catégorie et ses enfants (fragments)
    public function show(string $slug)
    {
        $album = Category::where('slug', $slug)->with('parent')->firstOrFail();

        $path = storage_path('app/public/photos/' . $album->parent->slug . '/' . $album->slug);

        if (!File::exists($path)) {
            return response()->json([
                'album' => $album,
                'photos' => [],
            ]);
        }

        $files = collect(File::files($path))->map(function ($file) use ($album) {
            return [
                'name' => $file->getFilename(),
                'url' => asset("storage/photos/{$album->parent->slug}/{$album->slug}/" . $file->getFilename()),
                'size_kb' => round($file->getSize() / 1024, 2),
            ];
        });

        return response()->json([
            'album' => $album,
            'photos' => $files,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:255']);

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'parent_id' => $request->parent_id ?? null
        ]);

        return response()->json($category, 201);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        $category->name = $request->name;
        if($request->has('parent_id')) {
            $category->parent_id = $request->parent_id;
        }
        $category->save();

        return response()->json(['success' => true, 'category' => $category]);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json(['success' => true]);
    }

    public function addPhoto(Request $request, $id)
    {
        $request->validate([
            'photos' => 'required|array'
        ]);

        $album = Category::findOrFail($id);
        $catFolder = $album->parent->slug;
        $subFolder = $album->slug;
        $targetDir = storage_path('app/public/photos/' . $catFolder . '/' . $subFolder . '/');

        if(!File::exists($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }
        
        foreach ($request->file('photos') as $photo) {
            $ext = pathinfo($photo->getClientOriginalName(), PATHINFO_EXTENSION);
            $newFileName = $subFolder . '-' . Str::uuid() . '.' . $ext;
            $targetPath = $targetDir . $newFileName;

            try {
                $img = match(strtolower($ext)) {
                    'jpg', 'jpeg' => imagecreatefromjpeg($photo->getRealPath()),
                    'png' => imagecreatefrompng($photo->getRealPath()),
                    'webp' => imagecreatefromwebp($photo->getRealPath()),
                    default => null
                };
                
                if(!$img) continue;
    
                $logoPath = public_path('storage\logo\signature_bulle-visuelle.png');
                $logo = imagecreatefrompng($logoPath);
                imagesavealpha($logo, true);
    
                $imgW = imagesx($img);
                $imgH = imagesy($img);
                $logoW = imagesx($logo);
                $logoH = imagesy($logo);
    
                $padding = 10;
                $dstX = $imgW - $logoW - $padding;
                $dstY = $imgH - $logoH - $padding;
    
                imagecopy($img, $logo, $dstX, $dstY, 0, 0, $logoW, $logoH);
    
                imagejpeg($img, $targetPath, 75);

                $ok = imagejpeg($img, $targetPath, 75);

                if (!$ok) {
                    error_log("❌ Échec de imagejpeg sur : $targetPath");
                } else {
                    error_log("✅ Sauvegarde OK : $targetPath");
                }

    
                imagedestroy($img);
                imagedestroy($logo);

                // $imageContent = file_get_contents($photo);
                // if($imageContent != false) {
                //     // File::put($targetPath, $imageContent);
                //     $this->saveOptimizedImage($photo, $targetPath);
                // }
                // else {
                //     error_log('Unable to read content of : ' . $photo);
                // }
                // unset($imageContent);
                gc_collect_cycles();
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }
    }

    function deletePhoto(Request $request, $id) {
        $request->validate([
            'filename' => 'required|string'
        ]);

        $album = Category::findOrFail($id);
        $filename = $request->filename;

        $relativePath = "photos/{$album->parent->slug}/{$album->slug}/{$filename}";

        if(Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'File not found', 'path' => $relativePath], 404);
    }

    function saveOptimizedImage(string $source, string $target, int $initialQuality = 80): bool
    {
        $imageContent = @file_get_contents($source);
        if ($imageContent === false) {
            error_log('Unable to read: ' . $source);
            return false;
        }

        // Si l’image d’origine fait moins de 500 Ko, on la copie directement
        if (strlen($imageContent) <= 512000) {
            return file_put_contents($target, $imageContent) !== false;
        }

        // Extension du fichier (basée sur l'URL)
        $ext = strtolower(pathinfo(parse_url($source, PHP_URL_PATH), PATHINFO_EXTENSION));

        // Écriture temporaire
        $tempPath = sys_get_temp_dir() . '/' . uniqid('img_', true) . '.' . $ext;
        file_put_contents($tempPath, $imageContent);

        // Crée l’image source
        $img = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($tempPath),
            'png'        => @imagecreatefrompng($tempPath),
            'webp'       => @imagecreatefromwebp($tempPath),
            default      => null
        };

        unlink($tempPath);

        if (!$img) {
            error_log("Error when processing $ext: $source");
            return false;
        }

        // Compression adaptative
        $quality = $initialQuality;
        $success = false;

        do {
            $success = imagejpeg($img, $target, $quality);
            $size = $success ? filesize($target) : PHP_INT_MAX;

            if ($size <= 512000) {
                break;
            }

            $quality -= 10; // baisse la qualité par paliers de 10
        } while ($quality >= 30); // limite basse de qualité

        imagedestroy($img);

        return $success && $size <= 512000;
    }
}
