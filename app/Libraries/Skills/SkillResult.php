<?php

namespace App\Libraries\Skills;

class SkillResult
{
    public function __construct(
        public bool $success,
        public string $message,
        public array $data = [],
        public array $sources = [],
        public ?string $error = null,
        public ?string $suggestion = null,
    ) {
    }

    public function toPromptContext(): string
    {
        $text = $this->message . "\n";

        if (! empty($this->data)) {
            $text .= json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }

        if (! $this->success && $this->error) {
            $text .= "Error: " . $this->error . "\n";
        }

        if ($this->suggestion) {
            $text .= "Saran: " . $this->suggestion . "\n";
        }

        return $text;
    }
}
