<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Support\Facades\File;

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
}