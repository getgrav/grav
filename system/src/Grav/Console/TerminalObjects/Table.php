<?php
/**
 * @package    Grav.Console
 *
 * @copyright  Copyright (C) 2015 - 2018 Trilby Media, LLC. All rights reserved.
 * @license    MIT License; see LICENSE file for details.
 */

namespace Grav\Console\TerminalObjects;

class Table extends \League\CLImate\TerminalObject\Basic\Table
{
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
