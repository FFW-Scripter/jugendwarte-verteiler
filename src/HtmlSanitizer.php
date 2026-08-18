<?php

declare(strict_types=1);

final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'ul', 'ol', 'li',
        'a', 'h1', 'h2', 'h3', 'h4', 'blockquote', 'div', 'span', 'hr', 'img',
    ];

    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'form',
        'textarea', 'noscript', 'svg', 'math',
    ];

    private int $inlineImageBytes = 0;

    public function sanitize(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $this->inlineImageBytes = 0;

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

        if ($tag === 'img') {
            return $this->rebuildImageTag($match[3]);
        }

        $attrs = '';
        if ($tag === 'a') {
            $attrs = $this->linkAttributes($match[3]);
        }

        return '<' . $tag . $attrs . '>';
    }

    private function rebuildImageTag(string $rawAttrs): string
    {
        if (!preg_match('/src\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $rawAttrs, $src) === 1) {
            return '';
        }

        $value = $src[1] ?? '';
        if ($value === '') {
            $value = $src[2] ?? '';
        }
        if ($value === '') {
            $value = $src[3] ?? '';
        }

        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (!preg_match('#^data:image/(jpeg|jpg|png|gif|webp);base64,([a-z0-9+/=\r\n]+)$#i', $value, $parts) === 1) {
            return '';
        }

        $binary = base64_decode(str_replace(["\r", "\n"], '', $parts[2]), true);
        if ($binary === false) {
            return '';
        }

        $size = strlen($binary);
        if ($size < 1 || $size > MAX_INLINE_IMAGE_BYTES) {
            return '';
        }

        if ($this->inlineImageBytes + $size > MAX_INLINE_IMAGES_TOTAL_BYTES) {
            return '';
        }

        $this->inlineImageBytes += $size;
        $mime = strtolower($parts[1]) === 'jpg' ? 'jpeg' : strtolower($parts[1]);
        $safeSrc = 'data:image/' . $mime . ';base64,' . base64_encode($binary);

        return '<img src="' . htmlspecialchars($safeSrc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '" alt="" class="inline-image">';
    }

    private function linkAttributes(string $rawAttrs): string
    {
        if (preg_match('/href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $rawAttrs, $href) !== 1) {
            return '';
        }

        $raw = $href[1] ?? '';
        if ($raw === '') {
            $raw = $href[2] ?? '';
        }
        if ($raw === '') {
            $raw = $href[3] ?? '';
        }

        $url = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (preg_match('#^(https?:|mailto:)#i', $url) !== 1) {
            return '';
        }

        return ' href="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
    }
}
