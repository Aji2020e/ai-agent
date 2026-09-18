<?php

namespace App\Libraries\Skills;

interface SkillInterface
{
    /**
     * Execute skill and return structured result.
     *
     * @param array<string, mixed> $params
     * @return SkillResult
     */
    public function execute(array $params): SkillResult;

    /**
     * Return skill name.
     */
    public function getName(): string;

    /**
     * Return skill description.
     */
    public function getDescription(): string;
}
