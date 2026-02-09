<?php

namespace TP\Components;

use TP\Components\TableRow;
use TP\Components\TableHead;

/**
 * @template T
 */
class Table extends \Component
{
    public readonly TableHead $header;

    /** @var TableRow[] */
    public readonly array $rows;

    /**
     * Constructor for the component.
     *
     * @param string[] $columns The column headers.
     * @param T[] $items The data items.
     * @param callable(T):array<int, callable|string|Component> $projection Function to transform an item into table cell values.
     * @param callable(T):string|null $onclick Function to generate the onclick action per row.
     */
    public function __construct(
        public readonly array $columns,
        array $items,
        callable $projection,
        callable|null $onclick = null,
        array $widths = [],
    ) {
        $this->header = new TableHead($columns);
        $this->rows = array_map(fn($item) => new TableRow(
            array_map(
                /** @param int $index @param callable|string|Component $value */
                fn($index, $value) => new TableCell(
                    $this->columns[$index],
                    $value,
                    array_key_exists($index, $widths ?? []) ? $widths[$index] : null
                ),
                array_keys($this->columns),
                $projection($item)
            ),
            onclick: $onclick ? $onclick($item) : null
        ), $items);
    }

    protected function template(): void
    { ?>
        <div style="display:table;" class="table">
            <?= $this->header ?>
            <?php foreach ($this->rows as $row): ?>
                <?= $row ?>
            <?php endforeach; ?>
        </div>
<?php }
}
