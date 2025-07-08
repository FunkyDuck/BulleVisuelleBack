<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Models\Category;

ini_set('memory_limit', '256M');

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        error_log('Start generate and transfert albums and pictures');
        $oldPath = 'https://bulle-visuelle.be/';
        $newPath = storage_path('app/photos/');

        // Get old categories
        $categories = DB::table('bv_menu')->get();

        foreach ($categories as $cat) {
            // Create a new category
            $parent = Category::create([
                'name' => $this->fixEncoding($cat->nom),
                'slug' => Str::slug($this->fixEncoding($cat->nom)),
                'parent_id' => null
            ]);
            
            // Get old subcategories by category
            $subcats = DB::table('bv_album')
            ->where('ID_menu', $cat->ID_menu)
            ->get();
            
            foreach ($subcats as $sub) {
                // Create new SubCategory
                if($sub->view == 'true') {
                    $children = Category::create([
                        'name' => $this->fixEncoding($sub->nom),
                        'slug' => Str::slug($this->fixEncoding($sub->nom)),
                        'parent_id' => $parent->id
                    ]);
                    
                    $photos = DB::table('bv_photo')
                    ->where('ID_album', $sub->ID_album)
                    ->get();
                    
                    foreach ($photos as $photo) {
                        $catFolder = $parent->slug;
                        $subFolder = $children->slug;
                        
                        $relativeUrl = $this->fixEncoding($photo->url);
                        $pathParts = explode('/', $relativeUrl);
                        $encodedPath = implode('/', array_map('rawurlencode', $pathParts));
                        
                        $sourcePath = $oldPath . $encodedPath;
                        $targetDir = storage_path('app/public/photos/' . $catFolder . '/' . $subFolder . '/');
                        
                        $ext = pathinfo($photo->url, PATHINFO_EXTENSION);
                        $newFileName = $subFolder . '-' . Str::uuid() . '.' . $ext;
                        $targetPath = $targetDir . $newFileName;
                        
                        if(!File::exists($targetDir)) {
                            File::makeDirectory($targetDir, 0755, true);
                        }
                        
                        try {
                            $imageContent = file_get_contents($sourcePath);
                            if($imageContent != false) {
                                // File::put($targetPath, $imageContent);
                                $this->saveOptimizedImage($sourcePath, $targetPath);
                            }
                            else {
                                error_log('Unable to read content of : ' . $sourcePath);
                            }
                            unset($imageContent);
                            gc_collect_cycles();
                        } catch (\Exception $e) {
                            error_log($e->getMessage());
                        }
                    }
                    error_log('CREATED :: ' . $sub->nom);
                }
            }
        }
        error_log('End transfert...');
    }

    function fixEncoding($str): string 
    {
        $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $replacements = [
            'Ã©' => 'é',
            'Ã¨' => 'è',
            'Ãª' => 'ê',
            'Ã«' => 'ë',
            'Ã ' => 'à',
            'Ã¹' => 'ù',
            'Ã´' => 'ô',
            'Ã»' => 'û',
            'Ã®' => 'î',
            'Ã¯' => 'ï',
            'Ã§' => 'ç',
            'Ã‰' => 'É',
            'Ã€' => 'À',
            'Ã”' => 'Ô',
            'Ãœ' => 'Ü',
            'Ãœ' => 'Ü',
            'Â'   => '',
            'â€™' => '’',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $str);
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
