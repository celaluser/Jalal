<?php

namespace App\Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves uploaded files from storage/app/public when the usual public/storage link could not be created (many shared hosts
 * forbid symlinks). When the link exists the web server answers first and this is never reached. Only known image/PDF types,
 * only inside the public disk, never a path with "..".
 */
class PublicFileController extends Controller
{
    private const TYPES = ['webp' => 'image/webp', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'pdf' => 'application/pdf', 'ico' => 'image/x-icon'];

    public function __invoke(string $path): BinaryFileResponse
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        abort_unless(isset(self::TYPES[$ext]) && ! str_contains($path, '..') && ! str_contains($path, "\0") && ! str_starts_with($path, '/'), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), ['Content-Type' => self::TYPES[$ext], 'Cache-Control' => 'public, max-age=604800', 'X-Content-Type-Options' => 'nosniff']);
    }
}
