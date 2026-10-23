<?php

namespace App\Actions\Content;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Keeps only simple formatting in HTML written by admins (terms and similar
 * texts) before it is shown on public pages: no scripts, styles, event
 * handlers or other attributes, and links only to web, mail and phone addresses.
 */
class SanitizeHtml
{
    private bool $allowPlaceholderLinks = false;

    private const array ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'blockquote', 'a'];

    /**
     * Elements whose content is dropped together with the element.
     */
    private const array DROPPED_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'svg', 'math', 'noscript', 'form', 'textarea', 'select'];

    /**
     * @param  bool  $allowPlaceholderLinks  also keep links like href="{link}" (email templates fill these in when sending)
     */
    public function handle(?string $html, bool $allowPlaceholderLinks = false): string
    {
        $this->allowPlaceholderLinks = $allowPlaceholderLinks;

        if (blank($html)) {
            return '';
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('root');
        if ($root === null) {
            return '';
        }

        $this->clean($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        // The HTML writer URL-encodes the braces of placeholder links; put them back.
        if ($this->allowPlaceholderLinks) {
            $output = (string) preg_replace('/href="%7B([a-z_]+)%7D"/', 'href="{$1}"', $output);
        }

        return trim($output);
    }

    private function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROPPED_TAGS, true)) {
                $node->removeChild($child);

                continue;
            }

            $this->clean($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            $href = $tag === 'a' ? trim($child->getAttribute('href')) : '';
            foreach (iterator_to_array($child->attributes) as $attribute) {
                $child->removeAttribute($attribute->nodeName);
            }
            $isPlaceholder = $this->allowPlaceholderLinks && preg_match('/^\{[a-z_]+\}$/', $href) === 1;
            if ($tag === 'a' && ($isPlaceholder || preg_match('#^(https?://|mailto:|tel:)#i', $href) === 1)) {
                $child->setAttribute('href', $href);
                $child->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }
}
