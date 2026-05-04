# QuizSnap

QuizSnap is a web application for **secure digital quizzes and exams** in schools: verified students, exam authoring, optional proctoring workflows, and student practice tools.

## Requirements

- PHP 8.2+, Composer
- Node.js + npm (for Vite frontend builds)
- Database (MySQL in production; SQLite supported for tests)

## Local setup

1. Copy `.env.example` to `.env` and configure `APP_KEY`, database, and mail.
2. Run `composer install` and `npm install`.
3. Run `php artisan migrate --seed` (includes default university and demo users from seeders).
4. Build assets: `npm run build` (or `npm run dev` during development).
5. Serve with `php artisan serve` (and configure Reverb/queues if you use live features).

## Testing

```bash
php artisan test
npm run build
```

## License

Proprietary unless otherwise stated.
