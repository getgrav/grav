<?php

/*
 * This file is part of Twig.
 *
 * (c) 2012 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
abstract class Twig_Node_Expression_Call extends Twig_Node_Expression
{
    protected function compileCallable(Twig_Compiler $compiler)
    {
        $closingParenthesis = false;
        if ($this->hasAttribute('callable') && $callable = $this->getAttribute('callable')) {
            if (is_string($callable)) {
                $compiler->raw($callable);
            } elseif (is_array($callable) && $callable[0] instanceof Twig_ExtensionInterface) {
                $compiler->raw(sprintf('$this->env->getExtension(\'%s\')->%s', $callable[0]->getName(), $callable[1]));
            } else {
                $type = ucfirst($this->getAttribute('type'));
                $compiler->raw(sprintf('call_user_func_array($this->env->get%s(\'%s\')->getCallable(), array', $type, $this->getAttribute('name')));
                $closingParenthesis = true;
            }
        } else {
            $compiler->raw($this->getAttribute('thing')->compile());
        }

        $this->compileArguments($compiler);

        if ($closingParenthesis) {
            $compiler->raw(')');
        }
    }

    protected function compileArguments(Twig_Compiler $compiler)
    {
        $compiler->raw('(');

        $first = true;

        if ($this->hasAttribute('needs_environment') && $this->getAttribute('needs_environment')) {
            $compiler->raw('$this->env');
            $first = false;
        }

        if ($this->hasAttribute('needs_context') && $this->getAttribute('needs_context')) {
            if (!$first) {
                $compiler->raw(', ');
            }
            $compiler->raw('$context');
            $first = false;
        }

        if ($this->hasAttribute('arguments')) {
            foreach ($this->getAttribute('arguments') as $argument) {
                if (!$first) {
                    $compiler->raw(', ');
                }
                $compiler->string($argument);
                $first = false;
            }
        }

        if ($this->hasNode('node')) {
            if (!$first) {
                $compiler->raw(', ');
            }
            $compiler->subcompile($this->getNode('node'));
            $first = false;
        }

        if ($this->hasNode('arguments') && null !== $this->getNode('arguments')) {
            $callable = $this->hasAttribute('callable') ? $this->getAttribute('callable') : null;

            $arguments = $this->getArguments($callable, $this->getNode('arguments'));

            foreach ($arguments as $node) {
                if (!$first) {
                    $compiler->raw(', ');
                }
                $compiler->subcompile($node);
                $first = false;
            }
        }

        $compiler->raw(')');
    }

    protected function getArguments($callable, $arguments)
    {
        $parameters = array();
        $named = false;
        foreach ($arguments as $name => $node) {
            if (!is_int($name)) {
                $named = true;
                $name = $this->normalizeName($name);
            } elseif ($named) {
                throw new Twig_Error_Syntax(sprintf('Positional arguments cannot be used after named arguments for %s "%s".', $this->getAttribute('type'), $this->getAttribute('name')));
            }

            $parameters[$name] = $node;
        }

        if (!$named) {
            return $parameters;
        }

        if (!$callable) {
            throw new LogicException(sprintf('Named arguments are not supported for %s "%s".', $this->getAttribute('type'), $this->getAttribute('name')));
        }

        // manage named arguments
        if (is_array($callable)) {
            $r = new ReflectionMethod($callable[0], $callable[1]);
        } elseif (is_object($callable) && !$callable instanceof Closure) {
            $r = new ReflectionObject($callable);
            $r = $r->getMethod('__invoke');
        } else {
            $r = new ReflectionFunction($callable);
        }

        $definition = $r->getParameters();
        if ($this->hasNode('node')) {
            array_shift($definition);
        }
        if ($this->hasAttribute('needs_environment') && $this->getAttribute('needs_environment')) {
            array_shift($definition);
        }
        if ($this->hasAttribute('needs_context') && $this->getAttribute('needs_context')) {
            array_shift($definition);
        }
        if ($this->hasAttribute('arguments') && null !== $this->getAttribute('arguments')) {
            foreach ($this->getAttribute('arguments') as $argument) {
                array_shift($definition);
            }
        }

        $arguments = array();
        $pos = 0;
        foreach ($definition as $param) {
            $name = $this->normalizeName($param->name);

            if (array_key_exists($name, $parameters)) {
                if (array_key_exists($pos, $parameters)) {
                    throw new Twig_Error_Syntax(sprintf('Argument "%s" is defined twice for %s "%s".', $name, $this->getAttribute('type'), $this->getAttribute('name')));
                }

                $arguments[] = $parameters[$name];
                unset($parameters[$name]);
            } elseif (array_key_exists($pos, $parameters)) {
                $arguments[] = $parameters[$pos];
                unset($parameters[$pos]);
                ++$pos;
            } elseif ($param->isDefaultValueAvailable()) {
                $arguments[] = new Twig_Node_Expression_Constant($param->getDefaultValue(), -1);
            } elseif ($param->isOptional()) {
                break;
            } else {
                throw new Twig_Error_Syntax(sprintf('Value for argument "%s" is required for %s "%s".', $name, $this->getAttribute('type'), $this->getAttribute('name')));
            }
        }

        if (!empty($parameters)) {
            throw new Twig_Error_Syntax(sprintf('Unknown argument%s "%s" for %s "%s".', count($parameters) > 1 ? 's' : '', implode('", "', array_keys($parameters)), $this->getAttribute('type'), $this->getAttribute('name')));
        }

        return $arguments;
    }

    protected function normalizeName($name)
    {
        return strtolower(preg_replace(array('/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'), array('\\1_\\2', '\\1_\\2'), $name));
    }
}
