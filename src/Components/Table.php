<?php

namespace TP\Components;

use TP\Components\TableRow;
use TP\Components\TableHead;
use TP\Core\Url;

/**
 * @template T
 */
final class Table extends Component
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
     * @param callable(T):null $href Function to generate the onclick action per row.
     */
    public function __construct(
        public readonly array $columns,
        array $items,
        callable $projection,
        callable|null $href = null,
        array $widths = [],
    ) {
        $this->header = new TableHead($columns);
        $this->rows = array_map(fn($item): TableRow => new TableRow(
            columns: array_map(
                /** @param int $index @param callable|string|Component $value */
                callback: fn($index, $value) => new TableCell(
                    title: $this->columns[$index],
                    content: $value,
                    width: array_key_exists($index, $widths ?? []) ? $widths[$index] : null
                ),
                array: array_keys($this->columns),
                arrays: $projection($item)
            ),
            onclick: $href ? $this->onclick($href, $item) : null
        ), $items);
    }

    private function onclick(callable $href, TableRow $item): string
    {
        $url = Url::build($href($item));
        return "window.location.href='{$url}'";
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
