<?php

declare(strict_types=1);

final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'ul', 'ol', 'li',
        'a', 'h2', 'h3', 'h4', 'blockquote', 'div', 'span', 'hr',
    ];

    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'form',
        'textarea', 'noscript', 'svg', 'math',
    ];

    public function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;

        foreach (self::DROP_WITH_CONTENT as $tag) {
            $html = preg_replace('#<' . $tag . '\b[^>]*>.*?</' . $tag . '>#is', '', $html) ?? $html;
            $html = preg_replace('#<' . $tag . '\b[^>]*/?>#is', '', $html) ?? $html;
        }

        $allowed = '<' . implode('><', self::ALLOWED_TAGS) . '>';
        $html = strip_tags($html, $allowed);

        $html = preg_replace_callback(
            '/<(\/?)([a-z0-9]+)([^>]*)>/i',
            [$this, 'rebuildTag'],
            $html
        ) ?? $html;

        return trim($html);
    }

    /** @param array<int, string> $match */
    private function rebuildTag(array $match): string
    {
        $closing = $match[1] === '/';
        $tag = strtolower($match[2]);

        if (!in_array($tag, self::ALLOWED_TAGS, true)) {
            return '';
        }

        if ($closing) {
            return '</' . $tag . '>';
        }

        if ($tag === 'br' || $tag === 'hr') {
            return '<' . $tag . '>';
        }

        $attrs = '';
        if ($tag === 'a' && preg_match('/href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $match[3], $href) === 1) {
            $raw = $href[1] ?? '';
            if ($raw === '') {
                $raw = $href[2] ?? '';
            }
            if ($raw === '') {
                $raw = $href[3] ?? '';
            }
            $url = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (preg_match('#^(https?:|mailto:)#i', $url) === 1) {
                $attrs = ' href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
            }
        }

        return '<' . $tag . $attrs . '>';
    }
}
