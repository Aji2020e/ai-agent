<?php

namespace App\Libraries\Tools;

class ToolRegistry
{
    /** @var array<string, ToolInterface> */
    protected array $tools = [];

    public function __construct()
    {
        $this->register(new WebSearchTool());
        $this->register(new FileReaderTool());
        $this->register(new DatabaseLookupTool());
    }

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    public function run(string $name, array $params = []): ToolResult
    {
        if (! isset($this->tools[$name])) {
            return new ToolResult(false, null, "Tool {$name} tidak ditemukan.");
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
