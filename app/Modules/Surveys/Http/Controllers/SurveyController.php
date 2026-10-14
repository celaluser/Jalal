<?php

namespace App\Modules\Surveys\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Surveys\Models\Survey;
use App\Modules\Surveys\Services\SurveyResults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The restaurant's side: build a survey, share its link, read the results. */
class SurveyController extends Controller
{
    public function __construct(private readonly SurveyResults $results) {}

    public function index(Request $request): View
    {
        return view('surveys::staff.index', ['surveys' => Survey::orderByDesc('id')->get(), 'restaurant' => $request->user()->restaurant]);
    }

    public function create(): View
    {
        return view('surveys::staff.form', ['survey' => new Survey(['is_active' => true, 'questions' => [
            ['id' => 'q'.Str::lower(Str::random(5)), 'type' => 'nps', 'label' => __('surveys.sample_nps'), 'required' => true, 'options' => []],
            ['id' => 'q'.Str::lower(Str::random(5)), 'type' => 'text', 'label' => __('surveys.sample_text'), 'required' => false, 'options' => []],
        ]])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $survey = new Survey($data);
        $survey->slug = $this->uniqueSlug($data['title']);
        $survey->save();

        return redirect()->route('surveys.index')->with('status', __('surveys.saved'));
    }

    public function edit(Survey $survey): View
    {
        return view('surveys::staff.form', ['survey' => $survey]);
    }

    public function update(Request $request, Survey $survey): RedirectResponse
    {
        $survey->update($this->validated($request));

        return redirect()->route('surveys.index')->with('status', __('surveys.saved'));
    }

    public function destroy(Survey $survey): RedirectResponse
    {
        $survey->delete();

        return redirect()->route('surveys.index')->with('status', __('surveys.deleted'));
    }

    public function results(Request $request, Survey $survey): View
    {
        return view('surveys::staff.results', ['survey' => $survey, 'summary' => $this->results->summarize($survey), 'restaurant' => $request->user()->restaurant]);
    }

    public function export(Survey $survey): StreamedResponse
    {
        $rows = $this->results->rows($survey);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'survey-'.$survey->slug.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'intro' => ['nullable', 'string', 'max:500'],
            'thank_you' => ['nullable', 'string', 'max:300'],
            'is_active' => ['nullable', 'boolean'],
            'after_order' => ['nullable', 'boolean'],
            'questions' => ['required', 'array', 'min:1', 'max:'.Survey::MAX_QUESTIONS],
            'questions.*.id' => ['nullable', 'string', 'regex:/^q[a-z0-9]{3,10}$/'],
            'questions.*.type' => ['required', 'in:'.implode(',', Survey::TYPES)],
            'questions.*.label' => ['required', 'string', 'max:160'],
            'questions.*.required' => ['nullable', 'boolean'],
            'questions.*.options' => ['nullable', 'string', 'max:800'],
        ]);

        $questions = collect($data['questions'])->values()->map(function (array $q) {
            $options = $q['type'] === 'choice' ? collect(preg_split('/\R/', (string) ($q['options'] ?? '')))->map(fn ($o) => Str::limit(trim($o), 60, ''))->filter()->unique()->take(10)->values()->all() : [];

            return ['id' => ($q['id'] ?? null) ?: 'q'.Str::lower(Str::random(5)), 'type' => $q['type'], 'label' => trim($q['label']), 'required' => (bool) ($q['required'] ?? false), 'options' => $options];
        });

        // A choice question needs something to choose from, and two questions must not share an id (they would overwrite each other's answers).
        abort_if($questions->contains(fn ($q) => $q['type'] === 'choice' && count($q['options']) < 2), 422, __('surveys.choice_needs_options'));
        abort_if($questions->pluck('id')->duplicates()->isNotEmpty(), 422);

        return [
            'title' => trim($data['title']), 'intro' => $data['intro'] ?? null, 'thank_you' => $data['thank_you'] ?? null,
            'is_active' => $request->boolean('is_active'), 'after_order' => $request->boolean('after_order'), 'questions' => $questions->all(),
        ];
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'survey';
        $slug = $base;

        for ($i = 2; Survey::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
