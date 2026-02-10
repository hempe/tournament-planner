<?php

namespace TP\Components;

use TP\Components\TableCell;

class TableRow extends Component
{
    /**
     * Constructor for the component.
     *
     * @param TableCell[] $columns The title of the columns
     */
    public function __construct(
        public readonly array $columns,
        public readonly string|null $onclick = null,
    ) {
    }

    protected function template(): void
    { ?>
        <div style="display: table-row; <?= $this->onclick ? 'cursor: pointer' : '' ?>" class="table-row" <?= $this->onclick ? "onclick=\"$this->onclick\"" : '' ?>>
            <?php foreach ($this->columns as $col): ?>
                <?= $col ?>
            <?php endforeach; ?>
        </div>
    <?php }
}
