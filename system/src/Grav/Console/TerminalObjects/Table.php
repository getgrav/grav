<?php

/**
 * @package    Grav\Console\TerminalObjects
 *
 * @copyright  Copyright (c) 2015 - 2022 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\TerminalObjects;

/**
 * Class Table
 * @package Grav\Console\TerminalObjects
 * @deprecated 1.7 Use Symfony Console Table
 */
class Table extends \League\CLImate\TerminalObject\Basic\Table
{
    /**
     * @return array
     */
    public function result()
    {
        $this->column_widths = $this->getColumnWidths();
        $this->table_width   = $this->getWidth();
        $this->border        = $this->getBorder();

        $this->buildHeaderRow();

        foreach ($this->data as $key => $columns) {
            $this->rows[] = $this->buildRow($columns);
        }

        $this->rows[] = $this->border;

        return $this->rows;
    }
}
