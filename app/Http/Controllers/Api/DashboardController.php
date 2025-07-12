<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Social;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function dashboard()
    {
        $categories = Category::whereNull('parent_id')->count();
        $albums = Category::whereNotNull('parent_id')->count();
        
        $photoCount = 0;
        $photoAlbums = Category::whereNotNull('parent_id')->with('parent')->get();

        foreach ($photoAlbums as $album) {
            $dir = storage_path("app/public/photos/{$album->parent->slug}/{$album->slug}");
            if(File::exists($dir)) {
                $photoCount += count(File::files($dir));
            }
        }

        return response()->json(['categories' => $categories, 'galleries' => $albums, 'photos' => $photoCount]);
    }

    public function setPhoto(Request $request)
    {
        try {
            $request->validate([
                'photo' => 'required|image|max:10240'
            ]);
            
            $folder = storage_path('app/public/home');
            
            if (File::exists($folder)) {
                File::cleanDirectory($folder);
            } else {
                File::makeDirectory($folder, 0755, true);
            }
            
            $file = $request->file('photo');
            $extension = strtolower($file->getClientOriginalExtension());
            $filename = uniqid() . '.' . $extension;
            $fullPath = $folder . '/' . $filename;
            
            // Charger l'image avec GD selon son type
            switch ($extension) {
            case 'jpeg':
            case 'jpg':
                if (!@getimagesize($file->getRealPath())) {
                    return response()->json(['error' => 'Fichier image invalide ou corrompu'], 400);
                }
                $img = imagecreatefromjpeg($file->getRealPath());
                imagejpeg($img, $fullPath, 75); // compression 75%
                chmod($fullPath, 0644);
                break;
            case 'png':
                $img = imagecreatefrompng($file->getRealPath());
                imagepng($img, $fullPath, 6); // compression niveau 0 (pas compressé) à 9 (max compression)
                break;
            case 'webp':
                $img = imagecreatefromwebp($file->getRealPath());
                imagewebp($img, $fullPath, 75);
                break;
            default:
                return response()->json(['error' => 'Format non supporté'], 415);
            }
                
            imagedestroy($img);
            gc_collect_cycles();
        
            return response()->json([
                'message' => 'Image enregistrée avec compression',
                'url' => asset('storage/home/' . $filename)
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    function setSocial(Request $request) {
        $social = Social::create([
            'name' => $request->name,
            'url' => $request->url
        ]);

        return response()->json($social, 201);
    }

    function removeSocial($id) {
        $social = Social::findOrFail($id);
        $social->delete();

        return response()->json(['success' => true]);
    }
}