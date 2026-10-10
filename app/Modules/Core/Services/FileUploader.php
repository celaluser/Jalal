<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Media;
use App\Modules\Core\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use InvalidArgumentException;

/**
 * Stores uploads on the configured disk (local "public" or S3 compatible).
 * Raster images are re-encoded to WebP, resized and compressed; the original is never kept.
 */
class FileUploader
{
    /** Extensions accepted by default; the real MIME type is checked, not the client's. */
    private const IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private const DOCUMENT_MIMES = ['application/pdf'];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly TenantContext $tenant,
    ) {}

    public function disk(): string
    {
        $disk = $this->settings->get('storage.disk', 'public');

        return in_array($disk, ['public', 's3'], true) ? $disk : 'public';
    }

    public function store(UploadedFile $file, string $directory = 'uploads', bool $allowDocuments = false, int $maxDimension = 1920): Media
    {
        $mime = $file->getMimeType();
        $isImage = in_array($mime, self::IMAGE_MIMES, true);

        if (! $isImage && ! ($allowDocuments && in_array($mime, self::DOCUMENT_MIMES, true))) {
            throw new InvalidArgumentException(__('files.invalid_type'));
        }

        $restaurantId = $this->tenant->id();
        $folder = trim($directory, '/').($restaurantId ? "/r{$restaurantId}" : '/platform');
        $disk = $this->disk();

        $width = $height = null;
        $hasThumb = false;

        if ($isImage) {
            $image = Image::read($file->getRealPath());
            $image->scaleDown(width: $maxDimension, height: $maxDimension);
            $width = $image->width();
            $height = $image->height();

            $path = $folder.'/'.Str::uuid().'.webp';
            $contents = (string) $image->toWebp(quality: (int) $this->settings->get('storage.image_quality', 82));
            Storage::disk($disk)->put($path, $contents, 'public');

            // Lists and cards load a small copy; the full image is for the detail view.
            if ($image->width() > Media::THUMB_SIZE || $image->height() > Media::THUMB_SIZE) {
                $thumb = Image::read($file->getRealPath())->scaleDown(width: Media::THUMB_SIZE, height: Media::THUMB_SIZE);
                Storage::disk($disk)->put(Media::thumbPath($path), (string) $thumb->toWebp(quality: 78), 'public');
                $hasThumb = true;
            }

            $mime = 'image/webp';
            $size = strlen($contents);
        } else {
            $path = $folder.'/'.Str::uuid().'.pdf';
            Storage::disk($disk)->put($path, file_get_contents($file->getRealPath()), 'public');
            $size = $file->getSize();
        }

        return Media::create([
            'restaurant_id' => $restaurantId,
            'disk' => $disk,
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'mime' => $mime,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'has_thumb' => $hasThumb,
        ]);
    }

    public function delete(Media $media): void
    {
        Storage::disk($media->disk)->delete(array_filter([$media->path, $media->has_thumb ? Media::thumbPath($media->path) : null]));
        $media->delete();
    }
}
