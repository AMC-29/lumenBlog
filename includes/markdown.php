<?php
/**
 * A small, dependency-free Markdown -> HTML converter.
 *
 * Supports: # ## ### headings, **bold**, *italic*, `inline code`,
 * ```fenced code blocks```, > blockquotes, - / * unordered lists,
 * 1. ordered lists, [links](url), ![images](url), --- rules, paragraphs.
 *
 * All raw text is HTML-escaped before formatting is applied, so the
 * output is always safe to print directly (protects against stored XSS
 * from post content).
 */
function markdown_to_html(string $markdown): string
{
    $lines = preg_split('/\r\n|\r|\n/', $markdown);
    $html = [];
    $inCode = false;
    $codeBuffer = [];
    $listType = null; // 'ul' | 'ol' | null
    $paraBuffer = [];
    $quoteBuffer = [];

    $flushParagraph = function () use (&$paraBuffer, &$html) {
        if ($paraBuffer) {
            $html[] = '<p>' . implode('<br>', $paraBuffer) . '</p>';
            $paraBuffer = [];
        }
    };
    $flushList = function () use (&$listType, &$html) {
        if ($listType) {
            $html[] = "</{$listType}>";
            $listType = null;
        }
    };
    $flushQuote = function () use (&$quoteBuffer, &$html) {
        if ($quoteBuffer) {
            $html[] = '<blockquote><p>' . implode('<br>', $quoteBuffer) . '</p></blockquote>';
            $quoteBuffer = [];
        }
    };

    foreach ($lines as $rawLine) {
        // Fenced code blocks pass through untouched (only HTML-escaped).
        if (preg_match('/^\s*```/', $rawLine)) {
            if ($inCode) {
                $html[] = '<pre><code>' . implode("\n", $codeBuffer) . '</code></pre>';
                $codeBuffer = [];
                $inCode = false;
            } else {
                $flushParagraph();
                $flushList();
                $flushQuote();
                $inCode = true;
            }
            continue;
        }
        if ($inCode) {
            $codeBuffer[] = e($rawLine);
            continue;
        }

        $line = trim($rawLine);

        if ($line === '') {
            $flushParagraph();
            $flushList();
            $flushQuote();
            continue;
        }

        // Horizontal rule
        if (preg_match('/^(-{3,}|\*{3,})$/', $line)) {
            $flushParagraph();
            $flushList();
            $flushQuote();
            $html[] = '<hr>';
            continue;
        }

        // Headings
        if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
            $flushParagraph();
            $flushList();
            $flushQuote();
            $level = strlen($m[1]);
            $html[] = "<h{$level}>" . inline_markdown($m[2]) . "</h{$level}>";
            continue;
        }

        // Blockquote
        if (preg_match('/^>\s?(.*)$/', $line, $m)) {
            $flushParagraph();
            $flushList();
            $quoteBuffer[] = inline_markdown($m[1]);
            continue;
        }

        // Unordered list
        if (preg_match('/^[-*]\s+(.*)$/', $line, $m)) {
            $flushParagraph();
            $flushQuote();
            if ($listType !== 'ul') {
                $flushList();
                $html[] = '<ul>';
                $listType = 'ul';
            }
            $html[] = '<li>' . inline_markdown($m[1]) . '</li>';
            continue;
        }

        // Ordered list
        if (preg_match('/^\d+\.\s+(.*)$/', $line, $m)) {
            $flushParagraph();
            $flushQuote();
            if ($listType !== 'ol') {
                $flushList();
                $html[] = '<ol>';
                $listType = 'ol';
            }
            $html[] = '<li>' . inline_markdown($m[1]) . '</li>';
            continue;
        }

        // Plain paragraph text
        $flushList();
        $flushQuote();
        $paraBuffer[] = inline_markdown($line);
    }

    // Flush anything left over
    if ($inCode && $codeBuffer) {
        $html[] = '<pre><code>' . implode("\n", $codeBuffer) . '</code></pre>';
    }
    $flushParagraph();
    $flushList();
    $flushQuote();

    return implode("\n", $html);
}

/** Apply inline formatting (bold, italic, code, links, images) to one already-plain line. */
function inline_markdown(string $text): string
{
    $text = e($text);

    $text = str_replace(["\x01", "\x02"], ['&#1;', '&#2;'], $text);
    $protected = [];
    $protect = function (string $html) use (&$protected): string {
        $token = "\x01" . count($protected) . "\x02";
        $protected[$token] = $html;
        return $token;
    };

    // Keep formatting syntax out of code spans, link URLs, and link labels.
    $text = preg_replace_callback('/`(.+?)`/', function ($m) use ($protect) {
        return $protect('<code>' . $m[1] . '</code>');
    }, $text);

    // Images: ![alt](url)
    $text = preg_replace_callback('/!\[(.*?)\]\((\S+)\)/', function ($m) use ($protect) {
        if (!markdown_url_is_allowed($m[2])) {
            return $m[1];
        }
        return $protect('<img src="' . $m[2] . '" alt="' . $m[1] . '" loading="lazy">');
    }, $text);

    // Links: [text](url)
    $text = preg_replace_callback('/\[(.*?)\]\((\S+)\)/', function ($m) use ($protect) {
        if (!markdown_url_is_allowed($m[2], true)) {
            return $m[1];
        }
        return $protect('<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>');
    }, $text);

    // Bold: **text**
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);

    // Italic: *text* or _text_
    $text = preg_replace('/(?<!\*)\*(?!\*)(.+?)\*(?!\*)/', '<em>$1</em>', $text);
    $text = preg_replace('/(?<![\p{L}\p{N}])_(?!_)(.+?)(?<!_)_(?![\p{L}\p{N}_])/u', '<em>$1</em>', $text);

    return strtr($text, $protected);
}

/** Allow web/mail links and relative references, but never executable schemes. */
function markdown_url_is_allowed(string $escapedUrl, bool $allowMailto = false): bool
{
    if (preg_match('/[\x00-\x20\x7f]/', $escapedUrl)) {
        return false;
    }

    if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $escapedUrl, $matches)) {
        $allowedSchemes = $allowMailto ? ['http', 'https', 'mailto'] : ['http', 'https'];
        return in_array(strtolower($matches[1]), $allowedSchemes, true);
    }

    return true;
}
