<?php

namespace App\Modules\BuyGuide;

use App\Exceptions\ApiException;
use App\Models\BuyGuideMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class BuyGuideMediaService
{
    /** @var list<int> */
    private const WIDTHS = [480, 960, 1440];

    public function storeImage(UploadedFile $file, string $alt): BuyGuideMedia
    {
        $alt = BuyGuideContentRules::cleanText($alt);
        if (mb_strlen($alt) < 3) {
            throw new ApiException('متن جایگزین تصویر حداقل سه حرف است.', 422);
        }
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagewebp')) {
            throw new ApiException('تبدیل WebP روی سرور در دسترس نیست.', 500);
        }
        $raw = file_get_contents($file->getRealPath());
        if ($raw === false) {
            throw new ApiException('خواندن فایل تصویر انجام نشد.', 422);
        }
        $source = @imagecreatefromstring($raw);
        if ($source === false) {
            throw new ApiException('فرمت تصویر پشتیبانی نمی‌شود.', 422);
        }
        $width = imagesx($source);
        $height = imagesy($source);
        $folder = 'buy-guides/'.date('Y').'/'.date('m');
        $base = Str::lower(Str::random(12));
        $disk = Storage::disk('public');
        $originalExt = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $originalPath = $folder.'/'.$base.'.orig.'.$originalExt;
        $disk->put($originalPath, $raw);
        $mainPath = $folder.'/'.$base.'.webp';
        $this->writeWebp($source, $disk, $mainPath, $width, $height);
        $variants = [];
        foreach (self::WIDTHS as $target) {
            if ($target >= $width) {
                continue;
            }
            $variantPath = $folder.'/'.$base.'-'.$target.'.webp';
            $this->writeWebp($source, $disk, $variantPath, $target, (int) round($height * ($target / $width)));
            $variants[(string) $target] = $variantPath;
        }
        imagedestroy($source);
        $bytes = $disk->size($mainPath);

        return BuyGuideMedia::query()->create([
            'disk' => 'public',
            'path' => $mainPath,
            'original_path' => $originalPath,
            'mime' => 'image/webp',
            'width' => $width,
            'height' => $height,
            'bytes' => $bytes ?: null,
            'alt' => mb_substr($alt, 0, 180),
            'variants' => $variants,
            'kind' => 'image',
        ]);
    }

    public function storeImageFromPath(string $absolutePath, string $alt): BuyGuideMedia
    {
        if (! is_file($absolutePath)) {
            throw new ApiException('فایل تصویر پیدا نشد.', 422);
        }
        $uploaded = new UploadedFile($absolutePath, basename($absolutePath), mime_content_type($absolutePath) ?: 'image/png', null, true);

        return $this->storeImage($uploaded, $alt);
    }

    public function storeVideo(UploadedFile $file, string $alt): BuyGuideMedia
    {
        $alt = BuyGuideContentRules::cleanText($alt);
        if (mb_strlen($alt) < 3) {
            throw new ApiException('متن جایگزین ویدیو حداقل سه حرف است.', 422);
        }
        $folder = 'buy-guides/'.date('Y').'/'.date('m');
        $path = $file->store($folder, 'public');

        return BuyGuideMedia::query()->create([
            'disk' => 'public',
            'path' => $path,
            'original_path' => $path,
            'mime' => $file->getMimeType() ?: 'video/mp4',
            'width' => null,
            'height' => null,
            'bytes' => $file->getSize() ?: null,
            'alt' => mb_substr($alt, 0, 180),
            'variants' => null,
            'kind' => 'video',
        ]);
    }

    public function updateAlt(BuyGuideMedia $media, string $alt): BuyGuideMedia
    {
        $alt = BuyGuideContentRules::cleanText($alt);
        if (mb_strlen($alt) < 3) {
            throw new ApiException('متن جایگزین حداقل سه حرف است.', 422);
        }
        $media->alt = mb_substr($alt, 0, 180);
        $media->save();

        return $media;
    }

    private function writeWebp(\GdImage $source, $disk, string $path, int $width, int $height): void
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        ob_start();
        imagewebp($canvas, null, 82);
        $binary = (string) ob_get_clean();
        imagedestroy($canvas);
        $disk->put($path, $binary);
    }
}
