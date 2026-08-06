<?php

namespace Modules\Commerce\Services\Catalog;

use DOMDocument;
use DOMElement;
use DOMNode;

class ProductDescriptionSanitizer
{
    /** @var string[] */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'h2', 'h3', 'ul', 'ol', 'li',
        'a', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    public function sanitize(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="product-description-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('product-description-root');
        if ($root === null) {
            return null;
        }

        $this->sanitizeChildren($root);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output) === '' ? null : trim($output);
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);
            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $this->unwrap($child);

                continue;
            }

            $this->sanitizeAttributes($child, $tag);
            $this->sanitizeChildren($child);
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            if ($tag !== 'a' || $name !== 'href') {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($tag !== 'a' || ! $element->hasAttribute('href')) {
            return;
        }

        $href = trim($element->getAttribute('href'));
        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));
        if ($scheme !== '' && ! in_array($scheme, ['http', 'https', 'mailto'], true)) {
            $element->removeAttribute('href');

            return;
        }

        $element->setAttribute('rel', 'nofollow noopener noreferrer');
    }

    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }
}