# Library Test Project

A simple Laravel API for managing a digital library.

## Live Server
https://library-test-8q62.onrender.com/

## Features
- User authentication (register/login/logout)
- Book management (create, read, update, delete)
- Book categories
- Book comments
- Book views tracking
- Save/unsave books
- Book file uploads/downloads
- Search and filter books (popular, recent)

## Tech Stack
- Laravel 11
- PHP 8.2+
- Laravel Sanctum (authentication)
- Scramble (API documentation)

## Installation
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## License
MIT
