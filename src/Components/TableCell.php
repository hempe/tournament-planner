<?php

namespace TP\Components;

class TableCell extends Component
{
    /** @var string The rendered content of the card */
    public readonly string $content;

    /**
     * @param callable|string|Component $content The card's content,
     */
    public function __construct(
        public readonly string $title,
        callable|string|Component $content,
        public readonly int|float|string|null $width = null,
    ) {
        $this->content = $this->captureOutput($content);
    }

    protected function template(): void
    { ?>
        <div style="display: table-cell; <?= $this->width ? 'width:' . $this->width . 'px' : '' ?>" class="table-cell">
            <?php if ($this->title): ?>
                <div class='table-cell-title'><?= $this->title ?></div>
            <?php endif; ?>
            <?= $this->content ?>
        </div>
    <?php }
}
