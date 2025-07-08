<?php

namespace App\Http\Controllers\Api;

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

    public function setPhoto(Request $request) {
        $request->validate([
            'photo' => 'required|image|max:5120'
        ]);

        $user = $request->user();

        $folder = 'photos/profil';

        if($user->profil_picture && Storage::disk('public')->exists($folder . '/' . $user->profil_picture)) {
            Storage::disk('public')->delete($user->profil_picture);
        }

        $path = $request->file('photo')->store($folder, 'public');

        $user->profile_photo = $path;
        $user->save();

        return response()->json([
            'message' => 'Picture profil updated',
            'user' => $user,
            'url' => asset('storage/' . $path)
        ]);
    }
}
