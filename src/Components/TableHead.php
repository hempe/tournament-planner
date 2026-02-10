<?php

namespace TP\Components;

class TableHead extends Component
{
    /**
     * Constructor for the component.
     *
     * @param string $title The title of the table head.
     * @param string[] $columns The title of the columns
     */
    public function __construct(
        public readonly array $columns
    ) {
    }

    protected function template(): void
    { ?>
        <div style="display: table-row;" class="table-head">
            <?php foreach ($this->columns as $col): ?>
                <div style="display: table-cell; vertical-align: middle;" class="table-header-cell">
                    <div><?= $col ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php }
}
