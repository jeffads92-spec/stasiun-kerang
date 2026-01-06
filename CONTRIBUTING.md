# Contributing to Stasiun Kerang

First off, thank you for considering contributing to Stasiun Kerang! It's people like you that make this restaurant management system better for everyone.

## Code of Conduct

This project and everyone participating in it is governed by respect and professionalism. By participating, you are expected to uphold this code.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates. When you create a bug report, include as many details as possible:

**Bug Report Template:**
```markdown
**Describe the bug**
A clear description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior:
1. Go to '...'
2. Click on '....'
3. See error

**Expected behavior**
What you expected to happen.

**Screenshots**
If applicable, add screenshots.

**Environment:**
 - OS: [e.g. Windows 10]
 - Browser: [e.g. Chrome 96]
 - PHP Version: [e.g. 7.4]
 - Database: [e.g. MySQL 8.0]

**Additional context**
Any other context about the problem.
```

### Suggesting Enhancements

Enhancement suggestions are tracked as GitHub issues. When creating an enhancement suggestion:

- Use a clear and descriptive title
- Provide a detailed description of the suggested enhancement
- Explain why this enhancement would be useful
- List any alternatives you've considered

### Pull Requests

1. **Fork the Repository**
```bash
git clone https://github.com/jeffads92-spec/stasiun-kerang.git
cd stasiun-kerang
```

2. **Create a Branch**
```bash
git checkout -b feature/AmazingFeature
```

3. **Make Your Changes**
- Follow the coding standards (see below)
- Add comments to your code where necessary
- Update documentation if needed

4. **Test Your Changes**
```bash
# Run PHP CodeSniffer
composer phpcs

# Run tests
composer test
```

5. **Commit Your Changes**
```bash
git add .
git commit -m "Add some AmazingFeature"
```

Use conventional commit messages:
- `feat:` for new features
- `fix:` for bug fixes
- `docs:` for documentation changes
- `style:` for formatting changes
- `refactor:` for code refactoring
- `test:` for adding tests
- `chore:` for maintenance tasks

6. **Push to Your Fork**
```bash
git push origin feature/AmazingFeature
```

7. **Open a Pull Request**
- Provide a clear description of the changes
- Reference any related issues
- Include screenshots if applicable

## Development Setup

### Prerequisites
- PHP >= 7.4
- MySQL >= 8.0
- Composer
- Git

### Local Development

1. **Clone and Install**
```bash
git clone https://github.com/jeffads92-spec/stasiun-kerang.git
cd stasiun-kerang
composer install
```

2. **Configure Database**
```bash
cp .env.example .env
# Edit .env with your database credentials
```

3. **Import Database**
```bash
mysql -u username -p database_name < config/schema.sql
```

4. **Run Development Server**
```bash
php -S localhost:8000
```

## Coding Standards

### PHP

Follow PSR-12 coding standards:

```php
<?php
// Use strict types
declare(strict_types=1);

// Proper spacing
function myFunction(string $param): array
{
    // 4 spaces indentation
    if ($param === 'value') {
        return ['result' => 'success'];
    }
    
    return [];
}
```

### Database

- Use prepared statements to prevent SQL injection
- Always use transactions for multiple related queries
- Index frequently queried columns
- Use meaningful table and column names

```php
// Good
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);

// Bad - SQL injection risk
$sql = "SELECT * FROM orders WHERE id = $id";
```

### JavaScript

- Use ES6+ syntax
- Use meaningful variable names
- Add comments for complex logic
- Handle errors properly

```javascript
// Good
async function fetchOrders() {
    try {
        const response = await fetch('/api/orders.php');
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching orders:', error);
        throw error;
    }
}

// Bad
function getO() {
    fetch('/api/orders.php')
        .then(r => r.json())
        .then(d => console.log(d));
}
```

### HTML/CSS

- Use semantic HTML5 elements
- Keep CSS organized and commented
- Use consistent naming conventions
- Ensure responsive design

## API Development

When adding new API endpoints:

1. **Follow RESTful conventions**
```php
GET    /api/resource.php      // List all
GET    /api/resource.php?id=1 // Get one
POST   /api/resource.php      // Create
PUT    /api/resource.php?id=1 // Update
DELETE /api/resource.php?id=1 // Delete
```

2. **Use consistent response format**
```php
function sendResponse($code, $success, $message, $data = null) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}
```

3. **Validate input**
```php
$required = ['field1', 'field2'];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        sendResponse(400, false, "Field {$field} is required");
        return;
    }
}
```

4. **Update API documentation**
Add your endpoint to `API_DOCUMENTATION.md`

## Testing

### Manual Testing
- Test all CRUD operations
- Test with different user roles
- Test error scenarios
- Test on different browsers

### Automated Testing
```bash
# Run PHPUnit tests
composer test

# Run with coverage
composer test -- --coverage-html coverage/
```

## Documentation

- Update README.md if adding features
- Update API_DOCUMENTATION.md for API changes
- Add inline code comments for complex logic
- Update configuration examples if needed

## Version Control

### Branch Naming
- `feature/feature-name` - New features
- `fix/bug-description` - Bug fixes
- `docs/what-changed` - Documentation updates
- `refactor/what-refactored` - Code refactoring

### Commit Messages
```
feat: add kitchen display auto-refresh

- Implement 5-second auto-refresh for orders
- Add toggle to enable/disable auto-refresh
- Update kitchen.html with new feature

Fixes #123
```

## Questions?

Feel free to:
- Open an issue with the `question` label
- Reach out to the maintainers
- Check existing documentation

## Recognition

Contributors will be recognized in:
- README.md contributors section
- Release notes
- Project documentation

Thank you for contributing to Stasiun Kerang! 🦪
