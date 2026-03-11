# nickbell.dev Quality Gates

Run these gates in order before committing.

| Gate | Command | Description |
|------|---------|-------------|
| Pint | `vendor/bin/pint --dirty` | Auto-fix code style |
| PHPStan | `vendor/bin/phpstan analyse --memory-limit=2G` | Static analysis, no errors |
| Tests | `php artisan test --compact --parallel` | Full test suite must pass |
