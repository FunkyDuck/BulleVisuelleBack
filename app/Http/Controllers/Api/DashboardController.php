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

    public function setPhoto(Request $request) {
        $request->validate([
            'photo' => 'required|image|max:5120'
        ]);

        $folder = storage_path('app/public/home');

        if(File::exists($folder)) {
            File::cleanDirectory($folder);
        }
        else {
            File::makeDirectory($folder, 0755, true);
        }

        $path = $request->file('photo')->store('home', 'public');

        return response()->json([
            'message' => 'Picture profil updated',
            'url' => asset('storage/' . $path)
        ]);
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