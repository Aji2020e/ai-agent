<?php

namespace App\Libraries\Tools;

class ToolResult
{
    public function __construct(
        public bool $success,
        public mixed $data = null,
        public ?string $error = null,
        public ?string $message = null,
    ) {
    }

    public function toContextString(): string
    {
        $text = $this->message ? $this->message . "\n" : '';

        if (is_array($this->data) || is_object($this->data)) {
            $text .= json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } elseif (is_string($this->data)) {
            $text .= $this->data;
        }

        if (! $this->success && $this->error) {
            $text .= "\nError: " . $this->error;
        }

        return $text;
    }
}
