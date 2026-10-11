# Dependency update

Updated to the newest releases allowed by each project’s existing framework/version constraints. Runtime families remain PHP 8.5/8.6, Node 24 and PostgreSQL 17.

| Project | Changed/newly locked packages | Selected direct dependencies |
|---|---:|---|
| fpm (PHP) | 57 | laravel/framework v13.35.0 |
| laravel-spawn (PHP) | 58 | laravel/framework v13.35.0, yangusik/laravel-spawn v0.4.9 |
| octane_frankenphp (PHP) | 59 | laravel/framework v13.35.0, laravel/octane v2.21.0 |
| octane_swoole (PHP) | 59 | laravel/framework v13.35.0, laravel/octane v2.21.0 |
| symfony-spawn (PHP) | 36 | symfony/framework-bundle v7.4.20, yangusik/symfony-spawn v0.2.3 |
| symfony-swoole (PHP) | 34 | runtime/swoole 1.0.0, symfony/framework-bundle v7.4.20 |
| symfony-swoole-coroutine (PHP) | 40 | silpo-tech/openswoole-bundle v1.3.3, symfony/framework-bundle v7.4.20 |
| fpm (npm) | 139 | vite 8.3.4, tailwindcss 4.3.3, axios 1.20.0 |
| laravel-spawn (npm) | 139 | vite 8.3.4, tailwindcss 4.3.3, axios 1.20.0 |
| nodejs-express (npm) | 10 | express 5.3.0, pg 8.23.1 |
| nodejs-nestjs (npm) | 16 | @nestjs/common 11.2.7, @nestjs/core 11.2.7, @nestjs/platform-express 11.2.7, pg 8.23.1, reflect-metadata 0.2.2, rxjs 7.8.2 |
| nodejs-nextjs (npm) | 51 | next 16.4.0, pg 8.23.1, react 19.3.0, react-dom 19.3.0 |
| octane_frankenphp (npm) | 139 | vite 8.3.4, tailwindcss 4.3.3, axios 1.20.0 |
| octane_swoole (npm) | 139 | vite 8.3.4, tailwindcss 4.3.3, axios 1.20.0 |

TrueAsync images are pinned to 0.10.0. Active versioned Docker base images were refreshed; runs retain their resolved image IDs.
