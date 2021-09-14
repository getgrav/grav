<?php

// Fix too many ob_get_clean() calls when exception is thrown inside the template.

namespace Phive\Twig\Extensions\Deferred;

class DeferredExtension extends \Twig_Extension
{
    /**
     * @var array
     */
    private $blocks = array();

    /**
     * {@inheritdoc}
     */
    public function getTokenParsers()
    {
        return array(new DeferredTokenParser());
    }

    /**
     * {@inheritdoc}
     */
    public function getNodeVisitors()
    {
        return array(new DeferredNodeVisitor());
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'deferred';
    }

    public function defer(\Twig_Template $template, $blockName)
    {
        ob_start();
        $templateName = $template->getTemplateName();
        $this->blocks[$templateName][] = [ob_get_level(), $blockName];
    }

    public function resolve(\Twig_Template $template, array $context, array $blocks)
    {
        $templateName = $template->getTemplateName();
        if (empty($this->blocks[$templateName])) {
            return;
        }

        while ($block = array_pop($this->blocks[$templateName])) {
            [$level, $blockName] = $block;
            if (ob_get_level() !== $level) {
                continue;
            }

            $buffer = ob_get_clean();

            $blocks[$blockName] = array($template, 'block_'.$blockName.'_deferred');
            $template->displayBlock($blockName, $context, $blocks);

            echo $buffer;
        }

        if ($parent = $template->getParent($context)) {
            $this->resolve($parent, $context, $blocks);
        }
    }
}
