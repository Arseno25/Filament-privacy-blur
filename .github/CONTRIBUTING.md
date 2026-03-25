# Contributing to Filament Privacy Blur

Contributions are welcome! Please follow these guidelines when contributing to this project.

## Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates. When creating a bug report, include:

- A clear title and description
- Steps to reproduce the issue
- Expected vs. actual behavior
- Package version, PHP version, Laravel version, and Filament version
- Any relevant code snippets or configuration

## Suggesting Features

Feature suggestions are welcome. Please:

- Check existing issues and pull requests first
- Provide a clear use case for the feature
- Consider whether the feature is broadly useful to other users

## Pull Requests

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes
4. Run tests (`composer test`) and linting (`composer test:lint`)
5. Commit your changes with clear messages
6. Push to your fork (`git push origin feature/amazing-feature`)
7. Open a Pull Request

## Code Style

This project follows the PSR-12 coding standard. Laravel Pint is used for code formatting.

## Testing

Tests are written using Pest. Run tests with:

```bash
composer test
```

For static analysis:

```bash
composer analyse
```

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
