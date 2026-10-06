@php($ga = platform_setting('seo.google_analytics_id'))
@if (platform_setting('security.cookie_banner', '1') === '1' || $ga)
    <div x-data="{ show: false, choice: null,
            init() { try { this.choice = localStorage.getItem('cookie-consent'); } catch (e) {} this.show = this.choice === null; this.load(); },
            set(v) { this.choice = v; this.show = false; try { localStorage.setItem('cookie-consent', v); } catch (e) {} this.load(); },
            load() {
                @if ($ga)
                if (this.choice !== 'accepted' || window.__gaLoaded) return;
                window.__gaLoaded = true;
                const s = document.createElement('script'); s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id={{ $ga }}'; document.head.appendChild(s);
                window.dataLayer = window.dataLayer || []; window.gtag = function () { dataLayer.push(arguments); }; gtag('js', new Date()); gtag('config', '{{ $ga }}', { anonymize_ip: true });
                @endif
            } }"
         x-show="show" x-cloak role="dialog" aria-label="{{ __('cookie.title') }}"
         class="fixed inset-x-0 bottom-0 z-50 border-t border-gray-200 bg-white p-4 shadow-lg dark:border-gray-700 dark:bg-gray-900">
        <div class="mx-auto flex max-w-4xl flex-col gap-3 sm:flex-row sm:items-center">
            <p class="flex-1 text-sm">{{ platform_setting('security.cookie_text', __('cookie.default_text')) }}</p>
            <div class="flex gap-2">
                <button type="button" x-on:click="set('declined')" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold dark:bg-gray-800">{{ __('cookie.decline') }}</button>
                <button type="button" x-on:click="set('accepted')" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white">{{ __('cookie.accept') }}</button>
            </div>
        </div>
    </div>
@endif
