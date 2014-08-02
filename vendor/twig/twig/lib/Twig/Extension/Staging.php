<?php

/*
 * This file is part of Twig.
 *
 * (c) 2012 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Internal class.
 *
 * This class is used by Twig_Environment as a staging area and must not be used directly.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Twig_Extension_Staging extends Twig_Extension
{
    protected $functions = array();
    protected $filters = array();
    protected $visitors = array();
    protected $tokenParsers = array();
    protected $globals = array();
    protected $tests = array();

    public function addFunction($name, $function)
    {
        $this->functions[$name] = $function;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return $this->functions;
    }

    public function addFilter($name, $filter)
    {
        $this->filters[$name] = $filter;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return $this->filters;
    }

    public function addNodeVisitor(Twig_NodeVisitorInterface $visitor)
    {
        $this->visitors[] = $visitor;
    }

    /**
     * {@inheritdoc}
     */
    public function getNodeVisitors()
    {
        return $this->visitors;
    }

    public function addTokenParser(Twig_TokenParserInterface $parser)
    {
        $this->tokenParsers[] = $parser;
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenParsers()
    {
        return $this->tokenParsers;
    }

    public function addGlobal($name, $value)
    {
        $this->globals[$name] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getGlobals()
    {
        return $this->globals;
    }

    public function addTest($name, $test)
    {
        $this->tests[$name] = $test;
    }

    /**
     * {@inheritdoc}
     */
    public function getTests()
    {
        return $this->tests;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'staging';
    }
}
