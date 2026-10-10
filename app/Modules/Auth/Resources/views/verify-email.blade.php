<x-layouts.guest :title="__('auth.verify_email')" :heading="__('auth.verify_email')" :subheading="__('auth.verify_help')">
    @if (session('status') === 'verification-link-sent')<x-ui.alert>{{ __('auth.verification_sent') }}</x-ui.alert>@endif
    <div class="flex items-center gap-4 rounded-2xl border border-line bg-surface p-4">
        <span class="grid size-12 shrink-0 place-items-center rounded-xl bg-brand-100 text-brand-800 dark:bg-brand-900/40 dark:text-brand-200"><x-ui.icon name="mail" size="6" /></span>
        <p class="text-sm text-muted">{{ auth()->user()->email }}</p>
    </div>
    <form method="POST" action="{{ route('verification.send') }}">@csrf<x-ui.button size="lg">{{ __('auth.resend_verification') }}</x-ui.button></form>
    <form method="POST" action="{{ route('logout') }}">@csrf<x-ui.button variant="ghost">{{ __('ui.logout') }}</x-ui.button></form>
</x-layouts.guest>
