<?php

/*
 * This file is part of Twig.
 *
 * (c) 2010-2012 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Represents a template test.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Twig_SimpleTest
{
    protected $name;
    protected $callable;
    protected $options;

    public function __construct($name, $callable, array $options = array())
    {
        $this->name = $name;
        $this->callable = $callable;
        $this->options = array_merge(array(
            'node_class' => 'Twig_Node_Expression_Test',
        ), $options);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getCallable()
    {
        return $this->callable;
    }

    public function getNodeClass()
    {
        return $this->options['node_class'];
    }
}
