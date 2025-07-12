<?php

namespace App\Http\Controllers\Api;

ini_set('memory_limit', '512M');
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

use function PHPUnit\Framework\throwException;


class UserController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $user->pict_url = asset("storage/{$user->profile_photo}"); 
        return response()->json($user);
    }
    
    public function author()
    {
        $user = User::first();
        $user->pict_url = asset("storage/{$user->profile_photo}"); 
        return response()->json($user);
        
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if(! Hash::check($request->oldPassword, $user->password)) {
            return response()->json(['error' => 'Bad credentials', 'old-pwd' => $request->oldPassword], 401);
        }
        
        $user->password = Hash::make($request->newPassword);
        $user->save();

        return response()->json(['message' => 'password updated']);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required']
        ]);

        $user = User::where('email', $request->email)->first();

        if(!$user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => ['Bad credentials']
            ], 401);
        }

        $token = $user->createToken('bulle-visuelle-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    public function logout(Request $request) {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie']);
    }

    public function setPhoto(Request $request)
    {
        try {
            $request->validate([
                'photo' => 'required|image|max:10240'
            ]);
            
            $folder = storage_path('app/public/profil');
            
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

            $user = $request->user();
            $user->profile_photo = 'profil/' . $filename;
            $user->save();
                
            imagedestroy($img);
            gc_collect_cycles();
        
            return response()->json([
                'message' => 'Image enregistrée avec compression',
                'url' => asset('storage/profil/' . $filename)
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
