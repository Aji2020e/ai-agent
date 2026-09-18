<?php

if (! function_exists('renderMessage')) {
    function renderMessage(string $role, string $content, ?string $timestamp = null, ?string $attachment = null): string
    {
        $avatarIcon = $role === 'user' ? 'bi-person' : 'bi-cpu';
        $avatarClass = $role === 'user' ? 'user-avatar' : 'ai-avatar';
        $time = $timestamp ? date('H:i', strtotime($timestamp)) : date('H:i');

        $escapedContent = esc($content);
        $escapedContent = nl2br($escapedContent);

        $badge = '';
        if ($attachment !== null && $attachment !== '') {
            $badge = '<div class="mb-2"><span class="badge text-bg-secondary"><i class="bi bi-paperclip"></i> '
                . esc($attachment) . '</span></div>';
        }

        $avatarHtml = '<div class="message-avatar ' . $avatarClass . '"><i class="bi ' . $avatarIcon . '"></i></div>';

        return '<div class="message-row ' . $role . '">'
            . ($role === 'assistant' ? $avatarHtml : '')
            . '<div class="message-content">'
            . '<div class="message-bubble">' . $badge . $escapedContent . '</div>'
            . '<div class="message-time">' . $time . '</div>'
            . '</div>'
            . ($role === 'user' ? $avatarHtml : '')
            . '</div>';
    }
}
