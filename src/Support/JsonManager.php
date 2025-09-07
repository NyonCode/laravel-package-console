<?php

namespace NyonCode\LaravelPackageConsole\Support;

class JsonManager
{
    public array $data = [];

    protected string $filePath;

    public function __construct(string $packagePath)
    {
        $this->filePath = $packagePath.'/composer.json';
        $this->load();
    }

    protected function load(): void
    {
        if (file_exists($this->filePath)) {
            $this->data = json_decode(file_get_contents($this->filePath), true) ?? [];
        } else {
            $this->data = [];
        }
    }

    public function save(): void
    {
        file_put_contents($this->filePath, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function setMultiple(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function all(): array
    {
        return $this->data;
    }
}
