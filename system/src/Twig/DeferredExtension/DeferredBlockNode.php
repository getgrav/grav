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
            ->write("public function block_$name(\$context, array \$blocks = [])\n", "{\n")
            ->indent()
            ->write("\$this->deferred->defer(\$this, '$name');\n")
            ->outdent()
            ->write("}\n\n")
        ;

        $compiler
            ->addDebugInfo($this)
            ->write("public function block_{$name}_deferred(\$context, array \$blocks = [])\n", "{\n")
            ->indent()
            ->subcompile($this->getNode('body'))
            ->write("\$this->deferred->resolve(\$this, \$context, \$blocks);\n")
            ->outdent()
            ->write("}\n\n")
        ;
    }
}
