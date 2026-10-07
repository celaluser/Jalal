<x-layouts.app :title="__('team.title')">
    <x-ui.page-header :title="__('team.title')" :description="__('team.subtitle')">
        <x-slot:actions>
            @can('roles.manage')<a href="{{ route('team.roles.index') }}" class="btn btn-secondary"><x-ui.icon name="shield" size="4" />{{ __('team.roles') }}</a>@endcan
            @if ($canManage)<a href="{{ route('team.create') }}" class="btn btn-primary"><x-ui.icon name="plus" size="4" />{{ __('team.invite') }}</a>@endif
        </x-slot:actions>
    </x-ui.page-header>

    @error('team')<x-ui.alert type="error" class="mb-4">{{ $message }}</x-ui.alert>@enderror
    <p class="mb-3 text-sm text-muted tnum"><bdi>{{ $remaining === null ? __('team.usage_unlimited', ['used' => $users->count()]) : __('team.usage', ['used' => $users->count(), 'max' => $users->count() + $remaining]) }}</bdi></p>

    <ul class="grid gap-3">
        @foreach ($users as $member)
            @php($owner = $team->isOwner($member))
            @php($me = $member->is(auth()->user()))
            <li class="card flex flex-wrap items-center gap-4 p-4">
                <x-ui.avatar :name="$member->name" />
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2 font-semibold">{{ $member->name }}@if ($me)<x-ui.badge>{{ __('team.you') }}</x-ui.badge>@endif</p>
                    <p class="truncate text-sm text-muted" dir="ltr">{{ $member->email }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <x-ui.badge :tone="$owner ? 'warning' : 'info'">{{ $owner ? __('team.owner') : (in_array($role = $team->roleOf($member), \App\Modules\Team\Services\TeamService::ASSIGNABLE) ? __('team.role_'.$role) : $role) }}</x-ui.badge>
                    @if ($member->disabled_at)<x-ui.badge tone="danger" dot>{{ __('team.status_disabled') }}</x-ui.badge>
                    @elseif ($member->invited_at && ! $member->updated_at->gt($member->invited_at->addSeconds(5)))<x-ui.badge tone="warning" dot>{{ __('team.status_invited') }}</x-ui.badge>
                    @else<x-ui.badge tone="success" dot>{{ __('team.status_active') }}</x-ui.badge>@endif
                </div>
                @if ($canManage && ! $owner && ! $me)
                    <div class="flex items-center gap-1">
                        <a href="{{ route('team.edit', $member->id) }}" class="btn btn-ghost btn-sm" aria-label="{{ __('admin.edit') }}"><x-ui.icon name="pen" size="4" /></a>
                        <form method="POST" action="{{ route('team.toggle', $member->id) }}">@csrf<button class="btn btn-ghost btn-sm">{{ $member->disabled_at ? __('team.enable') : __('team.disable') }}</button></form>
                    </div>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($users->count() < 2)
        <div class="card mt-4"><x-ui.empty icon="users" :title="__('team.empty_title')" :text="__('team.empty_text')" /></div>
    @endif
</x-layouts.app>
