<?php

namespace App\Modules\Surveys\Services;

use App\Modules\Surveys\Models\Survey;

/** Turns the answers to a survey into numbers per question: averages, NPS, counts, and the latest comments. */
class SurveyResults
{
    /**
     * @return array<int, array<string, mixed>> one entry per question, in order
     */
    public function summarize(Survey $survey): array
    {
        $answers = $survey->responses()->latest('id')->limit(5000)->pluck('answers');

        return collect($survey->questions)->map(function (array $q) use ($answers) {
            $values = $answers->map(fn ($a) => $a[$q['id']] ?? null)->filter(fn ($v) => $v !== null && $v !== '' && $v !== []);
            $out = ['id' => $q['id'], 'type' => $q['type'], 'label' => $q['label'], 'count' => $values->count()];

            switch ($q['type']) {
                case 'rating':
                    $nums = $values->map(fn ($v) => (int) $v)->filter(fn ($v) => $v >= 1 && $v <= 5);
                    $out['count'] = $nums->count();
                    $out['average'] = $nums->isEmpty() ? null : round($nums->avg(), 2);
                    $out['distribution'] = collect(range(1, 5))->mapWithKeys(fn ($n) => [$n => $nums->filter(fn ($v) => $v === $n)->count()])->all();
                    break;
                case 'nps':
                    $nums = $values->map(fn ($v) => (int) $v)->filter(fn ($v) => $v >= 0 && $v <= 10);
                    $n = $nums->count();
                    $out['count'] = $n;
                    $out['score'] = $n === 0 ? null : (int) round(($nums->filter(fn ($v) => $v >= 9)->count() - $nums->filter(fn ($v) => $v <= 6)->count()) / $n * 100);
                    $out['distribution'] = collect(range(0, 10))->mapWithKeys(fn ($i) => [$i => $nums->filter(fn ($v) => $v === $i)->count()])->all();
                    break;
                case 'choice':
                    $out['options'] = collect($q['options'] ?? [])->mapWithKeys(fn ($o) => [$o => $values->flatten()->filter(fn ($v) => $v === $o)->count()])->all();
                    break;
                default:
                    $out['latest'] = $values->take(20)->map(fn ($v) => (string) $v)->values()->all();
            }

            return $out;
        })->all();
    }

    /** @return list<list<string>> a header row and one row per response, for a spreadsheet */
    public function rows(Survey $survey): array
    {
        $rows = [array_merge(['#', 'date'], array_map(fn ($q) => $q['label'], $survey->questions))];

        foreach ($survey->responses()->orderBy('id')->limit(10000)->get() as $r) {
            $rows[] = array_merge([(string) $r->id, $r->created_at->toDateTimeString()], array_map(function ($q) use ($r) {
                $v = $r->answers[$q['id']] ?? '';
                $v = is_array($v) ? implode(', ', $v) : (string) $v;

                // A cell that starts with = + - @ would be run as a formula by a spreadsheet.
                return $v !== '' && str_contains('=+-@', $v[0]) ? "'".$v : $v;
            }, $survey->questions));
        }

        return $rows;
    }
}
