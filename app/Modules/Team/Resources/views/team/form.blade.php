@php($editing = $member !== null)
<x-layouts.app :title="$editing ? __('team.edit_member') : __('team.invite_title')">
    <x-ui.page-header :title="$editing ? __('team.edit_member') : __('team.invite_title')" :description="$editing ? $member->email : __('team.invite_sub')" :back="['url' => route('team.index'), 'label' => __('team.title')]" />

    <div class="max-w-xl space-y-5">
        @error('team')<x-ui.alert type="error">{{ $message }}</x-ui.alert>@enderror
        @if (! $editing && $remaining === 0)
            <x-ui.alert type="warning"><span class="flex flex-wrap items-center justify-between gap-2"><span>{{ __('team.limit_note') }}</span><a class="font-semibold underline" href="{{ route('billing.index') }}">{{ __('team.upgrade') }}</a></span></x-ui.alert>
        @endif

        <form method="POST" action="{{ $editing ? route('team.update', $member->id) : route('team.store') }}" class="card card-pad space-y-5">
            @csrf @if ($editing) @method('PUT') @endif
            @unless ($editing)
                <x-ui.input name="name" :label="__('team.name')" required autocomplete="off" />
                <x-ui.input name="email" type="email" :label="__('team.email')" required autocomplete="off" />
            @endunless
            <fieldset>
                <legend class="mb-2 text-sm font-medium">{{ __('team.role') }}</legend>
                <div class="grid gap-2">
                    @foreach ($roles as $role)
                        @php($builtIn = in_array($role, \App\Modules\Team\Services\TeamService::ASSIGNABLE))
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line-strong p-3 transition hover:bg-surface-2 has-[:checked]:border-accent-600 has-[:checked]:bg-accent-50 dark:has-[:checked]:bg-accent-900/20">
                            <input type="radio" name="role" value="{{ $role }}" class="mt-1 size-4 accent-[var(--color-accent-600)]" @checked(old('role', $editing ? $team->roleOf($member) : 'waiter') === $role) required>
                            <span><span class="block font-medium">{{ $builtIn ? __('team.role_'.$role) : $role }}</span>
                                @if ($builtIn)<span class="block text-sm text-muted">{{ __('team.role_'.$role.'_text') }}</span>@endif</span>
                        </label>
                    @endforeach
                </div>
                @error('role')<p class="mt-1.5 text-sm text-red-600" role="alert">{{ $message }}</p>@enderror
            </fieldset>
            <div class="flex items-center justify-between gap-3 pt-1">
                <span>@if ($editing)<button type="submit" form="remove-member" class="btn btn-ghost text-red-600 dark:text-red-400">{{ __('team.remove') }}</button>@endif</span>
                <x-ui.button :block="false" :disabled="! $editing && $remaining === 0">{{ $editing ? __('team.save') : __('team.send_invite') }}</x-ui.button>
            </div>
        </form>

        @if ($editing)
            <form id="remove-member" method="POST" action="{{ route('team.destroy', $member->id) }}" onsubmit="return confirm('{{ __('team.remove_confirm') }}')">@csrf @method('DELETE')</form>
            @if ($member->invited_at)
                <x-ui.card><form method="POST" action="{{ route('team.resend', $member->id) }}" class="flex items-center justify-between gap-3">@csrf<span class="text-sm text-muted">{{ $member->email }}</span><x-ui.button variant="secondary" size="sm" :block="false" icon="mail">{{ __('team.resend') }}</x-ui.button></form></x-ui.card>
            @endif
            <p class="text-sm text-muted">{{ __('team.disable_help') }}</p>
        @endif
    </div>
</x-layouts.app>
