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
use Twig\Node\Node;

final class DeferredNode extends Node
{
    public function compile(Compiler $compiler) : void
    {
        $compiler
            ->write("\$this->deferred->resolve(\$this, \$context, \$blocks);\n")
        ;
    }
}
