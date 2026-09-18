<?php

namespace App\Libraries\Tools;

interface ToolInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function run(array $params): ToolResult;
}
