<?php

namespace App\Modules\Menu\Services;

use App\Modules\Core\Models\Media;
use App\Modules\Core\Services\FileUploader;
use Illuminate\Http\UploadedFile;

/** Replace or remove the image of a menu item, cleaning up the file it no longer needs. */
class MenuImage
{
    public function __construct(private readonly FileUploader $uploader) {}

    /** @return int|null|false the new media id, null when removed, false when nothing changed */
    public function sync(?int $currentId, ?UploadedFile $upload, bool $remove): int|null|false
    {
        if (! $upload && ! $remove) {
            return false;
        }

        $new = $upload ? $this->uploader->store($upload, 'menu', maxDimension: (int) config('menu.image_max_dimension')) : null;

        if ($currentId && ($old = Media::find($currentId))) {
            $this->uploader->delete($old);
        }

        return $new?->id;
    }
}
