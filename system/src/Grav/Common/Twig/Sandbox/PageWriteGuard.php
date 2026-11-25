<?php

/**
 * @package    Grav\Common\Twig\Sandbox
 *
 * @copyright  Copyright (c) 2015 - 2026 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Common\Twig\Sandbox;

use Grav\Common\Page\Interfaces\PageInterface;
use Grav\Common\Twig\Node\PageWriteCaptureNode;
use Grav\Common\Twig\Node\PageWriteCheckNode;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\SandboxExtension;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\GetAttrExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Source;
use Twig\Template;

/**
 * Lets sandboxed content change only the page being rendered.
 *
 * Most page accessors are setters too: `page.title()` reads, `page.title('x')`
 * writes. The sandbox policy is asked about the name of a call, never its
 * arguments, so on its own it lets content write to any page it can reach
 * (`page.find('/x').header({...})`, an item of `page.collection()`). Those
 * writes change that page for the rest of the request, and through it its
 * permission checks and cached content.
 *
 * Every method call with arguments that compiles with sandbox checks has its
 * arguments passed through checkArguments() first, which refuses a setter
 * (SETTER_METHODS) on any page other than the one Twig::processPage() is
 * rendering. Trusted templates compile without sandbox checks, and so without
 * this call. The page is compared by identity: processPage() hands content
 * the same object it renders as `page`, while a page reached another way, or
 * a translation of it (which shares its folder), is another page.
 *
 * Registered next to the SandboxExtension. Being an extension of its own also
 * changes the compiled class names, so content compiled before it existed is
 * compiled again rather than reused from the cache.
 */
final class PageWriteGuard extends AbstractExtension implements NodeVisitorInterface
{
    /**
     * Page methods that change the page they are called on when given an
     * argument. Lowercase, as Twig resolves method names.
     */
    public const SETTER_METHODS = [
        'addcontentmeta', 'addforms', 'cachecontrol', 'content', 'copy', 'date', 'dateformat', 'etag',
        'expires', 'extension', 'filepath', 'folder', 'frontmatter', 'header', 'id', 'inherited', 'init',
        'language', 'lastmodified', 'maxcount', 'media', 'menu', 'metadata', 'modified', 'modifyheader',
        'modular', 'modulartwig', 'move', 'name', 'order', 'orderby', 'orderdir', 'ordermanual', 'parent',
        'path', 'process', 'publishdate', 'published', 'raw', 'rawmarkdown', 'rawroute', 'redirect',
        'routable', 'route', 'routealiases', 'routecanonical', 'save', 'setcontentmeta', 'setrawcontent',
        'setsummary', 'slug', 'ssl', 'taxonomy', 'template', 'templateformat', 'title', 'unpublishdate',
        'visible',
    ];

    /**
     * Page methods whose arguments only choose what to read. Not consulted at
     * runtime: it is here so that every page method taking arguments is
     * classified one way or the other (see PageWriteGuardTest).
     */
    public const READ_METHODS = [
        'adjacentsibling', 'ancestor', 'canonical', 'collection', 'evaluate', 'find', 'getblueprint',
        'getcontentmeta', 'inheritedfield', 'ispage', 'link', 'shouldprocess', 'summary',
        'translatedlanguages', 'untranslatedlanguages', 'url', 'value',
    ];

    /** @var list<PageInterface> */
    private static array $rendering = [];

    /** @var int */
    private int $count = 0;

    /**
     * Runs $render with $page as the page being rendered.
     *
     * @template T
     * @param callable(): T $render
     * @return T
     */
    public static function rendering(PageInterface $page, callable $render): mixed
    {
        self::$rendering[] = $page;
        try {
            return $render();
        } finally {
            array_pop(self::$rendering);
        }
    }

    /**
     * Compiled in front of the arguments of a method call in sandboxed Twig.
     * Returns the arguments unchanged, or refuses a setter call on a page
     * other than the one being rendered.
     *
     * @internal
     */
    public static function checkArguments(Environment $env, Source $source, mixed $object, mixed $method, mixed $arguments, int $lineno): mixed
    {
        if ([] === $arguments || !$object instanceof PageInterface) {
            return $arguments;
        }

        $key = array_key_last(self::$rendering);
        if (null !== $key && $object === self::$rendering[$key]) {
            return $arguments;
        }

        // A name that is not a string is refused rather than turned into one here,
        // before the sandbox has checked its __toString().
        $method = \is_string($method) ? strtolower($method) : null;
        if (null !== $method && !\in_array($method, self::SETTER_METHODS, true)) {
            return $arguments;
        }

        if (!$env->hasExtension(SandboxExtension::class) || !$env->getExtension(SandboxExtension::class)->isSandboxed($source)) {
            return $arguments;
        }

        $method ??= '(dynamic)';
        $error = new SecurityNotAllowedMethodError(
            sprintf('Calling "%s" method with arguments on a page other than the one being rendered is not allowed.', $method),
            $object::class,
            $method
        );
        $error->setSourceContext($source);
        $error->setTemplateLine($lineno);

        throw $error;
    }

    public function getNodeVisitors(): array
    {
        return [$this];
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        // The SandboxNodeVisitor marks the attribute access of a trusted template,
        // which then compiles without sandbox checks.
        if (!$node instanceof GetAttrExpression
            || $node->hasAttribute('sandbox_trusted')
            || Template::ARRAY_CALL === $node->getAttribute('type')
            || !$node->hasNode('arguments')
            || 0 === \count($arguments = $node->getNode('arguments'))
        ) {
            return $node;
        }

        // The object and a dynamic method name are evaluated once, into locals the
        // check reads: PHP evaluates the object, the name and then the arguments.
        $object = '__grav_page_write_' . ++$this->count;
        $node->setNode('node', new PageWriteCaptureNode($node->getNode('node'), $object));

        $attribute = $node->getNode('attribute');
        if ($attribute instanceof ConstantExpression) {
            $check = new PageWriteCheckNode($arguments, $object, null, $attribute->getAttribute('value'), $node->getTemplateLine());
        } else {
            $method = '__grav_page_write_' . ++$this->count;
            $node->setNode('attribute', new PageWriteCaptureNode($attribute, $method));
            $check = new PageWriteCheckNode($arguments, $object, $method, null, $node->getTemplateLine());
        }

        $node->setNode('arguments', $check);

        return $node;
    }

    public function getPriority(): int
    {
        // After the sandbox's visitor (0), which marks trusted templates.
        return 10;
    }
}
