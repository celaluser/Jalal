@props(['title' => null])
<x-layouts.shell :groups="\App\Modules\Admin\Support\AdminNav::groups()" :title="$title" :section="__('admin.panel')" home="admin.dashboard">{{ $slot }}</x-layouts.shell>
