<?php

namespace App\Libraries\Tools;

use Config\Services;

class WebSearchTool implements ToolInterface
{
    public function getName(): string
    {
        return 'web_search';
    }

    public function getDescription(): string
    {
        return 'Mencari informasi di internet.';
    }

    public function run(array $params): ToolResult
    {
        $query = $params['query'] ?? '';

        if (empty($query)) {
            return new ToolResult(false, null, 'Query pencarian kosong.');
        }

        try {
            $results = $this->searchDuckDuckGo($query);

            return new ToolResult(true, $results, null, "Hasil pencarian untuk: {$query}");
        } catch (\Throwable $e) {
            return new ToolResult(false, null, $e->getMessage(), 'Gagal melakukan web search.');
        }
    }

    protected function searchDuckDuckGo(string $query): array
    {
        $client = Services::curlrequest();
        $url = 'https://html.duckduckgo.com/html/?q=' . urlencode($query);

        $response = $client->get($url, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.0',
            ],
            'timeout' => 15,
        ]);

        $html = (string) $response->getBody();
        $results = [];

        if (preg_match_all('/<a[^>]+class="result__a"[^>]*>(.*?)<\/a>.*?<a[^>]+class="result__snippet"[^>]*>(.*?)<\/a>/s', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $results[] = [
                    'title'   => strip_tags($match[1]),
                    'snippet' => strip_tags($match[2]),
                ];
            }
        }

        return array_slice($results, 0, 5);
    }
}
