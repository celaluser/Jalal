<?php

namespace App\Modules\Admin\Services;

use App\Modules\Core\Services\SettingsService;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs the scheduler and the queue from a web request, for hosting without a shell or without a real cron:
 *  - an address (/cron/<secret>) that any cron service, cPanel "wget" job or uptime pinger can call every minute, and
 *  - an optional fallback that does the same after a visitor's page has been sent, when no cron has been seen for a while.
 * Only one run happens at a time, and never more often than once a minute.
 */
class WebCron
{
    public const LOCK = 'web-cron.lock';

    public const GATE = 'web-cron.gate';

    public function __construct(private readonly SettingsService $settings) {}

    /** The secret in the cron address. Created on first use. */
    public function token(): string
    {
        $token = (string) $this->settings->get('system.cron_token');

        if ($token === '') {
            $token = Str::random(40);
            $this->settings->set('system.cron_token', $token);
        }

        return $token;
    }

    public function regenerate(): string
    {
        $this->settings->set('system.cron_token', $token = Str::random(40));

        return $token;
    }

    public function valid(?string $given): bool
    {
        return $given !== null && hash_equals($this->token(), $given);
    }

    /** 'auto' (default): fallback only when the real cron is silent. 'off': never. 'always': after every minute of traffic. */
    public function mode(): string
    {
        $mode = (string) $this->settings->get('system.web_cron', 'auto');

        return in_array($mode, ['auto', 'off', 'always'], true) ? $mode : 'auto';
    }

    /**
     * One pass: due scheduled tasks, then queued jobs for up to $seconds.
     *
     * @return array{ran: bool, scheduler: string, queue: string}
     */
    public function run(int $seconds = 45): array
    {
        $lock = Cache::lock(self::LOCK, $seconds + 15);

        if (! $lock->get()) {
            return ['ran' => false, 'scheduler' => 'busy', 'queue' => 'busy'];
        }

        try {
            return ['ran' => true, 'scheduler' => $this->runScheduler(), 'queue' => $this->call('queue:work', ['--stop-when-empty' => true, '--max-time' => $seconds, '--sleep' => 1, '--tries' => 3])];
        } finally {
            $lock->release();
        }
    }

    /**
     * Due scheduled tasks, run IN this process. `schedule:run` starts each command as a separate `php artisan` process, which
     * fails when PHP is running as FastCGI/FPM (that binary is not the command-line PHP) or when exec() is disabled: both common on shared hosting.
     */
    public function runScheduler(): string
    {
        $done = [];

        try {
            foreach (app(Schedule::class)->dueEvents(app()) as $event) {
                if ($event instanceof CallbackEvent) {
                    $event->run(app());
                    $done[] = 'callback';
                } elseif (preg_match("/'artisan'\s+(.+?)\s*(?:>|$)/", (string) $event->command, $m)) {
                    Artisan::call(trim($m[1]));
                    $done[] = trim(explode(' ', trim($m[1]))[0]);
                }
            }
        } catch (Throwable $e) {
            report($e);

            return 'error: '.Str::limit($e->getMessage(), 200);
        }

        return $done ? 'ran '.implode(', ', $done) : 'nothing due';
    }

    /** The fallback, called after the response was sent. Cheap when a real cron is running (one cache read). */
    public function maybeRunAfterResponse(): void
    {
        $mode = $this->mode();

        if ($mode === 'off') {
            return;
        }

        $silent = (int) Cache::get(SystemInfo::SCHEDULER_KEY, 0) < now()->subSeconds(150)->timestamp;

        if (($mode === 'auto' && ! $silent) || ! Cache::add(self::GATE, 1, 60)) {
            return;
        }

        try {
            $this->run(15);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @param array<string, mixed> $args */
    private function call(string $command, array $args = []): string
    {
        try {
            Artisan::call($command, $args);

            return trim(Str::limit(Artisan::output(), 400)) ?: 'ok';
        } catch (Throwable $e) {
            report($e);

            return 'error: '.Str::limit($e->getMessage(), 200);
        }
    }
}
