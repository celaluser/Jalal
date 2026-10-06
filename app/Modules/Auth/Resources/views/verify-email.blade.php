<x-layouts.guest :title="__('auth.verify_email')">
    <h1 class="mb-2 text-xl font-semibold">{{ __('auth.verify_email') }}</h1>
    <p class="mb-4 text-sm text-gray-500">{{ __('auth.verify_help') }}</p>
    @if (session('status') === 'verification-link-sent')<x-ui.alert>{{ __('auth.verification_sent') }}</x-ui.alert>@endif
    <form method="POST" action="{{ route('verification.send') }}" class="space-y-3">
        @csrf
        <x-ui.button>{{ __('auth.resend_verification') }}</x-ui.button>
    </form>
    <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf
        <x-ui.button variant="secondary">{{ __('ui.logout') }}</x-ui.button>
    </form>
</x-layouts.guest>
