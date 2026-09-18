<?php

namespace App\Libraries\Tools;

use App\Libraries\Auth\PolicyGuard;

class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    protected array $tools = [];

    /**
     * @param bool $respectPolicy bila true, hanya tool yang diizinkan kebijakan
     *                            klien saat ini yang didaftarkan. Tool yang tidak
     *                            diizinkan TIDAK TERLIHAT oleh AI sama sekali —
     *                            ini lebih kuat daripada menolak saat dipanggil,
     *                            karena model tidak bisa mencoba menebak namanya.
     */
    public function __construct(bool $respectPolicy = true)
    {
        foreach (self::availableTools() as $tool) {
            if ($respectPolicy && ! PolicyGuard::canUseTool($tool->getName())) {
                continue;
            }
            $this->register($tool);
        }
    }

    /**
     * Daftar seluruh tool yang dikenal sistem, apa pun kebijakannya.
     *
     * @return ToolInterface[]
     */
    public static function availableTools(): array
    {
        return [
            new WebSearchTool(),
            new FileReaderTool(),
            new DatabaseLookupTool(),
        ];
    }

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    public function run(string $name, array $params = []): ToolResult
    {
        if (! isset($this->tools[$name])) {
            // Pesan sengaja tidak menyebut tool apa saja yang ada,
            // agar tidak membocorkan kemampuan sistem ke pemanggil.
            return new ToolResult(false, null, "Tool tidak tersedia untuk permintaan ini.");
        }

        // Pertahanan kedua: meski terdaftar, tegaskan lagi di sini.
        // Melindungi dari registry yang dibuat dengan $respectPolicy = false.
        if (! PolicyGuard::canUseTool($name)) {
            return new ToolResult(false, null, "Tool tidak tersedia untuk permintaan ini.");
        }

        return $this->tools[$name]->run($params);
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    /** @return array<string, ToolInterface> */
    public function all(): array
    {
        return $this->tools;
    }
}
