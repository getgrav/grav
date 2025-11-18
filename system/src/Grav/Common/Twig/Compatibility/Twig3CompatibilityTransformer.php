<?php

declare(strict_types=1);

namespace Grav\Common\Twig\Compatibility;

/**
 * Applies automatic rewrites that help legacy Twig 1/2 templates compile under Twig 3.
 */
class Twig3CompatibilityTransformer
{
    /**
     * Transform raw Twig source code.
     */
    public function transform(string $code): string
    {
        $code = $this->rewriteForLoopGuards($code);
        $code = $this->rewriteSpacelessBlocks($code);
        $code = $this->rewriteFilterBlocks($code);
        $code = $this->rewriteSameAsTests($code);
        $code = $this->rewriteDivisibleByTests($code);
        $code = $this->rewriteNoneTests($code);
        $code = $this->rewriteReplaceFilterSignatures($code);
        $code = $this->rewriteRawBlocks($code);

        return $code;
    }

    /**
     * Convert legacy "{% for ... if ... %}" guard syntax to a Twig 3 friendly form that
     * filters the sequence before iteration.
     */
    private function rewriteForLoopGuards(string $code): string
    {
        $pattern = '/(\{%-?\s*for\s+)(.+?)(\s*-?%\})/s';

        return (string) preg_replace_callback($pattern, function (array $matches) {
            $clause = $matches[2];

            // Find the last " if " (including leading whitespace) to reduce false positives
            if (!preg_match_all('/\sif\s+/i', $clause, $ifs, PREG_OFFSET_CAPTURE)) {
                return $matches[0];
            }

            $lastIf = end($ifs[0]);
            if ($lastIf === false) {
                return $matches[0];
            }

            $ifPos = (int) $lastIf[1];
            $ifLength = strlen($lastIf[0]);

            $head = trim(substr($clause, 0, $ifPos));
            $condition = trim(substr($clause, $ifPos + $ifLength));

            if ($head === '' || $condition === '') {
                return $matches[0];
            }

            if (!preg_match('/^(.*)\s+in\s+(.*)$/is', $head, $parts)) {
                return $matches[0];
            }

            $targetSpec = trim($parts[1]);
            $sequence = trim($parts[2]);

            if ($targetSpec === '' || $sequence === '') {
                return $matches[0];
            }

            $targets = array_map(static fn (string $value): string => trim($value), explode(',', $targetSpec));

            if (count($targets) === 1) {
                $arrow = sprintf('%s => %s', $targets[0], $condition);
            } elseif (count($targets) === 2) {
                [$keyVar, $valueVar] = $targets;
                if ($valueVar === '') {
                    return $matches[0];
                }
                $arrow = sprintf('(%s, %s) => %s', $valueVar, $keyVar, $condition);
            } else {
                // Unsupported target list: fall back to the original clause.
                return $matches[0];
            }

            $sequence = $this->ensureWrapped($sequence);

            $rewrittenClause = sprintf('%s in %s|filter(%s)', $targetSpec, $sequence, $arrow);

            return $matches[1] . $rewrittenClause . $matches[3];
        }, $code);
    }

    private function rewriteSpacelessBlocks(string $code): string
    {
        $openPattern = '/\{%(\-?)\s*spaceless\s*(\-?)%\}/i';
        $code = (string) preg_replace_callback($openPattern, static function (array $matches): string {
            $leading = $matches[1] === '-' ? '-' : '';
            $trailing = $matches[2] === '-' ? '-' : '';

            return '{%' . $leading . ' apply spaceless ' . $trailing . '%}';
        }, $code);

        $closePattern = '/\{%(\-?)\s*endspaceless\s*(\-?)%\}/i';

        return (string) preg_replace_callback($closePattern, static function (array $matches): string {
            $leading = $matches[1] === '-' ? '-' : '';
            $trailing = $matches[2] === '-' ? '-' : '';

            return '{%' . $leading . ' endapply ' . $trailing . '%}';
        }, $code);
    }

    private function rewriteFilterBlocks(string $code): string
    {
        $openPattern = '/\{%(\-?)\s*filter\s+(.+?)\s*(\-?)%\}/i';
        $code = (string) preg_replace_callback($openPattern, static function (array $matches): string {
            $leading = $matches[1] === '-' ? '-' : '';
            $expression = trim($matches[2]);
            $trailing = $matches[3] === '-' ? '-' : '';

            if ($expression === '') {
                return $matches[0];
            }

            return '{%' . $leading . ' apply ' . $expression . ' ' . $trailing . '%}';
        }, $code);

        $closePattern = '/\{%(\-?)\s*endfilter\s*(\-?)%\}/i';

        return (string) preg_replace_callback($closePattern, static function (array $matches): string {
            $leading = $matches[1] === '-' ? '-' : '';
            $trailing = $matches[2] === '-' ? '-' : '';

            return '{%' . $leading . ' endapply ' . $trailing . '%}';
        }, $code);
    }

    private function rewriteSameAsTests(string $code): string
    {
        $pattern = '/([\'"])(?:\\\\.|(?!\\1).)*\\1|\\bis\\s+(?:not\\s+)?sameas\\b/is';

        return (string) preg_replace_callback($pattern, static function ($matches) {
            // If group 1 is not set, it means 'is sameas' was matched.
            if (!isset($matches[1])) {
                return str_ireplace('sameas', 'same as', $matches[0]);
            }

            // Otherwise, it's a quoted string, so return it as is.
            return $matches[0];
        }, $code);
    }

    private function rewriteReplaceFilterSignatures(string $code): string
    {
        $pattern = '/\|replace\(\s*(["\'])(.*?)\1\s*,\s*(["\'])(.*?)\3\s*\)/';
        $code = (string) preg_replace_callback($pattern, static function (array $matches): string {
            $keyQuote = $matches[1];
            $key = $matches[2];
            $valueQuote = $matches[3];
            $value = $matches[4];

            return sprintf('|replace({%1$s%2$s%1$s: %3$s%4$s%3$s})', $keyQuote, $key, $valueQuote, $value);
        }, $code);

        return $code;
    }

    private function rewriteRawBlocks(string $code): string
    {
        $openPattern = '/\{%(\-?)\s*raw\s*(\-?)%\}/i';
        $code = (string) preg_replace_callback($openPattern, static function (array $matches): string {
            $leading = $matches[1] === '-' ? '-' : '';
            $trailing = $matches[2] === '-' ? '-' : '';

            return '{%' . $leading . ' verbatim ' . $trailing . '%}';
        }, $code);

        $closePattern = '/\{%(\-?)\s*endraw\s*(\-?)%\}/i';

        return (string) preg_replace_callback($closePattern, static function (array $matches): string {
            $leading = $matches[1] === '-' ? '-' : '';
            $trailing = $matches[2] === '-' ? '-' : '';

            return '{%' . $leading . ' endverbatim ' . $trailing . '%}';
        }, $code);
    }

    private function rewriteDivisibleByTests(string $code): string
    {
        $pattern = '/([\'"])(?:\\\\.|(?!\\1).)*\\1|\\bis\\s+(?:not\\s+)?divisibleby\\b/is';

        return (string) preg_replace_callback($pattern, static function ($matches) {
            // If group 1 is not set, it means 'is divisibleby' was matched.
            if (!isset($matches[1])) {
                return str_ireplace('divisibleby', 'divisible by', $matches[0]);
            }

            // Otherwise, it's a quoted string, so return it as is.
            return $matches[0];
        }, $code);
    }

    private function rewriteNoneTests(string $code): string
    {
        $pattern = '/([\'"])(?:\\\\.|(?!\\1).)*\\1|\\bis\\s+(?:not\\s+)?none\\b/is';

        return (string) preg_replace_callback($pattern, static function ($matches) {
            // If group 1 is not set, it means 'is none' was matched.
            if (!isset($matches[1])) {
                return str_ireplace('none', 'null', $matches[0]);
            }

            // Otherwise, it's a quoted string, so return it as is.
            return $matches[0];
        }, $code);
    }

    private function ensureWrapped(string $expression): string
    {
        $trimmed = trim($expression);

        if ($trimmed === '') {
            return $expression;
        }

        $startsWithParen = str_starts_with($trimmed, '(') && str_ends_with($trimmed, ')');

        return $startsWithParen ? $expression : '(' . $expression . ')';
    }
}
