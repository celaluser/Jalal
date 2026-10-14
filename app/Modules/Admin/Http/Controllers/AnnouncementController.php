<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Support\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin::announcements.index', ['announcements' => Announcement::latest('id')->paginate(25)]);
    }

    public function create(): View
    {
        return view('admin::announcements.form', ['announcement' => new Announcement(['level' => 'info', 'is_active' => true, 'is_dismissible' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Announcement::create($this->validated($request));

        return redirect()->route('admin.announcements.index')->with('status', __('admin.saved'));
    }

    public function edit(Announcement $announcement): View
    {
        return view('admin::announcements.form', ['announcement' => $announcement]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($this->validated($request));

        return redirect()->route('admin.announcements.index')->with('status', __('admin.saved'));
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('status', __('admin.cms.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['nullable', 'string', 'max:2000'],
            'level' => ['required', Rule::in(Announcement::LEVELS)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        return $data + ['body' => null, 'starts_at' => null, 'ends_at' => null, 'is_active' => $request->boolean('is_active'), 'is_dismissible' => $request->boolean('is_dismissible')];
    }
}
