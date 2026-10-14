<x-layouts.app :title="__('website.title')">
    <x-ui.page-header :title="__('website.title')" :description="__('website.sub')" />

    <form method="POST" action="{{ route('site.update') }}" class="max-w-3xl space-y-6">
        @csrf @method('PUT')
        <x-ui.card :title="__('website.visibility')">
            <div class="space-y-3">
                <x-ui.checkbox name="site_enabled" :label="__('website.enabled')" :checked="$s['site_enabled']" />
                <p class="-mt-2 text-xs text-muted">{{ __('website.enabled_help', ['url' => $restaurant->publicUrl('about')]) }}</p>
                <x-ui.checkbox name="indexable" :label="__('website.indexable')" :checked="$s['indexable']" />
                <p class="-mt-2 text-xs text-muted">{{ __('website.indexable_help', ['url' => $restaurant->publicUrl('sitemap.xml')]) }}</p>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('website.texts')">
            <div class="space-y-5">
                @foreach ($locales as $code)
                    <fieldset class="space-y-3 rounded-lg border border-line p-4">
                        <legend class="px-1 text-sm font-semibold uppercase" dir="ltr">{{ $code }}</legend>
                        <x-ui.input :name="'seo_title['.$code.']'" :value="old('seo_title.'.$code, $s['seo_title'][$code] ?? '')" :label="__('website.seo_title')" maxlength="70" />
                        <x-ui.input :name="'seo_description['.$code.']'" :value="old('seo_description.'.$code, $s['seo_description'][$code] ?? '')" :label="__('website.seo_description')" maxlength="170" />
                        <div><label class="mb-1.5 block text-sm font-medium" for="about_{{ $code }}">{{ __('website.about') }}</label>
                            <textarea id="about_{{ $code }}" name="about[{{ $code }}]" rows="4" maxlength="3000" class="field">{{ old('about.'.$code, $s['about'][$code] ?? '') }}</textarea></div>
                    </fieldset>
                @endforeach
            </div>
        </x-ui.card>

        <x-ui.card :title="__('website.hours')">
            <p class="mb-3 text-sm text-muted">{{ __('website.hours_help') }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach (range(0, 6) as $i)<x-ui.input :name="'hours['.$i.']'" :value="old('hours.'.$i, $s['hours'][$i] ?? '')" :label="__('customer.about_day_'.$i)" placeholder="11:00-23:00" dir="ltr" maxlength="60" />@endforeach
            </div>
        </x-ui.card>

        <x-ui.card :title="__('website.details')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="cuisine" :value="$s['cuisine']" :label="__('website.cuisine')" placeholder="Italian, Pizza" maxlength="80" />
                <div><label class="mb-1.5 block text-sm font-medium" for="price_range">{{ __('website.price_range') }}</label>
                    <select id="price_range" name="price_range" class="field"><option value="">–</option>@foreach (['$', '$$', '$$$', '$$$$'] as $p)<option value="{{ $p }}" @selected($s['price_range'] === $p)>{{ $p }}</option>@endforeach</select></div>
                <div class="sm:col-span-2"><x-ui.input name="map_url" type="url" :value="$s['map_url']" :label="__('website.map_url')" :hint="__('website.map_url_help')" dir="ltr" maxlength="500" /></div>
            </div>
        </x-ui.card>

        {{-- Page builder and photo gallery. The editor keeps its state in two hidden JSON fields; the server cleans them. --}}
        <div class="space-y-6"
             x-data="{
                locales: @js($locales), loc: @js($locales[0]), urls: @js((object) $urls), uploading: 0, error: '',
                blocks: @js(collect($s['blocks'])->map(fn ($b) => $b + ['title' => [], 'body' => [], 'caption' => [], 'label' => [], 'target' => 'custom', 'url' => '', 'style' => 'primary', 'media' => null])->all()),
                gallery: @js($s['gallery']),
                types: ['text', 'image', 'button', 'gallery', 'hours', 'contact', 'follow'],
                add(type) { if (this.blocks.length < 30) this.blocks.push({ type, title: {}, body: {}, caption: {}, label: {}, target: 'custom', url: '', style: 'primary', media: null }); },
                move(list, i, d) { const j = i + d; if (j < 0 || j >= list.length) return; [list[i], list[j]] = [list[j], list[i]]; },
                async upload(file) {
                    if (!file) return null; this.uploading++; this.error = '';
                    const fd = new FormData(); fd.append('image', file); fd.append('_token', @js(csrf_token()));
                    try {
                        const r = await fetch(@js(route('site.media')), { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
                        if (!r.ok) { this.error = @js(__('sitebuilder.upload_failed')); return null; }
                        const d = await r.json(); this.urls[d.id] = d.url; return d.id;
                    } catch (e) { this.error = @js(__('sitebuilder.upload_failed')); return null; } finally { this.uploading--; }
                },
                async setImage(b, ev) { const id = await this.upload(ev.target.files[0]); if (id) b.media = id; ev.target.value = ''; },
                async addPhotos(ev) { for (const f of Array.from(ev.target.files)) { if (this.gallery.length >= 24) break; const id = await this.upload(f); if (id) this.gallery.push({ media: id, caption: '' }); } ev.target.value = ''; }
             }">
            <input type="hidden" name="blocks_json" :value="JSON.stringify(blocks)">
            <input type="hidden" name="gallery_json" :value="JSON.stringify(gallery)">

            <x-ui.card :title="__('sitebuilder.sections_title')" :description="__('sitebuilder.sections_help')">
                <div class="mb-4 flex flex-wrap items-center gap-3" x-show="locales.length > 1">
                    <label for="sb-loc" class="text-sm font-medium">{{ __('sitebuilder.language') }}</label>
                    <select id="sb-loc" class="field !w-auto" x-model="loc"><template x-for="l in locales" :key="l"><option :value="l" x-text="l.toUpperCase()"></option></template></select>
                </div>

                <p class="rounded-lg bg-surface-2 px-3 py-2 text-sm text-muted" x-show="blocks.length === 0">{{ __('sitebuilder.empty_note') }}</p>

                <ol class="space-y-3">
                    <template x-for="(b, i) in blocks" :key="i">
                        <li class="rounded-xl border border-line p-3">
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold" x-text="({ text: @js(__('sitebuilder.type_text')), image: @js(__('sitebuilder.type_image')), button: @js(__('sitebuilder.type_button')), gallery: @js(__('sitebuilder.type_gallery')), hours: @js(__('sitebuilder.type_hours')), contact: @js(__('sitebuilder.type_contact')), follow: @js(__('sitebuilder.type_follow')) })[b.type]"></p>
                                <div class="flex items-center gap-1 text-sm">
                                    <button type="button" class="rounded px-2 py-1 hover:bg-surface-2" @click="move(blocks, i, -1)" :disabled="i === 0" aria-label="{{ __('sitebuilder.up') }}">↑</button>
                                    <button type="button" class="rounded px-2 py-1 hover:bg-surface-2" @click="move(blocks, i, 1)" :disabled="i === blocks.length - 1" aria-label="{{ __('sitebuilder.down') }}">↓</button>
                                    <button type="button" class="rounded px-2 py-1 text-red-600 hover:bg-surface-2" @click="blocks.splice(i, 1)">{{ __('sitebuilder.remove') }}</button>
                                </div>
                            </div>

                            <div class="space-y-3" x-show="b.type === 'text'">
                                <div><label class="mb-1 block text-xs font-medium text-muted">{{ __('sitebuilder.f_title') }}</label><input type="text" class="field" maxlength="120" x-model="b.title[loc]"></div>
                                <div><label class="mb-1 block text-xs font-medium text-muted">{{ __('sitebuilder.f_body') }}</label><textarea class="field" rows="4" maxlength="2000" x-model="b.body[loc]"></textarea><p class="mt-1 text-xs text-muted">{{ __('sitebuilder.body_help') }}</p></div>
                            </div>

                            <div class="space-y-3" x-show="b.type === 'image'">
                                <img :src="urls[b.media]" alt="" class="max-h-40 rounded-lg" x-show="b.media && urls[b.media]">
                                <input type="file" accept="image/*" class="text-sm" @change="setImage(b, $event)" aria-label="{{ __('sitebuilder.upload') }}">
                                <div><label class="mb-1 block text-xs font-medium text-muted">{{ __('sitebuilder.f_caption') }}</label><input type="text" class="field" maxlength="160" x-model="b.caption[loc]"></div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2" x-show="b.type === 'button'">
                                <div><label class="mb-1 block text-xs font-medium text-muted">{{ __('sitebuilder.f_label') }}</label><input type="text" class="field" maxlength="40" x-model="b.label[loc]"></div>
                                <div><label class="mb-1 block text-xs font-medium text-muted">{{ __('sitebuilder.f_target') }}</label>
                                    <select class="field" x-model="b.target">
                                        @foreach (\App\Modules\Storefront\Services\SiteBuilder::TARGETS as $t)<option value="{{ $t }}">{{ __('sitebuilder.target_'.$t) }}</option>@endforeach
                                    </select></div>
                                <div class="sm:col-span-2" x-show="b.target === 'custom'"><label class="mb-1 block text-xs font-medium text-muted">{{ __('sitebuilder.f_url') }}</label><input type="text" class="field" dir="ltr" maxlength="300" placeholder="https://…" x-model="b.url"></div>
                                <div><label class="mb-1 block text-xs font-medium text-muted">{{ __('sitebuilder.style_primary') }} / {{ __('sitebuilder.style_dark') }}</label>
                                    <select class="field" x-model="b.style"><option value="primary">{{ __('sitebuilder.style_primary') }}</option><option value="dark">{{ __('sitebuilder.style_dark') }}</option></select></div>
                            </div>

                            <p class="text-sm text-muted" x-show="b.type === 'gallery'">{{ __('sitebuilder.gallery_block_note') }}</p>
                            <p class="text-sm text-muted" x-show="['hours', 'contact', 'follow'].includes(b.type)">{{ __('sitebuilder.auto_block_note') }}</p>
                        </li>
                    </template>
                </ol>

                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach (\App\Modules\Storefront\Services\SiteBuilder::TYPES as $t)
                        <button type="button" class="btn btn-secondary btn-sm" @click="add('{{ $t }}')">+ {{ __('sitebuilder.type_'.$t) }}</button>
                    @endforeach
                </div>
                <p class="mt-3 text-sm font-medium text-red-600" x-show="error" x-text="error" role="alert" x-cloak></p>
            </x-ui.card>

            <x-ui.card :title="__('sitebuilder.gallery_title')" :description="__('sitebuilder.gallery_help')">
                <p class="text-sm text-muted" x-show="gallery.length === 0">{{ __('sitebuilder.no_photos') }}</p>
                <ul class="grid gap-3 sm:grid-cols-2">
                    <template x-for="(p, i) in gallery" :key="p.media">
                        <li class="flex gap-3 rounded-xl border border-line p-2">
                            <img :src="urls[p.media]" alt="" class="size-20 shrink-0 rounded-lg object-cover" loading="lazy">
                            <div class="min-w-0 flex-1 space-y-1.5">
                                <input type="text" class="field" maxlength="120" x-model="p.caption" placeholder="{{ __('sitebuilder.photo_caption') }}" aria-label="{{ __('sitebuilder.photo_caption') }}">
                                <div class="flex items-center gap-1 text-sm">
                                    <button type="button" class="rounded px-2 py-0.5 hover:bg-surface-2" @click="move(gallery, i, -1)" :disabled="i === 0" aria-label="{{ __('sitebuilder.up') }}">↑</button>
                                    <button type="button" class="rounded px-2 py-0.5 hover:bg-surface-2" @click="move(gallery, i, 1)" :disabled="i === gallery.length - 1" aria-label="{{ __('sitebuilder.down') }}">↓</button>
                                    <button type="button" class="rounded px-2 py-0.5 text-red-600 hover:bg-surface-2" @click="gallery.splice(i, 1)">{{ __('sitebuilder.remove') }}</button>
                                </div>
                            </div>
                        </li>
                    </template>
                </ul>
                <div class="mt-4 flex items-center gap-3">
                    <label class="btn btn-secondary cursor-pointer">{{ __('sitebuilder.add_photos') }}<input type="file" accept="image/*" multiple class="sr-only" @change="addPhotos($event)" :disabled="gallery.length >= 24"></label>
                    <span class="text-sm text-muted" x-show="uploading > 0" x-cloak>{{ __('sitebuilder.uploading') }}</span>
                </div>
            </x-ui.card>
        </div>

        <x-ui.button :block="false">{{ __('admin.save') }}</x-ui.button>
    </form>
</x-layouts.app>
