# Laravel API Forge

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-blue.svg)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/laravel-%3E%3D11.0-red.svg)](https://laravel.com/)
[![Laravel Installer](https://img.shields.io/badge/laravel--installer-%3E%3D5.0-orange.svg)](https://laravel.com/docs/installation)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

*[正體中文](README.ZH-TW.md) | English*

Laravel API Forge is a powerful command-line tool that helps you quickly scaffold a complete Laravel API project with modern development practices and essential packages pre-configured. It's built on top of Laravel Zero and automates the setup of a production-ready Laravel API with optional features like Redis caching, RBAC (Role-Based Access Control), and modular architecture.

## ✨ Features

- **🚀 Quick Setup**: Create a complete Laravel API project in minutes
- **🔧 Environment Validation**: Automatically checks for required tools and versions
- **📝 Code Quality**: Automatic code formatting with Laravel Pint
- **🔄 Git Integration**: Step-by-step Git commits with meaningful messages
- **🐳 Docker Ready**: Laravel Sail configuration with MySQL and Redis
- **🔐 Authentication**: Laravel Sanctum API authentication setup
- **📊 Database**: MySQL database configuration with Laravel Sail
- **⚡ Redis Cache**: Optional Redis caching configuration
- **🛡️ RBAC System**: Optional role-based access control with Spatie Permission
- **🧩 Modular Architecture**: Optional Laravel Modules for modular development
- **📚 API Documentation**: Spectator for OpenAPI specification testing
- **🎯 Data Transfer Objects**: Laravel Data for structured data handling
- **🪝 Git Hooks**: Pre-configured Git hooks for code quality checks

## 📋 Requirements

Before using Laravel API Forge, make sure you have the following tools installed:

- **PHP 8.2+**: [Download PHP](https://www.php.net/downloads.php)
- **Composer**: [Install Composer](https://getcomposer.org/download/)
- **Laravel Installer 5.0+**: `composer global require laravel/installer`
- **Git**: [Install Git](https://git-scm.com/downloads)

The tool will automatically validate these requirements before starting.

## 🚀 Installation

Install Laravel API Forge globally using Composer:

```bash
composer global require goez/laravel-api-forge
```

Make sure your global Composer vendor bin directory is in your `$PATH`. You can add this to your shell profile (`.bashrc`, `.zshrc`, etc.):

```bash
# For bash/zsh
export PATH="$PATH:$HOME/.composer/vendor/bin"

# Alternative path on some systems
export PATH="$PATH:$HOME/.config/composer/vendor/bin"
```

After installation, you can use the tool globally:

```bash
laravel-api-forge --version
laravel-api-forge list
```

## 📖 Usage

### Basic Usage

Create a new Laravel API project:

```bash
laravel-api-forge new my-api-project
```

### Advanced Usage with Options

```bash
laravel-api-forge new my-api-project --redis --rbac --modules
```

### Available Options

- `--redis`: Use Redis as the cache store
- `--rbac`: Install and configure RBAC (Role-Based Access Control) package
- `--modules`: Install and configure modular architecture with Laravel Modules

### Interactive Mode

If you don't specify options, the tool will prompt you interactively:

```bash
laravel-api-forge new my-api-project

# You'll be asked:
# Do you want to use Redis as cache store? (yes/no)
# Do you want to install RBAC package? (yes/no)  
# Do you want to install modular architecture? (yes/no)
```

## 🏗️ What Gets Created

The tool creates a complete Laravel API project with:

### Core Setup
- Laravel 11+ with Pest testing framework
- Laravel Sanctum for API authentication
- API routes with `/v1` prefix
- MySQL database configuration
- Laravel Sail for Docker development environment

### Optional Features (based on your choices)

#### Redis Cache (`--redis`)
- Redis cache configuration
- Removes database cache migration
- Updates environment files

#### RBAC System (`--rbac`)
- Spatie Laravel Permission package
- Team-based permissions support
- Pre-configured User model with roles
- Abilities directory structure

#### Modular Architecture (`--modules`)
- Laravel Modules package
- Modular directory structure
- Vite integration for module assets
- Composer merge plugin for module dependencies

### Additional Packages
- **Laravel Data**: For structured data transfer objects
- **Spectator**: For OpenAPI specification testing
- **Laravel Pint**: For code style formatting

### Development Tools
- Pre-configured Git hooks (pre-commit, pre-push, post-merge)
- Automatic code formatting with Pint
- Environment file synchronization (.env and .env.example)
- Step-by-step Git commits for better history

## 🔄 Development Workflow

After creating your project:

```bash
cd my-api-project

# Start the development environment
./vendor/bin/sail up -d

# Run migrations
./vendor/bin/sail artisan migrate

# Run tests
./vendor/bin/sail composer test

# Code formatting
./vendor/bin/sail composer lint
```

## 📁 Project Structure

```
my-api-project/
├── app/
│   ├── Models/User.php          # Enhanced with Sanctum & RBAC
│   └── Abilities/               # RBAC abilities (if enabled)
├── docs/v1/                     # API documentation
├── modules/                     # Modular architecture (if enabled)
├── tests/
├── .git-hooks/                  # Pre-configured Git hooks
├── docker-compose.yml           # Laravel Sail configuration
└── vite-module-loader.js        # Module asset loading (if enabled)
```

## ⚙️ Configuration

The tool automatically configures:

- **Database**: MySQL with Laravel Sail
- **Cache**: Redis (if selected) or file-based
- **API**: Sanctum authentication with `/v1` prefix
- **Testing**: Pest with LazilyRefreshDatabase
- **Code Quality**: Laravel Pint with pre-commit hooks

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

Laravel API Forge is open-sourced software licensed under the [MIT license](LICENSE).

## 🙏 Acknowledgments

- Built with [Laravel Zero](https://laravel-zero.com/)
- Inspired by modern Laravel development practices
- Thanks to the Laravel community for the amazing ecosystem

---

**Happy coding! 🚀**
