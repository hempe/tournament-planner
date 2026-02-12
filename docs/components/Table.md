# Table Component

The `Table` component renders a data table with customizable columns, rows, and cell widths.

## Constructor Signature

```php
public function __construct(
    array $columns,           // Column headers
    array $items,             // Data items
    callable $projection,     // fn($item) => array - Transform item to cells
    callable|null $onclick = null,  // fn($item) => string - Row click handler
    array $widths = [],       // Column widths (null = auto, int = px)
)
```

## Parameters

### `$columns` (required)
Array of column header strings.

```php
columns: ['Name', 'Email', 'Actions']
```

### `$items` (required)
Array of data items to display. Each item will be passed to the projection function.

**Important:** Use distinct values if you need to differentiate rows (not `null`).

```php
// ❌ Bad - all items are null
items: [null, null, null]

// ✅ Good - distinct values
items: [0, 1, 2]
// or
items: $users  // Array of user objects
```

### `$projection` (required)
Function that transforms each item into an array of cell values.

**Signature:** `fn($item) => array<int, callable|string|Component>`

**Important:** Receives ONLY the item, not an index!

```php
projection: fn($user) => [
    $user->name,
    $user->email,
    new IconButton(...)
]
```

For form-like tables with different rows:

```php
items: [0, 1, 2],
projection: fn($rowIndex) => match($rowIndex) {
    0 => ['Label 1', '<input name="field1">'],
    1 => ['Label 2', '<input name="field2">'],
    2 => ['Label 3', '<input name="field3">'],
}
```

### `$onclick` (optional)
Function that generates JavaScript for row click events.

**Signature:** `fn($item) => string`

```php
onclick: fn($event) => "window.location.href='/events/{$event->id}'"
```

### `$widths` (optional)
Array of column widths. Index corresponds to column index.

- `null` or omitted: Auto width
- `1`: Minimal width (1px)
- Integer > 1: Fixed pixel width

```php
widths: [null, null, 1, 1]  // First 2 auto, last 2 minimal
```

## Examples

### Simple Data Table

```php
use TP\Components\Table;
use TP\Models\DB;

new Table(
    columns: ['Name', 'Email', 'Admin'],
    items: DB::$users->all(),
    projection: fn($user) => [
        $user->name,
        $user->email,
        $user->isAdmin ? 'Yes' : 'No'
    ]
)
```

### Table with Actions

```php
new Table(
    columns: ['Event', 'Date', ''],
    items: $events,
    projection: fn($event) => [
        $event->name,
        $event->date,
        new IconActionButton(
            actionUrl: "/events/{$event->id}/delete",
            title: 'Delete',
            icon: 'fa-trash',
            color: Color::Accent
        )
    ],
    onclick: fn($event) => "window.location.href='/events/{$event->id}'",
    widths: [null, null, 1]  // Action column minimal width
)
```

### Form-Style Table

```php
new Table(
    columns: ['', '', ''],
    items: [0, 1, 2],  // Row indices
    projection: fn($i) => match($i) {
        0 => [
            '<label>Username</label>',
            '<input type="text" name="username" required>',
            ''
        ],
        1 => [
            '<label>Password</label>',
            '<input type="password" name="password" required>',
            ''
        ],
        2 => [
            '',
            '',
            new IconButton(
                title: 'Submit',
                type: 'submit',
                icon: 'fa-save',
                color: Color::Primary
            )
        ],
    }
)
```

### Conditional Columns (Admin-Only Actions)

```php
$isAdmin = User::admin();

$columns = ['Name', 'Email'];
$widths = [null, null];

if ($isAdmin) {
    $columns[] = '';
    $widths[] = 1;
}

new Table(
    columns: $columns,
    items: $users,
    projection: function($user) use ($isAdmin) {
        $row = [$user->name, $user->email];

        if ($isAdmin) {
            $row[] = new IconActionButton(
                actionUrl: "/users/{$user->id}/delete",
                title: 'Delete',
                icon: 'fa-trash'
            );
        }

        return $row;
    },
    widths: $widths
)
```

## Common Mistakes

### ❌ Two-Parameter Projection

```php
projection: fn($item, $index) => [...]  // ❌ Only receives $item!
```

**Fix:**
```php
items: [0, 1, 2],
projection: fn($index) => [...]  // ✅ Use item as index
```

### ❌ Using Null Items with Match

```php
items: [null, null, null],
projection: fn($item) => match($item) {  // ❌ All null, can't match
    0 => [...],
    1 => [...],
}
```

**Fix:**
```php
items: [0, 1, 2],
projection: fn($item) => match($item) {  // ✅ Distinct values
    0 => [...],
    1 => [...],
}
```

### ❌ Invalid Footer Parameter

```php
new Table(
    columns: ['Col'],
    items: [1],
    projection: fn($i) => [$i],
    footer: '<div>Footer</div>'  // ❌ No footer parameter!
)
```

**Fix:** Add footer as last row item:
```php
items: [1, 2],
projection: fn($i) => match($i) {
    1 => ['Regular row'],
    2 => ['<div>Footer content</div>'],
}
```

## See Also

- [TableRow](TableRow.md)
- [TableCell](TableCell.md)
- [TableHead](TableHead.md)
- [IconActionButton](IconActionButton.md)
