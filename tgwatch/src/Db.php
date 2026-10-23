<?php
declare(strict_types=1);

namespace TgWatch;

use PDO;

final class Db
{
    public PDO $pdo;

    public function __construct(string $path)
    {
        $this->pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        // Cron ve webhook aynı anda yazabilir: kilitlenmede 5 sn bekle, WAL ile okuyucular engellenmez.
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->migrate();
    }

    private function migrate(): void
    {
        $this->pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS channels (
    id INTEGER PRIMARY KEY,
    username TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL DEFAULT '',
    enabled INTEGER NOT NULL DEFAULT 1,
    last_post_id INTEGER,
    last_checked_at INTEGER,
    last_ok_at INTEGER,
    last_error TEXT,
    created_at INTEGER NOT NULL
);
CREATE TABLE IF NOT EXISTS jobs (
    id INTEGER PRIMARY KEY,
    channel TEXT NOT NULL,
    msg_id INTEGER NOT NULL,
    text_hash TEXT NOT NULL,
    source_text TEXT NOT NULL,
    items_json TEXT NOT NULL,
    status TEXT NOT NULL,
    note TEXT NOT NULL DEFAULT '',
    tg_message_id INTEGER,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL,
    UNIQUE(channel, msg_id)
);
CREATE INDEX IF NOT EXISTS jobs_status ON jobs(status);
CREATE INDEX IF NOT EXISTS jobs_hash ON jobs(channel, text_hash);
CREATE TABLE IF NOT EXISTS settings (
    k TEXT PRIMARY KEY,
    v TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY,
    ts INTEGER NOT NULL,
    level TEXT NOT NULL,
    source TEXT NOT NULL,
    message TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS login_attempts (
    ip TEXT PRIMARY KEY,
    fails INTEGER NOT NULL,
    locked_until INTEGER NOT NULL DEFAULT 0,
    last_at INTEGER NOT NULL
);
SQL);
    }

    public function all(string $sql, array $args = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    }

    public function one(string $sql, array $args = []): ?array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public function run(string $sql, array $args = []): int
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($args);
        return $st->rowCount();
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $row = $this->one('SELECT v FROM settings WHERE k = ?', [$key]);
        return $row === null ? $default : $row['v'];
    }

    public function set(string $key, string $value): void
    {
        $this->run('INSERT INTO settings(k, v) VALUES(?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v', [$key, $value]);
    }

    public function getJson(string $key, $default = null)
    {
        $v = $this->get($key);
        if ($v === null) {
            return $default;
        }
        $d = json_decode($v, true);
        return $d === null ? $default : $d;
    }

    public function setJson(string $key, $value): void
    {
        $this->set($key, json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function log(string $level, string $source, string $message): void
    {
        $this->run('INSERT INTO logs(ts, level, source, message) VALUES(?, ?, ?, ?)', [time(), $level, $source, mb_substr($message, 0, 4000)]);
        // Son 5000 kaydı tut.
        if (random_int(1, 50) === 1) {
            $this->pdo->exec('DELETE FROM logs WHERE id <= (SELECT MAX(id) - 5000 FROM logs)');
        }
    }

    // --- işler ---

    public function job(int $id): ?array
    {
        $row = $this->one('SELECT * FROM jobs WHERE id = ?', [$id]);
        if ($row !== null) {
            $row['items'] = json_decode($row['items_json'], true) ?: [];
        }
        return $row;
    }

    public function saveItems(int $id, array $items): void
    {
        $this->run('UPDATE jobs SET items_json = ?, updated_at = ? WHERE id = ?', [
            json_encode($items, JSON_UNESCAPED_UNICODE), time(), $id,
        ]);
    }

    public function setStatus(int $id, string $status, string $note = ''): void
    {
        $this->run('UPDATE jobs SET status = ?, note = ?, updated_at = ? WHERE id = ?', [$status, $note, time(), $id]);
    }

    /**
     * Durumu yalnızca beklenen eski durumlardaysa değiştirir (iki kez basılan butonlara karşı).
     * @return bool değişti mi
     */
    public function transition(int $id, array $from, string $to, string $note = ''): bool
    {
        $in = implode(',', array_fill(0, count($from), '?'));
        return $this->run(
            "UPDATE jobs SET status = ?, note = ?, updated_at = ? WHERE id = ? AND status IN ($in)",
            array_merge([$to, $note, time(), $id], $from)
        ) === 1;
    }
}
