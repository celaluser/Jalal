<?php

class HistoryStore
{
    private string $file;
    private int $limit;

    public function __construct(string $file, int $limit = 100)
    {
        $this->file  = $file;
        $this->limit = $limit;
    }

    /**
     * Bütün tarixçəni (ən yeni birinci) qaytarır.
     */
    public function all(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        $json = file_get_contents($this->file);
        $data = json_decode((string)$json, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Yeni bir qeyd əlavə edir (siyahının başına).
     */
    public function add(array $entry): void
    {
        $items = $this->all();
        array_unshift($items, $entry);
        $items = array_slice($items, 0, $this->limit);
        $this->save($items);
    }

    /**
     * ID-yə görə tək bir qeydi silir.
     */
    public function delete(string $id): void
    {
        $items = $this->all();
        $items = array_values(array_filter(
            $items,
            static fn(array $item): bool => ($item['id'] ?? '') !== $id
        ));
        $this->save($items);
    }

    /**
     * Bütün tarixçəni təmizləyir.
     */
    public function clear(): void
    {
        $this->save([]);
    }

    private function save(array $items): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($this->file, json_encode($items, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
