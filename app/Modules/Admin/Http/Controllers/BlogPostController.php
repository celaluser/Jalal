<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cms\Models\BlogPost;
use App\Modules\Core\Models\Language;
use App\Modules\Core\Services\FileUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BlogPostController extends Controller
{
    public function index(): View
    {
        return view('admin::cms.posts-index', ['posts' => BlogPost::latest('id')->paginate(25)]);
    }

    public function create(): View
    {
        return view('admin::cms.post-form', ['post' => new BlogPost(['locale' => config('app.default_locale'), 'is_published' => false]), 'locales' => $this->locales()]);
    }

    public function store(Request $request): RedirectResponse
    {
        BlogPost::create($this->validated($request) + ['author_id' => $request->user()->id]);

        return redirect()->route('admin.posts.index')->with('status', __('admin.saved'));
    }

    public function edit(BlogPost $post): View
    {
        return view('admin::cms.post-form', ['post' => $post, 'locales' => $this->locales()]);
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $post->update($this->validated($request, $post));

        return redirect()->route('admin.posts.index')->with('status', __('admin.saved'));
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $post->delete();

        return back()->with('status', __('admin.cms.deleted'));
    }

    /** @return array<string, string> */
    private function locales(): array
    {
        return Language::active()->pluck('name', 'code')->all() ?: ['en' => 'English'];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?BlogPost $post = null): array
    {
        $locale = (string) $request->input('locale');

        $data = $request->validate([
            'locale' => ['required', 'string', 'max:12', 'exists:languages,code'],
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('blog_posts', 'slug')->where('locale', $locale)->ignore($post?->id)],
            'excerpt' => ['nullable', 'string', 'max:400'],
            'body' => ['required', 'string', 'max:200000'],
            'meta_description' => ['nullable', 'string', 'max:300'],
            'published_at' => ['nullable', 'date'],
            'cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096'],
        ]);

        $attributes = [
            'locale' => $data['locale'],
            'title' => $data['title'],
            'slug' => $data['slug'],
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'meta_description' => $data['meta_description'] ?? null,
            'is_published' => $request->boolean('is_published'),
            'published_at' => $data['published_at'] ?? null,
        ];

        if ($request->boolean('remove_cover')) {
            $attributes['cover_url'] = null;
        }

        if ($request->hasFile('cover')) {
            $media = app(FileUploader::class)->store($request->file('cover'), 'blog');
            $attributes['cover_url'] = Storage::disk($media->disk)->url($media->path);
        }

        // Publishing without a date means "now", so the post shows up immediately.
        if ($attributes['is_published'] && $attributes['published_at'] === null) {
            $attributes['published_at'] = $post?->published_at ?? now();
        }

        return $attributes;
    }
}
