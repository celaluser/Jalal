<?php

namespace App\Modules\Menu\Services;

use App\Modules\Core\Models\Media;
use App\Modules\Core\Services\FileUploader;
use App\Modules\Menu\Models\Product;
use Illuminate\Http\UploadedFile;

/** Extra photos and the video link of a dish. */
class ProductMedia
{
    public const MAX_GALLERY = 6;

    public function __construct(private readonly FileUploader $uploader) {}

    /**
     * Adds uploads and removes ticked photos; returns the new list of media ids.
     *
     * @param  list<UploadedFile>  $uploads
     * @param  list<int|string>  $remove
     * @return list<int>
     */
    public function syncGallery(?Product $product, array $uploads, array $remove): array
    {
        $current = array_map('intval', $product?->gallery ?? []);
        $remove = array_map('intval', $remove);

        foreach (array_intersect($current, $remove) as $id) {
            if ($media = Media::find($id)) {
                $this->uploader->delete($media);
            }
        }

        $kept = array_values(array_diff($current, $remove));

        foreach ($uploads as $file) {
            if (count($kept) >= self::MAX_GALLERY) {
                break;
            }

            $kept[] = $this->uploader->store($file, 'menu', maxDimension: (int) config('menu.image_max_dimension'))->id;
        }

        return $kept;
    }

    /** Frees the files when the dish is deleted. */
    public function purge(Product $product): void
    {
        foreach ($product->gallery ?? [] as $id) {
            if ($media = Media::find($id)) {
                $this->uploader->delete($media);
            }
        }
    }

    /**
     * What the guest menu needs to show a video: only YouTube, Vimeo and direct video files, over https.
     *
     * @return array{type: string, src: string}|null
     */
    public static function video(?string $url): ?array
    {
        $url = trim((string) $url);

        if ($url === '' || ! preg_match('#^https://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        if (preg_match('#^https://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#i', $url, $m)) {
            return ['type' => 'embed', 'src' => 'https://www.youtube-nocookie.com/embed/'.$m[1]];
        }

        if (preg_match('#^https://(?:www\.)?vimeo\.com/(?:video/)?(\d{5,12})#i', $url, $m)) {
            return ['type' => 'embed', 'src' => 'https://player.vimeo.com/video/'.$m[1].'?dnt=1'];
        }

        return preg_match('#\.(mp4|webm|ogg)(\?.*)?$#i', $url) ? ['type' => 'file', 'src' => $url] : null;
    }
}
