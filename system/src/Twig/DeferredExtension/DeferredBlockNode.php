<?php

/**
 * This file is part of the rybakit/twig-deferred-extension package.
 *
 * (c) Eugene Leonovich <gen.work@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Twig\DeferredExtension;

use Twig\Compiler;
use Twig\Node\BlockNode;

final class DeferredBlockNode extends BlockNode
{
    public function compile(Compiler $compiler) : void
    {
        $name = $this->getAttribute('name');

        $compiler
            ->addDebugInfo($this)
            ->write("/**\n")
            ->write(" * @return iterable<null|scalar|\\Stringable>\n")
            ->write(" */\n")
            ->write("public function block_$name(array \$context, array \$blocks = []): iterable\n", "{\n")
            ->indent()
            ->write("\$macros = \$this->macros;\n")
            ->write("\$this->deferred->defer(\$this, '$name');\n")
            ->write("yield from [];\n")
            ->outdent()
            ->write("}\n\n")
        ;

        $compiler
            ->addDebugInfo($this)
            ->write("/**\n")
            ->write(" * @return iterable<null|scalar|\\Stringable>\n")
            ->write(" */\n")
            ->write("public function block_{$name}_deferred(array \$context, array \$blocks = []): iterable\n", "{\n")
            ->indent()
            ->write("\$macros = \$this->macros;\n")
            ->subcompile($this->getNode('body'))
            ->write("\$this->deferred->resolve(\$this, \$context, \$blocks);\n")
            ->write("yield from [];\n")
            ->outdent()
            ->write("}\n\n")
        ;
    }
}
