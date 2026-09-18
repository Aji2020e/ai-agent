<?php

namespace App\Libraries;

use App\Libraries\Skills\SkillResult;

class PromptBuilder
{
    public static function build(string $skillName, SkillResult $result, string $userMessage): array
    {
        $data = $result->data;

        $systemPrompt = "Kamu adalah AI Assistant dengan mode skill: {$skillName}.\n";
        $systemPrompt .= "Jawab dalam bahasa yang sama dengan user. Jangan membuat data palsu.\n";

        if (! empty($data['guide'])) {
            $systemPrompt .= "\n[PANDUAN SKILL]\n" . $data['guide'] . "\n";
        }

        if (! empty($data['modules'])) {
            $systemPrompt .= "\n[MODUL PANDUAN]\n";
            foreach ($data['modules'] as $module) {
                $systemPrompt .= "- " . ($module['module_name'] ?? '') . ": " . ($module['description'] ?? '') . "\n";
            }
        }

        if (! empty($data['db_data'])) {
            $systemPrompt .= "\n[DATA AKADEMIK]\n" . $data['db_data'] . "\n";
        }

        if (! empty($data['web_results'])) {
            $systemPrompt .= "\n[HASIL WEB SEARCH]\n" . $data['web_results'] . "\n";
        }

        if (! empty($data['file_content'])) {
            $systemPrompt .= "\n[ISI FILE]\n" . $data['file_content'] . "\n";
        }

        if (! $result->success && $result->error) {
            $systemPrompt .= "\n[CATATAN]\n" . $result->error;
            if ($result->suggestion) {
                $systemPrompt .= "\nSaran: " . $result->suggestion;
            }
        }

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userMessage],
        ];
    }
}
