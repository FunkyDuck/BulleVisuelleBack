<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Social;

class HomeController extends Controller {
    public function getPhoto() {
        $files = Storage::disk('public')->files('home');

        if (empty($files)) {
            return response()->json(['url' => null]);
        }

        return response()->json([
                'url' => asset('storage/' . $files[0])
            ]);
    }

    function getSocial() {
        $social = Social::all();
        return response()->json($social);
    }
}