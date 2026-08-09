<?php

namespace Modules\Commerce\Services\Storefront\RivaLang;

class RivaLangCompiler
{
    private const ALLOWED_COMPONENTS = [
        'riva-section', 'riva-container', 'riva-grid', 'riva-stack', 'riva-heading', 'riva-text',
        'riva-button', 'riva-link', 'riva-image', 'riva-video', 'riva-product-card',
        'riva-product-grid', 'riva-money', 'riva-price', 'riva-variant-picker',
        'riva-add-to-cart', 'riva-carousel', 'riva-badge', 'riva-slot',
        'section', 'container', 'grid', 'stack', 'heading', 'text', 'button-link', 'badge',
    ];

    private const ALLOWED_FILTERS = [
        'money', 'money_without_currency', 'percentage', 'inventory_label', 'discount_percentage',
        'asset_url', 'product_url', 'collection_url', 'page_url', 'image_url', 'responsive_image',
        'escape', 'truncate', 'lower', 'upper', 'capitalize', 'default', 'date', 'translate',
    ];

    private const SAFE_ROOTS = [
        'store', 'theme', 'page', 'product', 'variant', 'collection', 'cart', 'customer',
        'navigation', 'section', 'block', 'media', 'localization', 'request_context',
    ];

    /**
     * @return array{ir: array<string, mixed>, diagnostics: array<int, array<string, mixed>>}
     */
    public function compile(string $file, string $source): array
    {
        $diagnostics = [];
        $nodes = [];
        $depth = 0;
        $maxDepth = 0;

        preg_match_all('/(<\\/?([a-z][a-z0-9-]*)(?:\\s[^>]*)?>)|({{\\s*(.*?)\\s*}})|({%\\s*(.*?)\\s*%})/is', $source, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as $index => $match) {
            $token = $match[0];
            $offset = $match[1];
            [$line, $column] = $this->lineColumn($source, $offset);

            if ($matches[2][$index][1] !== -1) {
                $tag = strtolower($matches[2][$index][0]);
                $closing = str_starts_with($token, '</');
                if (! $closing && ! in_array($tag, self::ALLOWED_COMPONENTS, true)) {
                    $diagnostics[] = new RivaLangDiagnostic('error', $file, $line, $column, "Unknown component: <{$tag}>", $this->suggest($tag, self::ALLOWED_COMPONENTS));
                }
                $depth += $closing ? -1 : 1;
                $maxDepth = max($maxDepth, $depth);
                if ($depth < 0) {
                    $diagnostics[] = new RivaLangDiagnostic('error', $file, $line, $column, 'Unexpected closing tag.');
                    $depth = 0;
                }
                $nodes[] = ['type' => $closing ? 'CloseElement' : 'Element', 'name' => $tag, 'loc' => compact('line', 'column')];
            }

            if ($matches[4][$index][1] !== -1) {
                $expression = trim($matches[4][$index][0]);
                $diagnostics = [...$diagnostics, ...$this->validateExpression($file, $source, $offset, $expression)];
                $nodes[] = ['type' => 'OutputExpression', 'expression' => $expression, 'loc' => compact('line', 'column')];
            }

            if ($matches[6][$index][1] !== -1) {
                $statement = trim($matches[6][$index][0]);
                $diagnostics = [...$diagnostics, ...$this->validateStatement($file, $source, $offset, $statement)];
                $nodes[] = ['type' => 'Statement', 'statement' => $statement, 'loc' => compact('line', 'column')];
            }
        }

        if ($maxDepth > 32) {
            $diagnostics[] = new RivaLangDiagnostic('error', $file, 1, 1, 'Template nesting is too deep. Maximum depth is 32.');
        }

        return [
            'ir' => [
                'version' => 1,
                'file' => $file,
                'node_count' => count($nodes),
                'max_depth' => $maxDepth,
                'nodes' => $nodes,
            ],
            'diagnostics' => array_map(fn (RivaLangDiagnostic $diagnostic): array => $diagnostic->toArray(), $diagnostics),
        ];
    }

    /**
     * @return array<int, RivaLangDiagnostic>
     */
    private function validateExpression(string $file, string $source, int $offset, string $expression): array
    {
        [$line, $column] = $this->lineColumn($source, $offset);
        $diagnostics = [];

        if (preg_match('/\\b(fetch|http|curl|env|process|require|import|eval|window|document)\\b/i', $expression, $blocked)) {
            $diagnostics[] = new RivaLangDiagnostic('error', $file, $line, $column, "Forbidden expression capability: {$blocked[1]}.");
        }

        $parts = array_map('trim', explode('|', $expression));
        $root = explode('.', $parts[0])[0] ?? '';
        if ($root !== '' && ! preg_match('/^["\']/', $root) && ! is_numeric($root) && ! in_array($root, self::SAFE_ROOTS, true)) {
            $diagnostics[] = new RivaLangDiagnostic('error', $file, $line, $column, "Unknown safe object: {$root}.", $this->suggest($root, self::SAFE_ROOTS));
        }

        foreach (array_slice($parts, 1) as $filter) {
            $filterName = trim(preg_replace('/:.*/', '', $filter) ?? '');
            if ($filterName !== '' && ! in_array($filterName, self::ALLOWED_FILTERS, true)) {
                $diagnostics[] = new RivaLangDiagnostic('error', $file, $line, $column, "Unknown filter: {$filterName}.", $this->suggest($filterName, self::ALLOWED_FILTERS));
            }
        }

        return $diagnostics;
    }

    /**
     * @return array<int, RivaLangDiagnostic>
     */
    private function validateStatement(string $file, string $source, int $offset, string $statement): array
    {
        [$line, $column] = $this->lineColumn($source, $offset);
        $keyword = strtolower(strtok($statement, " \t\n") ?: '');
        $allowed = ['if', 'elseif', 'else', 'endif', 'for', 'endfor', 'let', 'assign', 'capture', 'endcapture'];
        if (! in_array($keyword, $allowed, true)) {
            return [new RivaLangDiagnostic('error', $file, $line, $column, "Unknown RivaLang statement: {$keyword}.", $this->suggest($keyword, $allowed))];
        }

        if ($keyword === 'for' && preg_match('/limit\\s*:\\s*(\\d+)/', $statement, $match) && (int) $match[1] > 50) {
            return [new RivaLangDiagnostic('warning', $file, $line, $column, 'Loop limit is high. Storefront runtime caps loops at 50 items.')];
        }

        return [];
    }

    /**
     * @return array{0:int,1:int}
     */
    private function lineColumn(string $source, int $offset): array
    {
        $prefix = substr($source, 0, $offset);
        $line = substr_count($prefix, "\n") + 1;
        $lastNewline = strrpos($prefix, "\n");
        $column = $lastNewline === false ? $offset + 1 : $offset - $lastNewline;

        return [$line, $column];
    }

    private function suggest(string $needle, array $haystack): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;
        foreach ($haystack as $candidate) {
            $distance = levenshtein($needle, $candidate);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }

        return $bestDistance <= 6 && $best ? "Did you mean: {$best}?" : null;
    }
}
