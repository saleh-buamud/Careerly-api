# CareerLy — Job Recruitment Platform

CareerLy is a job recruitment platform designed to connect job seekers with employers through a structured and secure recruitment process.

The platform enables employers to publish and manage job opportunities, while job seekers can explore available positions and submit applications. The backend is built with Laravel and MySQL and exposes a RESTful JSON API designed to integrate with a separate frontend application.

## Features

### 1. Authentication and Authorization

* User registration and login.
* Token-based authentication using Laravel Sanctum.
* Role-based access control for employers, job seekers, and administrators.
* Protected endpoints with server-side validation and authorization.

### 2. Job Management

* Browse publicly available job opportunities.
* View detailed job information.
* Search and filter jobs by keyword, category, location, and employment type.
* Create, update, and delete job posts according to employer permissions.
* Support for employment types, salary ranges, contact information, job status, and expiration dates.

### 3. Job Applications

* Allow job seekers to apply for available positions.
* Prevent duplicate applications to the same job.
* Allow employers to review applications for their own job posts.
* Manage application statuses according to the implemented workflow.
* Protect applicant information through appropriate access controls.

### 4. Profiles and Administration

* Employer profile endpoints.
* Job seeker profile endpoints.
* Management of job categories and skills through authorized endpoints.
* Validation and authorization for protected operations.

## Technology Stack

| Component            | Technology                                               |
| -------------------- | -------------------------------------------------------- |
| Backend              | Laravel / PHP                                            |
| Database             | MySQL                                                    |
| Authentication       | Laravel Sanctum                                          |
| API Architecture     | RESTful JSON API                                         |
| Validation           | Laravel Form Requests, where implemented                 |
| API Responses        | Laravel API Resources, where implemented                 |
| Authorization        | Policies and role-based access control, where configured |
| Testing              | PHPUnit / Laravel Feature Tests                          |
| Frontend Integration | React or another separate frontend application           |
| Version Control      | Git / GitHub                                             |

## Requirements

Before installing CareerLy, ensure the following tools are available:

* PHP version compatible with the project's Laravel version and dependencies.
* Composer.
* MySQL.
* Git.
* Node.js and npm, if frontend assets or Vite dependencies are required.

## Installation and Setup

### 1. Clone the Repository

```bash
git clone <YOUR_REPOSITORY_URL>
cd CareerLy
```

Replace `<YOUR_REPOSITORY_URL>` with the actual repository URL.

### 2. Install Dependencies

Install the PHP dependencies:

```bash
composer install
```

If the project uses frontend assets or Vite:

```bash
npm install
```

### 3. Configure Environment Variables

Create your local environment file.

**Linux / macOS / Git Bash:**

```bash
cp .env.example .env
```

**Windows PowerShell:**

```powershell
Copy-Item .env.example .env
```

Generate the Laravel application key:

```bash
php artisan key:generate
```

### 4. Configure the Database

Create a MySQL database named `careerly`, or choose another database name and configure it in `.env`.

Example configuration:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=careerly
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

Replace the example credentials with your local MySQL credentials.

**Security note:** Never commit `.env` files, database passwords, API tokens, or other sensitive credentials to version control.

### 5. Run Database Migrations

After confirming that the database configuration is correct, run:

```bash
php artisan migrate
```

This applies the available database migrations.

Do not use destructive migration commands on a database containing data you need to preserve.

### 6. Start the Development Server

```bash
php artisan serve
```

The default local addresses are usually:

* Application: `http://127.0.0.1:8000`
* API base URL: `http://127.0.0.1:8000/api`

Verify the registered routes and server configuration before connecting the frontend.

### 7. Optional: Use the Windows Setup Script

The repository includes a `setup-project.ps1` script intended to simplify project setup on Windows.

Review the script before execution and follow its documented requirements. Run it only after configuring the project environment and verifying which commands it executes.

## API Documentation

CareerLy provides JSON API endpoints for authentication, job posts, job applications, profiles, categories, and skills.

Refer to the following project documentation:

* **`API_FRONTEND_DOCUMENTATION.md`** — API endpoints, request and response formats, validation rules, authentication requirements, and frontend integration guidance.
* **`CAREERLY_API_TEST_REPORT.md`** — recorded API testing results, verification scope, and known limitations.
* **`docs/PROJECT_STATUS_REPORT.md`** — project status, implementation progress, and outstanding work.

Consult the latest documentation and registered routes for the exact behavior of each endpoint.

### Authentication

Protected endpoints that use Laravel Sanctum bearer tokens require an appropriate access token.

Example request headers:

```http
Authorization: Bearer YOUR_ACCESS_TOKEN
Accept: application/json
Content-Type: application/json
```

The required authentication method and user role depend on the specific endpoint.

## Testing

Run the automated test suite with:

```bash
php artisan test
```

For reliable results:

* Configure a dedicated test environment and test database.
* Review the testing configuration before executing database-backed tests.
* Never run automated tests against a production database.
* Review failed tests and verify the actual results rather than relying solely on documentation.

See `CAREERLY_API_TEST_REPORT.md` for the recorded test results and their verification scope.

## Project Structure

The following tree summarizes the main application components. Confirm the exact filenames and directories against the current repository.

```text
CareerLy/
├── app/
│   ├── Enums/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   ├── Middleware/
│   │   ├── Requests/
│   │   └── Resources/
│   │       └── UserResource.php
│   ├── Models/
│   ├── Policies/
│   └── Providers/
│       └── AppServiceProvider.php
├── bootstrap/
├── config/
│   └── cors.php
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── docs/
│   └── PROJECT_STATUS_REPORT.md
├── routes/
│   ├── api.php
│   └── api/
│       └── auth.php
├── tests/
│   └── Feature/
├── API_FRONTEND_DOCUMENTATION.md
├── CAREERLY_API_TEST_REPORT.md
├── README.md
└── setup-project.ps1
```

## Security Considerations

* Never commit environment files or sensitive credentials.
* Enforce authorization on the backend for all protected operations.
* Validate incoming data on the server.
* Restrict access to employer-owned job posts and private application information.
* Configure CORS to allow only the required frontend origins in production.
* Use HTTPS in production.
* Keep test, development, and production databases separate.
* Use appropriate error responses without exposing internal implementation details.
* Review authentication, authorization, and input-validation tests when modifying protected endpoints.

## Development Status

CareerLy is under active development. The project includes a Laravel backend, API endpoints, authentication, job management, application workflows, and supporting documentation.

Implementation status and verified functionality should be assessed using the current source code, automated test results, and API documentation. Features that have not been tested should not be considered fully verified.

## License

No license has been specified in this document. Add an appropriate `LICENSE` file before distributing the project or granting others explicit reuse rights.

Until a license is added, do not assume that the source code is available for unrestricted reuse.

---

**CareerLy — Connecting Job Seekers with Opportunities.**
