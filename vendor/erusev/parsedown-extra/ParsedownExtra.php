<?php

#
#
# Parsedown Extra
# https://github.com/erusev/parsedown-extra
#
# (c) Emanuil Rusev
# http://erusev.com
#
# For the full license information, view the LICENSE file that was distributed
# with this source code.
#
#

class ParsedownExtra extends Parsedown
{
    #
    # ~

    function __construct()
    {
        $this->BlockTypes[':'] []= 'DefinitionList';

        $this->DefinitionTypes['*'] []= 'Abbreviation';

        # identify footnote definitions before reference definitions
        array_unshift($this->DefinitionTypes['['], 'Footnote');

        # identify footnote markers before before links
        array_unshift($this->SpanTypes['['], 'FootnoteMarker');
    }

    #
    # ~

    function text($text)
    {
        $markup = parent::text($text);

        # merge consecutive dl elements

        $markup = preg_replace('/<\/dl>\s+<dl>\s+/', '', $markup);

        # add footnotes

        if (isset($this->Definitions['Footnote']))
        {
            $Element = $this->buildFootnoteElement();

            $markup .= "\n" . $this->element($Element);
        }

        return $markup;
    }

    #
    # Blocks
    #

    #
    # Atx

    protected function identifyAtx($Line)
    {
        $Block = parent::identifyAtx($Line);

        if (preg_match('/[ ]*'.$this->attributesPattern.'[ ]*$/', $Block['element']['text'], $matches, PREG_OFFSET_CAPTURE))
        {
            $attributeString = $matches[1][0];

            $Block['element']['attributes'] = $this->parseAttributes($attributeString);

            $Block['element']['text'] = substr($Block['element']['text'], 0, $matches[0][1]);
        }

        return $Block;
    }

    #
    # Definition List

    protected function identifyDefinitionList($Line, $Block)
    {
        if (isset($Block['type']))
        {
            return;
        }

        $Element = array(
            'name' => 'dl',
            'handler' => 'elements',
            'text' => array(),
        );

        $terms = explode("\n", $Block['element']['text']);

        foreach ($terms as $term)
        {
            $Element['text'] []= array(
                'name' => 'dt',
                'handler' => 'line',
                'text' => $term,
            );
        }

        $Element['text'] []= array(
            'name' => 'dd',
            'handler' => 'line',
            'text' => ltrim($Line['text'], ' :'),
        );

        $Block['element'] = $Element;

        return $Block;
    }

    protected function addToDefinitionList($Line, array $Block)
    {
        if ($Line['text'][0] === ':')
        {
            $Block['element']['text'] []= array(
                'name' => 'dd',
                'handler' => 'line',
                'text' => ltrim($Line['text'], ' :'),
            );

            return $Block;
        }

        if ( ! isset($Block['interrupted']))
        {
            $Element = array_pop($Block['element']['text']);

            $Element['text'] .= "\n" . chop($Line['text']);

            $Block['element']['text'] []= $Element;

            return $Block;
        }
    }

    #
    # Setext

    protected function identifySetext($Line, array $Block = null)
    {
        $Block = parent::identifySetext($Line, $Block);

        if (preg_match('/[ ]*'.$this->attributesPattern.'[ ]*$/', $Block['element']['text'], $matches, PREG_OFFSET_CAPTURE))
        {
            $attributeString = $matches[1][0];

            $Block['element']['attributes'] = $this->parseAttributes($attributeString);

            $Block['element']['text'] = substr($Block['element']['text'], 0, $matches[0][1]);
        }

        return $Block;
    }

    #
    # Markup

    protected function completeMarkup($Block)
    {
        $DOMDocument = new DOMDocument;

        $DOMDocument->loadXML($Block['element']);

        $result = $DOMDocument->documentElement->getAttribute('markdown');

        if ($result !== '1')
        {
            return $Block;
        }

        $DOMDocument->documentElement->removeAttribute('markdown');

        $index = 0;
        $texts = array();

        foreach ($DOMDocument->documentElement->childNodes as $Node)
        {
            if ($Node instanceof DOMText)
            {
                $texts [] = $this->text($Node->nodeValue);

                # replaces the text of the node with a placeholder
                $Node->nodeValue = '\x1A'.$index ++;
            }
        }

        $markup = $DOMDocument->saveXML($DOMDocument->documentElement);

        foreach ($texts as $index => $text)
        {
            $markup = str_replace('\x1A'.$index, $text, $markup);
        }

        $Block['element'] = $markup;

        return $Block;
    }

    #
    # Definitions
    #

    #
    # Abbreviation

    protected function identifyAbbreviation($Line)
    {
        if (preg_match('/^\*\[(.+?)\]:[ ]*(.+?)[ ]*$/', $Line['text'], $matches))
        {
            $Abbreviation = array(
                'id' => $matches[1],
                'data' => $matches[2],
            );

            return $Abbreviation;
        }
    }

    #
    # Footnote

    protected function identifyFootnote($Line)
    {
        if (preg_match('/^\[\^(.+?)\]:[ ]?(.+)$/', $Line['text'], $matches))
        {
            $Footnote = array(
                'id' => $matches[1],
                'data' => array(
                    'text' => $matches[2],
                    'count' => null,
                    'number' => null,
                ),
            );

            return $Footnote;
        }
    }

    #
    # Spans
    #

    #
    # Footnote Marker

    protected function identifyFootnoteMarker($Excerpt)
    {
        if (preg_match('/^\[\^(.+?)\]/', $Excerpt['text'], $matches))
        {
            $name = $matches[1];

            if ( ! isset($this->Definitions['Footnote'][$name]))
            {
                return;
            }

            $this->Definitions['Footnote'][$name]['count'] ++;

            if ( ! isset($this->Definitions['Footnote'][$name]['number']))
            {
                $this->Definitions['Footnote'][$name]['number'] = ++ $this->footnoteCount; # » &
            }

            $Element = array(
                'name' => 'sup',
                'attributes' => array('id' => 'fnref'.$this->Definitions['Footnote'][$name]['count'].':'.$name),
                'handler' => 'element',
                'text' => array(
                    'name' => 'a',
                    'attributes' => array('href' => '#fn:'.$name, 'class' => 'footnote-ref'),
                    'text' => $this->Definitions['Footnote'][$name]['number'],
                ),
            );

            return array(
                'extent' => strlen($matches[0]),
                'element' => $Element,
            );
        }
    }

    private $footnoteCount = 0;

    #
    # Link

    protected function identifyLink($Excerpt)
    {
        $Span = parent::identifyLink($Excerpt);

        $remainder = substr($Excerpt['text'], $Span['extent']);

        if (preg_match('/^[ ]*'.$this->attributesPattern.'/', $remainder, $matches))
        {
            $Span['element']['attributes'] += $this->parseAttributes($matches[1]);

            $Span['extent'] += strlen($matches[0]);
        }

        return $Span;
    }

    #
    # ~

    protected function readPlainText($text)
    {
        $text = parent::readPlainText($text);

        if (isset($this->Definitions['Abbreviation']))
        {
            foreach ($this->Definitions['Abbreviation'] as $abbreviation => $phrase)
            {
                $text = str_replace($abbreviation, '<abbr title="'.$phrase.'">'.$abbreviation.'</abbr>', $text);
            }
        }

        return $text;
    }

    #
    # ~
    #

    protected function buildFootnoteElement()
    {
        $Element = array(
            'name' => 'div',
            'attributes' => array('class' => 'footnotes'),
            'handler' => 'elements',
            'text' => array(
                array(
                    'name' => 'hr',
                ),
                array(
                    'name' => 'ol',
                    'handler' => 'elements',
                    'text' => array(),
                ),
            ),
        );

        usort($this->Definitions['Footnote'], function($A, $B) {
            return $A['number'] - $B['number'];
        });

        foreach ($this->Definitions['Footnote'] as $name => $Data)
        {
            if ( ! isset($Data['number']))
            {
                continue;
            }

            $text = $Data['text'];

            foreach (range(1, $Data['count']) as $number)
            {
                $text .= '&#160;<a href="#fnref'.$number.':'.$name.'" rev="footnote" class="footnote-backref">&#8617;</a>';
            }

            $Element['text'][1]['text'] []= array(
                'name' => 'li',
                'attributes' => array('id' => 'fn:'.$name),
                'handler' => 'elements',
                'text' => array(
                    array(
                        'name' => 'p',
                        'text' => $text,
                    ),
                ),
            );
        }

        return $Element;
    }

    #
    # Private
    #

    private function parseAttributes($attributeString)
    {
        $Data = array();

        $attributes = preg_split('/[ ]+/', $attributeString, - 1, PREG_SPLIT_NO_EMPTY);

        foreach ($attributes as $attribute)
        {
            if ($attribute[0] === '#')
            {
                $Data['id'] = substr($attribute, 1);
            }
            else # "."
            {
                $classes []= substr($attribute, 1);
            }
        }

        if (isset($classes))
        {
            $Data['class'] = implode(' ', $classes);
        }

        return $Data;
    }

    private $attributesPattern = '{((?:[#.][-\w]+[ ]*)+)}';
}
