@props(['title' => null])
<x-layouts.shell :groups="\App\Modules\Core\Support\RestaurantNav::groups()" :title="$title" :section="auth()->user()->restaurant?->name ?? config('app.name')" home="dashboard" announcements>
    @can('billing.manage')<x-billing-notices />@endcan
    {{ $slot }}
</x-layouts.shell>
