# CareerLy — Job Recruitment Platform

CareerLy is a job recruitment platform designed to connect job seekers with employers. Employers can publish and manage job opportunities, while job seekers can browse available positions and submit applications.

The project is built with Laravel and MySQL, with a RESTful API designed to support a separate frontend application.

## Features

### Authentication and Authorization

* User registration and login.
* Token-based authentication using Laravel Sanctum.
* Role-based access control for employers, job seekers, and administrators.
* Protected API endpoints based on user roles and permissions.

### Job Management

* Browse publicly available job opportunities.
* View job details.
* Search and filter jobs by keyword, category, location, and employment type.
* Create, update, and delete job posts as an authorized employer.
* Support for job status, salary information, contact details, and expiration dates.

### Job Applications

* Allow job seekers to apply for available positions.
* Prevent duplicate applications to the same job.
* Allow employers to review applications submitted to their own job posts.
* Manage application statuses according to the defined workflow.

### Profiles and Administration

* Employer and job-seeker profile endpoints.
* Administrative management of job categories and skills.
* Validation and authorization for protected operations.

## Technology Stack

* **Backend:** Laravel / PHP
* **Database:** MySQL
* **Authentication:** Laravel Sanctum
* **API:** RESTful JSON API
* **Testing:** PHPUnit / Laravel Feature Tests
* **Frontend:** Designed to integrate with a separate frontend application, such as React.

## Requirements

Before running the project, make sure you have:

* PHP version compatible with the project's Laravel version.
* Composer.
* MySQL.
* Node.js and npm, if frontend assets or Vite are needed.
* Git.

## Installation

### 1. Clone the repository

```bash
git clone <YOUR_REPOSITORY_URL>
cd CareerLy
```

Replace `<YOUR_REPOSITORY_URL>` with your actual repository URL.

### 2. Install dependencies

```bash
composer install
```

If the project requires frontend asset dependencies:

```bash
npm install
```

### 3. Configure environment variables

```bash
cp .env.example .env
php artisan key:generate
```

On Windows, you can copy `.env.example` to `.env` manually if necessary.

Configure your database connection in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=careerly
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Use your own local database credentials. Never commit `.env` or publish database passwords, API tokens, or other secrets.

### 4. Run database migrations

After verifying that the configured database is the intended development database and that you have a backup if needed, run:

```bash
php artisan migrate
```

Do not use destructive commands such as `migrate:fresh` on a database containing data you need to preserve.

### 5. Start the development server

```bash
php artisan serve
```

The default local server is usually available at:

`http://127.0.0.1:8000`

The API base URL is usually:

`http://127.0.0.1:8000/api`

Verify the actual registered routes and local server configuration before integrating the frontend.

## API Documentation

The backend exposes JSON API endpoints for authentication, job browsing, job management, profiles, applications, categories, and skills.

For complete endpoint details, request examples, response formats, validation rules, authentication requirements, and error handling, see:

* `API_FRONTEND_DOCUMENTATION.md` — frontend integration guide, if present in the repository.
* `CAREERLY_API_TEST_REPORT.md` — API testing and verification report, if present.

### Authentication

Protected endpoints use Laravel Sanctum token authentication where configured.

For bearer-token endpoints, send the token in the request header:

```http
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
```

Follow the endpoint documentation for the exact request body, response structure, and required user role.

## Testing

The project includes automated tests for API behavior, authentication, authorization, and job application workflows.

Run the test suite using the project's configured testing environment:

```bash
php artisan test
```

**Important:** Configure and verify a dedicated test database before running database-backed tests. Do not run tests against a development or production database.

See `CAREERLY_API_TEST_REPORT.md` for the recorded test results and verification scope.

## Project Structure

```text
CareerLy/
├── app/
│   ├── Enums/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Resources/
│   └── Models/
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── routes/
│   ├── api.php
│   └── api/
├── tests/
│   └── Feature/
├── API_FRONTEND_DOCUMENTATION.md
├── CAREERLY_API_TEST_REPORT.md
└── README.md
```

This structure is illustrative; the actual repository may contain additional files or directories.

## Security Notes

* Never commit `.env` files or credentials.
* Keep authorization checks on the backend; frontend role checks are not a substitute for API security.
* Validate all incoming data on the server.
* Use HTTPS in production.
* Keep test and production databases isolated.
* Do not expose private applicant information through public API responses.

## Development Status

CareerLy is under active development. The backend API, authentication, job management, application workflows, and automated tests are being developed and verified incrementally.

Check the API documentation and test report for the latest verified functionality and known limitations.

## License

Specify the project's license here before distributing or reusing the code. If no license has been selected, the project remains subject to the applicable default copyright rules.

---

**CareerLy** — Connecting job seekers with opportunities.
