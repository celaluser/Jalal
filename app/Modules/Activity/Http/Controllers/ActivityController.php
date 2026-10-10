<?php

namespace App\Modules\Activity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Activity\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The restaurant's own audit trail: who changed what, and when. */
class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        $subject = $request->query('subject');
        $q = trim((string) $request->query('q'));
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

        $logs = ActivityLog::query()
            ->when($subject, fn ($query) => $query->where('subject_type', $subject))
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('label', 'like', $like)->orWhere('user_name', 'like', $like)))
            ->orderByDesc('id')->paginate(40)->withQueryString();

        return view('activity::index', [
            'logs' => $logs, 'subject' => $subject, 'q' => $q,
            'subjects' => ActivityLog::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
        ]);
    }
}
