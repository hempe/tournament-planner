# Component System Documentation

The Golf El Faro application uses a custom component-based architecture for building UI elements. This system provides reusable, composable Components with strong typing and consistent rendering patterns.

## Core Concepts

### Component Base Class

All Components extend the abstract `Component` class located in `src/Core/Component.php`. This base class provides:

- **Output buffering**: Automatic capture of rendered content
- **Nested rendering**: Support for Components within Components
- **Type-safe rendering**: Strong typing for all component properties

```php
abstract class Component
{
    protected abstract function template(): void;
    public function __toString(): string;
    protected function captureOutput(Closure|string|Component|array $content): string;
}
```

### Creating Components

Components must implement the `template()` method where the HTML output is defined:

```php
final class MyComponent extends Component
{
    public function __construct(
        private readonly string $title,
        private readonly string $content
    ) {}

    protected function template(): void
    {
        echo <<<HTML
            <div class="my-component">
                <h2>{$this->title}</h2>
                <p>{$this->content}</p>
            </div>
        HTML;
    }
}
```

## Available Components

### Layout Components

#### Page
**File**: `src/Components/Page.php`
**Purpose**: Main page wrapper with navigation and structure

```php
new Page(
    title: string,           // Page title
    content: Component|string, // Main content
    showNav: bool = true,    // Show navigation bar
    backButton: string = '', // Back button URL
    actions: array = []      // Page-level actions
)
```

**Example**:
```php
echo new Page(
    title: __('events.title'),
    content: new Card('Event list content'),
    actions: [
        new IconActionButton('plus', __('events.new'), '/events/new')
    ]
);
```

#### Card
**File**: `src/Components/Card.php`
**Purpose**: Content container with consistent styling

```php
new Card(
    content: Component|string, // Card content
    title: string = '',       // Optional card title
    className: string = ''    // Additional CSS classes
)
```

#### Div
**File**: `src/Components/Div.php`
**Purpose**: Generic container with flexible content

```php
new Div(
    content: Component|string|array, // Content (supports arrays)
    className: string = '',          // CSS classes
    attributes: array = []           // HTML attributes
)
```

### Form Components

#### Form
**File**: `src/Components/Form.php`
**Purpose**: Form wrapper with action and method handling

```php
new Form(
    content: Component|string|array, // Form fields
    action: string = '',            // Form action URL
    method: string = 'POST',        // HTTP method
    hiddenInputs: array = [],       // Hidden form fields
    className: string = ''          // CSS classes
)
```

**Features**:
- Automatic CSRF token inclusion
- Hidden input support
- Accessible form structure

#### Input
**File**: `src/Components/Input.php`
**Purpose**: Text input field with validation support

```php
new Input(
    name: string,              // Input name attribute
    label: string = '',        // Input label
    value: string = '',        // Default value
    type: string = 'text',     // Input type
    required: bool = false,    // Required field
    placeholder: string = '',  // Placeholder text
    className: string = ''     // CSS classes
)
```

**Supported Types**:
- `text`, `email`, `password`, `number`, `date`, `tel`, `url`

#### InputAction
**File**: `src/Components/InputAction.php`
**Purpose**: Input field with associated action button

```php
new InputAction(
    name: string,              // Input name
    buttonText: string,        // Button text
    buttonIcon: string = '',   // Button icon
    placeholder: string = '',  // Input placeholder
    value: string = '',        // Default value
    action: string = ''        // Form action override
)
```

### Button Components

#### IconButton
**File**: `src/Components/IconButton.php`
**Purpose**: Button with icon and text

```php
new IconButton(
    icon: string,              // Icon name
    text: string = '',         // Button text
    type: string = 'button',   // Button type
    className: string = '',    // CSS classes
    required: bool = false     // Required attribute
)
```

#### IconActionButton
**File**: `src/Components/IconActionButton.php`
**Purpose**: Button that submits to a specific action

```php
new IconActionButton(
    icon: string,              // Icon name
    text: string,              // Button text
    action: string,            // Target action/URL
    method: string = 'POST',   // HTTP method
    className: string = '',    // CSS classes
    confirmMessage: string = '' // Confirmation dialog
)
```

### Data Display Components

#### Table
**File**: `src/Components/Table.php`
**Purpose**: Data table with headers and rows

```php
new Table(
    headers: array,            // Table headers
    rows: array,              // Table rows (TableRow objects)
    className: string = ''     // CSS classes
)
```

#### TableRow
**File**: `src/Components/TableRow.php`
**Purpose**: Table row with cells

```php
new TableRow(
    cells: array,             // Array of TableCell objects
    className: string = ''    // CSS classes
)
```

#### TableCell
**File**: `src/Components/TableCell.php`
**Purpose**: Individual table cell

```php
new TableCell(
    content: Component|string, // Cell content
    isHeader: bool = false,   // Header cell
    className: string = ''    // CSS classes
)
```

#### TableHead
**File**: `src/Components/TableHead.php`
**Purpose**: Table header cell

```php
new TableHead(
    content: string,          // Header text
    className: string = ''    // CSS classes
)
```

### Specialized Components

#### Calendar
**File**: `src/Components/Calendar.php`
**Purpose**: Monthly calendar view with events

```php
new Calendar(
    date: DateTime,           // Calendar month/year
    events: array = []        // Array of event data
)
```

**Event Data Structure**:
```php
[
    'id' => int,
    'name' => string,
    'date' => string, // Y-m-d format
    'registrations_count' => int,
    'capacity' => int,
    'user_registered' => bool
]
```

#### CalendarEvent
**File**: `src/Components/CalendarEvent.php`
**Purpose**: Individual event display in calendar

```php
new CalendarEvent(
    event: array,             // Event data
    showActions: bool = true  // Show event actions
)
```

#### EventRegistrations
**File**: `src/Components/EventRegistrations.php`
**Purpose**: Event registration management interface

```php
new EventRegistrations(
    eventId: int,             // Event ID
    registrations: array,     // Registration data
    currentUserId: int,       // Current user ID
    isAdmin: bool = false,    // Admin privileges
    isLocked: bool = false    // Event locked status
)
```

### UI Elements

#### Icon
**File**: `src/Components/Icon.php`
**Purpose**: SVG icon display

```php
new Icon(
    name: string,             // Icon name
    className: string = ''    // CSS classes
)
```

**Available Icons**:
- `home`, `calendar`, `users`, `plus`, `edit`, `delete`, `lock`, `unlock`
- `chevron-left`, `chevron-right`, `check`, `x`, `logout`

#### Link
**File**: `src/Components/Link.php`
**Purpose**: Navigation link

```php
new Link(
    href: string,             // Link URL
    text: string,             // Link text
    className: string = '',   // CSS classes
    target: string = ''       // Link target
)
```

## Security Features

### XSS Protection
All Components automatically escape output using the security helper functions:

```php
// Escape HTML content
echo e($userInput);

// Escape HTML attributes
echo attr($userAttribute);
```

### CSRF Protection
Forms automatically include CSRF tokens:

```php
// Automatic CSRF token in forms
new Form(/* ... */); // Includes hidden _token field
```

## Best Practices

### 1. Type Safety
Always use strong typing for component properties:

```php
public function __construct(
    private readonly string $title,
    private readonly int $count,
    private readonly bool $isActive
) {}
```

### 2. Immutability
Make component properties readonly to ensure immutability:

```php
private readonly string $title;
```

### 3. Composition
Prefer composition over inheritance:

```php
new Page(
    title: 'Events',
    content: new Card(
        content: new Table($headers, $rows)
    )
);
```

### 4. Security
Always escape user input in templates:

```php
protected function template(): void
{
    echo <<<HTML
        <h1>{$this->escapeHtml($this->title)}</h1>
    HTML;
}
```

### 5. Internationalization
Use translation functions for all user-facing text:

```php
new IconButton('plus', __('actions.create'))
```

## Testing Components

### Unit Testing
Components can be tested by asserting their string output:

```php
public function testCardComponent(): void
{
    $card = new Card('Test content', 'Test Title');
    $output = (string)$card;
    
    $this->assertStringContains('Test Title', $output);
    $this->assertStringContains('Test content', $output);
}
```

### Integration Testing
Test Components in context with actual data:

```php
public function testEventTable(): void
{
    $events = [/* event data */];
    $table = new Table($headers, $this->createEventRows($events));
    
    $this->assertComponentRenders($table);
}
```

## Performance Considerations

### Output Buffering
Components use output buffering for efficient rendering. Avoid echoing large amounts of data directly.

### Lazy Loading
For expensive operations, consider lazy loading:

```php
private function getExpensiveData(): array
{
    if ($this->data === null) {
        $this->data = $this->loadFromDatabase();
    }
    return $this->data;
}
```

### Caching
For frequently used Components, implement caching:

```php
private function getCachedContent(): string
{
    $key = "component_{$this->id}";
    return Cache::remember($key, 3600, fn() => $this->renderContent());
}
```