# CareerLy API Frontend Integration Guide

**Documentation scope:** current registered Laravel API implementation, inspected on 2026-10-10. JSON examples are representative and derived from controllers, resources, models, and feature tests; they are not captured live HTTP responses. Hosts, ports, generated IDs, timestamps, and token values vary by environment.

## 1. API Overview

- **Backend:** Laravel 12, PHP `^8.2`, Laravel Sanctum `^4.0`.
- **API version:** no version segment is defined. Routes currently use the `/api` prefix.
- **Example local base URL:** `http://127.0.0.1:8000/api`. The hostname and port depend on how the backend is hosted.
- **Production:** configure the deployed HTTPS API origin, including `/api`, in the frontend environment. Example: `VITE_API_BASE_URL=https://api.example.test/api`.
- **Request/response format:** JSON for request bodies and JSON responses. Send `Accept: application/json` so validation/auth errors are returned as JSON. Send `Content-Type: application/json` when sending a JSON body. GET/DELETE requests do not need a body.
- **Authentication:** bearer personal access tokens from Sanctum. Login and registration return a `token` field in the JSON body. Use `Authorization: Bearer <token>` on protected requests. Token text has Sanctum's `id|secret` form; the exact value is secret and must not be logged.
- **Token expiry:** `sanctum.expiration` is currently `null` (no global minute-based expiry configured); no refresh-token endpoint exists. Review token lifetime and revocation policy for each deployment.
- **Cookies:** the documented API workflow issues bearer tokens. The feature tests verify bearer-token logout. Cookie-based SPA authentication is not verified as an integration contract here.
- **Token handling:** prefer holding a token in memory. Persistent browser storage such as `localStorage` is readable by injected JavaScript and increases the impact of XSS. If persistence is required, review the threat model and use appropriate XSS defenses. Never put a token or backend secret in source control.
- **Vite configuration:** `VITE_API_BASE_URL` is exposed in the built frontend. It may contain a public API URL, but must never contain a password, private API key, or other backend secret.
- **Profile workflow:** registration does not create a profile. Employers and job seekers create their own profile afterward using the matching profile endpoint.
- **Not implemented:** no password-reset, email-verification, refresh-token, profile-delete, saved-job, or job-seeker application-history endpoint was found.

### Authentication and role behavior

Registered user roles are `employer`, `job_seeker`, and `admin`. Public registration accepts only `employer` or `job_seeker`; omitting `role` defaults to `job_seeker`. A client cannot register itself as `admin`.

Roles are checked by backend middleware/controller logic; frontend role checks are only for user experience and do not replace the API checks. Typical responses are `401` when not authenticated and `403` when authenticated without permission. The role middleware message is `You do not have permission to perform this action.` Profile controllers use endpoint-specific 403 messages.

### Pagination

Public job listing and employer application listing use Laravel resource pagination. The response has top-level `data`, `links`, and `meta`. Both accept `per_page` from 1 through 100, default 15. Both use `page` through Laravel's paginator; public jobs explicitly validate it as an integer of at least 1. Employer application listing does not add its own `page` validation. Job list ordering is ascending `id`; employer applications are newest-first by `created_at`, then descending `id`.

## 2. Common Request and Error Conventions

For JSON requests, use:

```http
Accept: application/json
Content-Type: application/json
```

For authenticated requests, additionally use:

```http
Authorization: Bearer <token-from-register-or-login>
```

Laravel validation errors returned for JSON requests use the standard shape below. The exact message and error keys depend on the fields rejected:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field must be a valid email address."]
  }
}
```

Representative general errors:

| Status | Current use | Representative body |
|---|---|---|
| `200 OK` | Successful reads, updates, deletes, logout, and status updates | Endpoint-specific JSON; this API does not use a universal `data` wrapper. |
| `201 Created` | Registration, profile create, job create, application create, category/skill create | Endpoint-specific JSON. |
| `204 No Content` | Not used by the inspected API routes/controllers. Delete operations return JSON with `200`. |
| `401 Unauthorized` | Missing/invalid Sanctum authentication on protected routes; invalid login credentials | Middleware: `{"message":"Unauthenticated."}`. Login failure: `{"message":"Unauthorized"}`. |
| `403 Forbidden` | Insufficient role or a different employer tries to modify a job | Usually `{"message":"You do not have permission to perform this action."}`; profile messages are endpoint-specific. |
| `404 Not Found` | Missing record; closed/expired public job; application not owned by requesting employer | A JSON `message` is returned by the corresponding controller. |
| `409 Conflict` | Duplicate profile/application or disallowed application-status transition | Controller-specific JSON `message`. |
| `422 Unprocessable Content` | Request validation failure | Laravel `{message, errors}` JSON structure. |
| `500 Internal Server Error` | Unexpected server-side exception | No stable custom 500 response is defined. Do not depend on debug exception details; production response depends on server configuration. |

## 3. Authentication

### Register

**Method and URL:** `POST /api/register`

**Purpose:** Create an account and issue a Sanctum personal access token. It does not create a profile.

**Authentication:** Public.

**Required role:** None. If `role` is sent, it may be `employer` or `job_seeker`; `admin` is rejected.

**Headers:** `Accept: application/json`; `Content-Type: application/json`.

**Query parameters:** None.

**Request body:**

| Field | Type | Required | Validation / values | Nullable |
|---|---|---:|---|---:|
| `name` | string | Yes | Maximum 255 characters | No |
| `email` | string | Yes | Valid email, maximum 255, unique in users | No |
| `phone` | string | No | Maximum 20 characters | Yes |
| `password` | string | Yes | At least 8 characters and must match `password_confirmation` | No |
| `password_confirmation` | string | Yes | Must match password (`confirmed` validation) | No |
| `role` | string | No | `employer` or `job_seeker`; defaults to `job_seeker` | Yes |

**Example request:**

```http
POST /api/register HTTP/1.1
Host: 127.0.0.1:8000
Accept: application/json
Content-Type: application/json

{
  "name": "Jordan Example",
  "email": "jordan@example.test",
  "phone": null,
  "password": "ExamplePass123",
  "password_confirmation": "ExamplePass123",
  "role": "job_seeker"
}
```

**Successful response:** `201 Created`.

```json
{
  "message": "User registered successfully",
  "user": {
    "id": 101,
    "name": "Jordan Example",
    "email": "jordan@example.test",
    "phone": null,
    "role": "job_seeker",
    "email_verified_at": null,
    "created_at": "2026-10-10T12:00:00.000000Z",
    "updated_at": "2026-10-10T12:00:00.000000Z"
  },
  "token": "101|<redacted-token-secret>"
}
```

The `token` is the bearer token value to send on later protected calls. `password` and `remember_token` are hidden from serialized users. `role` is the authenticated role field. Timestamps/ID are examples.

**Error responses:** `422` for invalid/missing name, email, password, confirmation, phone type/length, or role; duplicate email is `422`. A typical body is `{ "message": "...", "errors": { "field": ["..."] } }`. Unsupported admin registration is a `role` validation error.

**Frontend notes:** retain the returned token securely and send it in the Bearer header. Prompt the user to create their employer/job-seeker profile separately. There is no email verification or password-reset route documented here.

### Login

**Method and URL:** `POST /api/login`

**Purpose:** Check credentials and issue a new Sanctum personal access token.

**Authentication:** Public.

**Required role:** None.

**Headers:** `Accept: application/json`; `Content-Type: application/json`.

**Query parameters:** None.

**Request body:**

| Field | Type | Required | Validation / values | Nullable |
|---|---|---:|---|---:|
| `email` | string | Yes | Valid email | No |
| `password` | string | Yes | String; no additional length rule in this action | No |

**Example request:**

```json
{
  "email": "jordan@example.test",
  "password": "ExamplePass123"
}
```

**Successful response:** `200 OK`. The response has the same `user` and `token` shape as registration, with message `Login successful`.

```json
{
  "message": "Login successful",
  "user": {
    "id": 101,
    "name": "Jordan Example",
    "email": "jordan@example.test",
    "phone": null,
    "role": "job_seeker",
    "email_verified_at": null,
    "created_at": "2026-10-10T12:00:00.000000Z",
    "updated_at": "2026-10-10T12:00:00.000000Z"
  },
  "token": "101|<redacted-token-secret>"
}
```

**Error responses:** `401` for unknown email or incorrect password: `{"message":"Unauthorized"}`. Invalid/missing email or password returns `422` with Laravel validation JSON.

**Frontend notes:** obtain the role from `user.role`; do not infer permissions solely from frontend state. Store/use the token as described above.

### Logout

**Method and URL:** `POST /api/logout`

**Purpose:** Revoke the current Sanctum personal access token.

**Authentication:** Protected with `auth:sanctum`.

**Required role:** Any authenticated user.

**Headers:** `Accept: application/json`; `Authorization: Bearer <token>`. No body is required.

**Query parameters:** None.

**Request body:** None. Extra body data is unused.

**Example request:**

```http
POST /api/logout HTTP/1.1
Host: 127.0.0.1:8000
Accept: application/json
Authorization: Bearer <token-from-login>
```

**Successful response:** `200 OK`.

```json
{"message":"Logged out successfully"}
```

**Error responses:** `401` if the request is unauthenticated. The verified bearer-token behavior deletes only the current token; a second token for the same user remains valid. Cookie-based session logout is not established as this endpoint's frontend contract.

**Frontend notes:** discard the local token after success. Other devices/tokens are not revoked by this endpoint.

## 4. Public Job Browsing

### List/search jobs

**Method and URL:** `GET /api/job-posts`

**Purpose:** List publicly visible jobs and apply supported database-side filters.

**Authentication:** Public.

**Required role:** None.

**Headers:** `Accept: application/json`. No request body.

**Query parameters:**

| Name | Type | Required | Values / constraints | Default / behavior |
|---|---|---:|---|---|
| `q` | string | No | Maximum 255 characters | Substring `LIKE` search across `title` OR `description`. |
| `category_id` | integer | No | Must exist in `job_categories` | Exact category match. |
| `location` | string | No | Maximum 255 characters | Substring `LIKE` match on the free-text `job_posts.location`. This is not a structured city filter. |
| `employment_type` | string | No | `full_time`, `part_time`, `remote`, `freelance`, `internship` | Exact match. |
| `per_page` | integer | No | 1 through 100 | 15. |
| `page` | integer | No | Integer at least 1 | Laravel paginator default (first page). |

Filters combine with AND, except the `q` group which matches title or description. Results always apply the public visibility condition: status must be `active`, and expiry must be null or at/after current application time. The query orders by ascending `id`. No sort parameter is implemented.

**Example request:**

```http
GET /api/job-posts?q=backend&category_id=4&location=Amman&employment_type=full_time&per_page=10&page=1 HTTP/1.1
Host: 127.0.0.1:8000
Accept: application/json
```

**Successful response:** `200 OK`; resource paginator shape:

```json
{
  "data": [
    {
      "id": 25,
      "employer_id": 9,
      "category_id": 4,
      "title": "Backend Engineer",
      "description": "Build and maintain APIs.",
      "requirements": null,
      "location": "Amman",
      "employment_type": "full_time",
      "salary_min": 900,
      "salary_max": 1500,
      "salary_currency": "USD",
      "contact_email": "hiring@example.test",
      "contact_phone": null,
      "google_form_url": null,
      "status": "active",
      "expires_at": "2026-12-01T00:00:00.000000Z",
      "created_at": "2026-10-01T12:00:00.000000Z",
      "updated_at": "2026-10-01T12:00:00.000000Z",
      "category": {"id": 4, "name": "Engineering"},
      "employer": {"id": 9, "name": "Example Employer"},
      "skills": [{"id": 3, "name": "PHP"}]
    }
  ],
  "links": {
    "first": "http://127.0.0.1:8000/api/job-posts?q=backend&category_id=4&location=Amman&employment_type=full_time&per_page=10&page=1",
    "last": "http://127.0.0.1:8000/api/job-posts?q=backend&category_id=4&location=Amman&employment_type=full_time&per_page=10&page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "links": [
      {"url": null, "label": "&laquo; Previous", "active": false},
      {"url": "http://127.0.0.1:8000/api/job-posts?q=backend&category_id=4&location=Amman&employment_type=full_time&per_page=10&page=1", "label": "1", "active": true},
      {"url": null, "label": "Next &raquo;", "active": false}
    ],
    "path": "http://127.0.0.1:8000/api/job-posts",
    "per_page": 10,
    "to": 1,
    "total": 1
  }
}
```

The example host, data, and totals are illustrative. Laravel generates `meta.links` entries for page navigation. An empty result still returns `200`, `data: []`, and `meta.total: 0`.

**Error responses:** `422` for invalid query values, e.g. `per_page=101`, a nonexistent `category_id`, or unsupported `employment_type`; Laravel validation format applies.

**Frontend notes:** preserve query parameters when navigating pages. The returned `links` may contain absolute URLs based on the current backend host; alternatively use `meta.current_page` and `meta.last_page` to build the next query.

### View one public job

**Method and URL:** `GET /api/job-posts/{jobPost}`

**Purpose:** Fetch a single publicly visible job by numeric database ID.

**Authentication:** Public.

**Required role:** None.

**Headers:** `Accept: application/json`. No body or query parameters.

**Request body:** None.

**Example request:**

```http
GET /api/job-posts/25 HTTP/1.1
Host: 127.0.0.1:8000
Accept: application/json
```

**Successful response:** `200 OK`; a `JobPostResource` with top-level `data`. The controller loads `category` and `employer`; it does not load `skills`, so the `skills` property is omitted on this response.

```json
{
  "data": {
    "id": 25,
    "employer_id": 9,
    "category_id": 4,
    "title": "Backend Engineer",
    "description": "Build and maintain APIs.",
    "requirements": null,
    "location": "Amman",
    "employment_type": "full_time",
    "salary_min": 900,
    "salary_max": 1500,
    "salary_currency": "USD",
    "contact_email": "hiring@example.test",
    "contact_phone": null,
    "google_form_url": null,
    "status": "active",
    "expires_at": "2026-12-01T00:00:00.000000Z",
    "created_at": "2026-10-01T12:00:00.000000Z",
    "updated_at": "2026-10-01T12:00:00.000000Z",
    "category": {"id": 4, "name": "Engineering"},
    "employer": {"id": 9, "name": "Example Employer"}
  }
}
```

**Error responses:** `404 Not Found` for missing, closed, or expired jobs: `{"message":"Job post not found"}`.

**Frontend notes:** a job can disappear from public detail after closure or expiration; treat 404 as unavailable, not as an authorization prompt.

## 5. Employer Job-Post Management

All three management routes require a Sanctum token and `employer` role. Employers can only update/delete their own jobs. Job create ownership comes from the authenticated user; clients cannot set `employer_id` through the validated input.

### Create job post

**Method and URL:** `POST /api/job-posts`

**Purpose:** Create an employer-owned job post and synchronize optional skill IDs.

**Authentication:** Protected.

**Required role:** `employer`.

**Headers:** `Accept: application/json`, `Content-Type: application/json`, Bearer authorization.

**Query parameters:** None.

**Request body:**

| Field | Type | Required | Validation / values | Nullable |
|---|---|---:|---|---:|
| `category_id` | integer ID | Yes | Existing `job_categories.id` | No |
| `title` | string | Yes | Maximum 255 | No |
| `description` | string | Yes | String; no max rule defined here | No |
| `requirements` | string | No | String | Yes |
| `location` | string | No | Maximum 255 | Yes |
| `employment_type` | string | No | `full_time`, `part_time`, `remote`, `freelance`, `internship` | Yes |
| `salary_min` | integer | No | At least 0 | Yes |
| `salary_max` | integer | No | At least 0 | Yes |
| `salary_currency` | string | No | Maximum 10 characters | Yes |
| `contact_email` | string | Conditional | Valid email; required only when both phone and form URL are absent | Yes |
| `contact_phone` | string | No | String; no length/format constraint in controller | Yes |
| `google_form_url` | string | No | Valid URL | Yes |
| `status` | string | Yes | `active` or `closed` | No |
| `expires_at` | date/time string | No | Laravel `date` validation | Yes |
| `skills` | array of integer IDs | No | Each ID must exist in `skills.id` | Yes |

At least one of email, phone, or Google Form URL is required. The `employer_id` is assigned from the bearer-authenticated user, not from the request. Extra unvalidated fields are not used by the create action.

**Example request:**

```json
{
  "category_id": 4,
  "title": "Backend Engineer",
  "description": "Build and maintain APIs.",
  "requirements": "Experience with PHP.",
  "location": "Amman",
  "employment_type": "full_time",
  "salary_min": 900,
  "salary_max": 1500,
  "salary_currency": "USD",
  "contact_email": "hiring@example.test",
  "status": "active",
  "expires_at": "2026-12-01T00:00:00Z",
  "skills": [3, 7]
}
```

**Successful response:** `201 Created`. `job_post` is serialized directly from the model; it is not a `JobPostResource` here and does not include nested category/employer/skills.

```json
{
  "message": "Job post created successfully",
  "job_post": {
    "id": 25,
    "employer_id": 9,
    "category_id": 4,
    "title": "Backend Engineer",
    "description": "Build and maintain APIs.",
    "requirements": "Experience with PHP.",
    "location": "Amman",
    "employment_type": "full_time",
    "salary_min": 900,
    "salary_max": 1500,
    "salary_currency": "USD",
    "contact_email": "hiring@example.test",
    "contact_phone": null,
    "google_form_url": null,
    "status": "active",
    "expires_at": "2026-12-01T00:00:00.000000Z",
    "created_at": "2026-10-01T12:00:00.000000Z",
    "updated_at": "2026-10-01T12:00:00.000000Z",
    "deleted_at": null
  }
}
```

**Error responses:** `401` guest; `403` wrong role; `422` invalid fields/IDs or no acceptable contact method. Validation follows Laravel's JSON `{message, errors}` structure.

**Frontend notes:** category/skill IDs are integers. The API accepts `status=closed` at create time, but closed jobs will not be publicly visible.

### Update job post

**Method and URL:** `PUT /api/job-posts/{jobPost}`

**Purpose:** Replace writable job fields for a job the employer owns.

**Authentication:** Protected.

**Required role:** `employer` and owner of the target post.

**Headers:** JSON headers and Bearer authorization.

**Query parameters:** None.

**Request body:** Same field rules as create, including required `category_id`, `title`, `description`, and `status`. This is not a partial update despite using PUT. `skills` is nullable/optional in validation, but the controller calls `sync($validated['skills'] ?? [])`: omitting or sending null clears all existing skill links. `employer_id` is not writable.

**Example request:** Use the create body with the full required fields for the replacement values.

**Successful response:** `200 OK`, a `JobPostResource` with top-level `data`; category, employer, and skills are loaded and included.

```json
{
  "data": {
    "id": 25,
    "employer_id": 9,
    "category_id": 4,
    "title": "Senior Backend Engineer",
    "description": "Build and maintain APIs.",
    "requirements": null,
    "location": "Amman",
    "employment_type": "full_time",
    "salary_min": null,
    "salary_max": null,
    "salary_currency": null,
    "contact_email": "hiring@example.test",
    "contact_phone": null,
    "google_form_url": null,
    "status": "active",
    "expires_at": null,
    "created_at": "2026-10-01T12:00:00.000000Z",
    "updated_at": "2026-10-10T12:00:00.000000Z",
    "category": {"id": 4, "name": "Engineering"},
    "employer": {"id": 9, "name": "Example Employer"},
    "skills": []
  }
}
```

**Error responses:** `401` unauthenticated; `403` not employer or not owner; `404` missing/soft-deleted post; `422` missing required fields or invalid values.

**Frontend notes:** send all required fields. Include `skills` explicitly if associations should be preserved; omission clears them.

### Delete job post

**Method and URL:** `DELETE /api/job-posts/{jobPost}`

**Purpose:** Soft-delete a job owned by the authenticated employer and detach skill relations.

**Authentication:** Protected.

**Required role:** `employer` and owner.

**Headers:** `Accept: application/json`, Bearer authorization. No body.

**Query parameters / request body:** None.

**Successful response:** `200 OK`.

```json
{"message":"Job post deleted"}
```

**Error responses:** `401`, `403` for another employer's job, `404` when missing or already soft-deleted. The controller returns JSON messages for 403/404.

**Frontend notes:** removal is soft deletion; the API uses `200` with a body, not `204`.

## 6. Employer and Job-Seeker Profiles

Registration does not automatically create profiles. Each profile endpoint is under `auth:sanctum`; controller role checks restrict employer endpoints to employers and job-seeker endpoints to job seekers. A user accesses the profile attached to their own authenticated account; there is no `{user_id}` path parameter. Duplicate creates return 409. There are no DELETE profile routes.

The controllers return raw Eloquent model JSON for `profile` rather than the separate profile Resource classes. The profile's `user_id` is assigned by the authenticated user's relationship; client-supplied `user_id` is not used. Successful profile GET responses include a limited `user` object (`id`, `name`, `email`, `phone`) and the complete own-profile model.

### Employer profile: read

**Method and URL:** `GET /api/employer-profile`

**Purpose:** Read the authenticated employer's profile.

**Authentication:** Protected; **required role:** `employer`.

**Headers:** `Accept: application/json`, Bearer authorization. **Query/body:** none.

**Successful response:** `200 OK`.

```json
{
  "user": {"id": 9, "name": "Example Employer", "email": "owner@example.test", "phone": null},
  "profile": {
    "id": 6,
    "user_id": 9,
    "employer_type": "company",
    "organization_name": "Example Company",
    "description": null,
    "logo": null,
    "address": null,
    "website": "https://example.test",
    "created_at": "2026-10-01T12:00:00.000000Z",
    "updated_at": "2026-10-01T12:00:00.000000Z"
  }
}
```

**Errors:** `401` unauthenticated; `403` wrong role (`Only employers can access this profile.`); `404` if no profile (`Employer profile not found.`).

**Frontend notes:** profile is private to the authenticated owner in this route.

### Employer profile: create

**Method and URL:** `POST /api/employer-profile`

**Purpose:** Create the authenticated employer's one profile.

**Authentication:** Protected; **required role:** `employer`.

**Headers:** JSON headers and Bearer authorization. **Query:** none.

**Request body:**

| Field | Type | Required | Validation / values | Nullable |
|---|---|---:|---|---:|
| `employer_type` | string | Yes | `individual`, `company`, `organization` | No |
| `organization_name` | string | No | Maximum 255 | Yes |
| `description` | string | No | String | Yes |
| `logo` | string | No | Maximum 255 | Yes |
| `address` | string | No | Maximum 255 | Yes |
| `website` | string | No | Valid URL, maximum 255 | Yes |

**Example request:**

```json
{"employer_type":"company","organization_name":"Example Company","website":"https://example.test"}
```

**Success:** `201 Created`.

```json
{
  "message": "Employer profile created successfully.",
  "profile": {
    "id": 6,
    "user_id": 9,
    "employer_type": "company",
    "organization_name": "Example Company",
    "description": null,
    "logo": null,
    "address": null,
    "website": "https://example.test",
    "created_at": "2026-10-01T12:00:00.000000Z",
    "updated_at": "2026-10-01T12:00:00.000000Z"
  }
}
```

**Errors:** `401`; `403` wrong role (`Only employers can create this profile.`); `409` if the profile already exists (`Employer profile already exists.`); `422` validation failure.

**Frontend notes:** no need or ability to submit `user_id`; it is associated with the token's user. Registration does not call this automatically.

### Employer profile: update

**Method and URL:** `PUT /api/employer-profile`

**Purpose:** Update selected fields on the authenticated employer's profile.

**Authentication:** Protected; **required role:** `employer`.

**Headers:** JSON headers and Bearer authorization. **Query:** none.

**Request body:** Same fields as employer profile create; all are optional on update. If supplied, `employer_type` is required and must be allowed; other fields allow null as indicated above. An empty JSON object is accepted and changes nothing.

**Success:** `200 OK`.

```json
{
  "message": "Employer profile updated successfully.",
  "profile": {
    "id": 6,
    "user_id": 9,
    "employer_type": "company",
    "organization_name": "Updated Example Company",
    "description": null,
    "logo": null,
    "address": null,
    "website": "https://example.test",
    "created_at": "2026-10-01T12:00:00.000000Z",
    "updated_at": "2026-10-10T12:00:00.000000Z"
  }
}
```

**Errors:** `401`; `403` wrong role (`Only employers can update this profile.`); `404` if no profile (`Employer profile not found.`); `422` invalid supplied fields.

**Frontend notes:** this endpoint does not create a missing profile; call POST first.

### Job-seeker profile: read

**Method and URL:** `GET /api/job-seeker-profile`

**Purpose:** Read the authenticated job seeker's profile.

**Authentication:** Protected; **required role:** `job_seeker`.

**Headers:** `Accept: application/json`, Bearer authorization. **Query/body:** none.

**Success:** `200 OK`.

```json
{
  "user": {"id": 21, "name": "Jordan Example", "email": "jordan@example.test", "phone": null},
  "profile": {
    "id": 11,
    "user_id": 21,
    "full_name": "Jordan Example",
    "headline": "Backend developer",
    "bio": null,
    "city": "Amman",
    "profile_image": null,
    "created_at": "2026-10-01T12:00:00.000000Z",
    "updated_at": "2026-10-01T12:00:00.000000Z"
  }
}
```

**Errors:** `401`; `403` wrong role (`Only job seekers can access this profile.`); `404` with `Job seeker profile not found.` when absent.

**Frontend notes:** this endpoint returns the owner's profile, not a public candidate lookup.

### Job-seeker profile: create

**Method and URL:** `POST /api/job-seeker-profile`

**Purpose:** Create the authenticated job seeker's one profile.

**Authentication:** Protected; **required role:** `job_seeker`.

**Headers:** JSON headers and Bearer authorization. **Query:** none.

**Request body:** all fields are optional and nullable.

| Field | Type | Required | Validation | Nullable |
|---|---|---:|---|---:|
| `full_name` | string | No | Maximum 255 | Yes |
| `headline` | string | No | Maximum 255 | Yes |
| `bio` | string | No | String | Yes |
| `city` | string | No | Maximum 255 | Yes |
| `profile_image` | string | No | Maximum 255 | Yes |

**Example request:**

```json
{"full_name":"Jordan Example","headline":"Backend developer","city":"Amman"}
```

An empty object is allowed and creates a profile with nullable fields unset/null.

**Success:** `201 Created`.

```json
{
  "message": "Job seeker profile created successfully.",
  "profile": {
    "id": 11,
    "user_id": 21,
    "full_name": "Jordan Example",
    "headline": "Backend developer",
    "bio": null,
    "city": "Amman",
    "profile_image": null,
    "created_at": "2026-10-01T12:00:00.000000Z",
    "updated_at": "2026-10-01T12:00:00.000000Z"
  }
}
```

**Errors:** `401`; `403` wrong role (`Only job seekers can create this profile.`); `409` duplicate (`Job seeker profile already exists.`); `422` invalid field type/length.

**Frontend notes:** the backend derives ownership from the authenticated user; do not send an applicant/user ID.

### Job-seeker profile: update

**Method and URL:** `PUT /api/job-seeker-profile`

**Purpose:** Update the authenticated job seeker's existing profile.

**Authentication:** Protected; **required role:** `job_seeker`.

**Headers:** JSON headers and Bearer authorization. **Query:** none.

**Request body:** same fields as profile creation; each is optional, and nullable. Empty `{}` is accepted and leaves data unchanged.

**Success:** `200 OK`.

```json
{
  "message": "Job seeker profile updated successfully.",
  "profile": {
    "id": 11,
    "user_id": 21,
    "full_name": "Jordan Example",
    "headline": "Senior backend developer",
    "bio": null,
    "city": "Amman",
    "profile_image": null,
    "created_at": "2026-10-01T12:00:00.000000Z",
    "updated_at": "2026-10-10T12:00:00.000000Z"
  }
}
```

**Errors:** `401`; `403` wrong role (`Only job seekers can update this profile.`); `404` if profile does not exist (`Job seeker profile not found.`); `422` invalid supplied field.

**Frontend notes:** update does not upsert; create the profile first.

## 7. Job Applications

The database unique constraint is `(job_post_id, job_seeker_id)`. New applications begin as `pending`. Applicant identity is always the authenticated user; no applicant ID is accepted by the controller. Employers see only applications for their own jobs. The employer resource exposes applicant `id`, `name`, and `email`, not phone or profile fields.

### Apply to a job

**Method and URL:** `POST /api/job-posts/{jobPost}/applications`

**Purpose:** Submit the authenticated job seeker's application to a publicly visible job.

**Authentication:** Protected; **required role:** `job_seeker`.

**Headers:** `Accept: application/json`, Bearer authorization. No body is required by the action; request fields are ignored.

**Query parameters:** None.

**Request body:** None required. Do not send an applicant identity to impersonate another user; the controller always uses `request.user().id`.

**Example request:**

```http
POST /api/job-posts/25/applications HTTP/1.1
Host: 127.0.0.1:8000
Accept: application/json
Authorization: Bearer <job-seeker-token>
```

**Success:** `201 Created`.

```json
{
  "message": "Application submitted successfully.",
  "application": {
    "id": 81,
    "status": "pending",
    "created_at": "2026-10-10T12:00:00.000000Z",
    "updated_at": "2026-10-10T12:00:00.000000Z"
  }
}
```

Because the create response resource has no relations loaded, it contains no `job_post` or `applicant` object.

**Errors:** `401` unauthenticated; `403` wrong role; `404` missing, closed, or expired job (`Job post not found.`); `409` duplicate application (`You have already applied to this job.`). Database uniqueness races are also converted to 409.

**Frontend notes:** public job visibility is reused for eligibility. Active jobs with null expiry remain eligible. No endpoint to list the job seeker's own applications exists.

### Employer application list

**Method and URL:** `GET /api/employer/applications`

**Purpose:** List applications attached to jobs owned by the authenticated employer.

**Authentication:** Protected; **required role:** `employer`.

**Headers:** `Accept: application/json`, Bearer authorization. No body.

**Query parameters:** `per_page` optional integer 1–100, default 15. Laravel paginator also accepts `page`; the controller does not add explicit page validation.

**Success:** `200 OK`, paginated `JobApplicationResource` collection. This action eager-loads `job_post` `{id,title}` and `applicant` `{id,name,email}`.

```json
{
  "data": [
    {
      "id": 81,
      "status": "pending",
      "created_at": "2026-10-10T12:00:00.000000Z",
      "updated_at": "2026-10-10T12:00:00.000000Z",
      "job_post": {"id": 25, "title": "Backend Engineer"},
      "applicant": {"id": 21, "name": "Jordan Example", "email": "jordan@example.test"}
    }
  ],
  "links": {"first":"...","last":"...","prev":null,"next":null},
  "meta": {"current_page":1,"from":1,"last_page":1,"links":[],"path":"...","per_page":15,"to":1,"total":1}
}
```

**Errors:** `401` guest; `403` non-employer. Applications on jobs belonging to other employers are not included. Invalid `per_page` returns `422`.

**Frontend notes:** only applicant account ID, display name, and email are exposed. Phone, city, headline, bio, and profile image are not part of this response.

### Update application status

**Method and URL:** `PATCH /api/employer/applications/{jobApplication}`

**Purpose:** Change an application status for an application attached to one of the employer's jobs.

**Authentication:** Protected; **required role:** `employer` and owner of the related job.

**Headers:** JSON headers and Bearer authorization.

**Query parameters:** None.

**Request body:**

| Field | Type | Required | Allowed values | Nullable |
|---|---|---:|---|---:|
| `status` | string | Yes | `pending`, `reviewed`, `accepted`, `rejected` | No |

Allowed transitions are enforced: `pending` → `reviewed`, `accepted`, or `rejected`; `reviewed` → `accepted` or `rejected`; `accepted` and `rejected` are terminal. Repeating the same status is not an allowed transition.

**Example request:**

```json
{"status":"reviewed"}
```

**Success:** `200 OK`.

```json
{
  "message": "Application status updated successfully.",
  "application": {
    "id": 81,
    "status": "reviewed",
    "created_at": "2026-10-10T12:00:00.000000Z",
    "updated_at": "2026-10-10T12:05:00.000000Z"
  }
}
```

Relations are not loaded on this response, so nested `job_post` and `applicant` are omitted.

**Errors:** `401` guest; `403` wrong role; `404` missing application or application belonging to another employer's job (`Application not found.`); `409` a valid status value with disallowed transition; `422` missing or unsupported status.

**Frontend notes:** distinguish invalid status (`422`) from invalid transition (`409`). A job seeker cannot access this employer endpoint.

## 8. Admin Categories and Skills

All operations in these domains—including list and detail—require `auth:sanctum` and role `admin`. Resource routes register GET list, POST create, GET item, PUT/PATCH update, DELETE item.

### Job categories

#### List categories

**Method and URL:** `GET /api/job-categories`

**Purpose:** Return all categories. **Authentication/role:** Sanctum, admin. **Headers:** Accept JSON + Bearer. **Query/body:** none.

**Success:** `200 OK`, a raw JSON array (not `{data: [...]}`). Each model includes `id`, `name`, nullable `description`, `created_at`, `updated_at`.

```json
[{"id":4,"name":"Engineering","description":null,"created_at":"2026-10-01T12:00:00.000000Z","updated_at":"2026-10-01T12:00:00.000000Z"}]
```

**Errors:** `401` unauthenticated; `403` non-admin.

#### Show category

**Method and URL:** `GET /api/job-categories/{job_category}`

**Purpose:** Return one raw category model. **Authentication/role:** Sanctum, admin. **Headers:** Accept JSON + Bearer. **Query/body:** none.

**Success:** `200 OK`, one category model as above. **Errors:** `401`, `403`, `404` with `{"message":"Job category not found"}`.

#### Create category

**Method and URL:** `POST /api/job-categories`

**Purpose:** Create a category. **Authentication/role:** Sanctum, admin. **Headers:** JSON + Bearer. **Query:** none.

**Request body:** `name` required string max 255 and unique; `description` optional nullable string.

```json
{"name":"Engineering","description":"Software roles"}
```

**Success:** `201 Created`.

```json
{
  "message": "Job category created successfully",
  "category": {
    "id": 4,
    "name": "Engineering",
    "description": "Software roles",
    "created_at": "2026-10-01T12:00:00.000000Z",
    "updated_at": "2026-10-01T12:00:00.000000Z"
  }
}
```

**Errors:** `401`, `403`, `422` duplicate/invalid/missing fields.

#### Update category

**Method and URL:** `PUT /api/job-categories/{job_category}` or `PATCH /api/job-categories/{job_category}`

**Purpose:** Update category fields. **Authentication/role:** Sanctum, admin. **Headers:** JSON + Bearer. **Query:** none.

**Request body:** `name` is required even for PATCH; string max 255 and unique except current ID. `description` optional, nullable string.

**Success:** `200 OK`.

```json
{
  "message": "Job category updated successfully",
  "category": {
    "id": 4,
    "name": "Software Engineering",
    "description": "Software roles",
    "created_at": "2026-10-01T12:00:00.000000Z",
    "updated_at": "2026-10-10T12:00:00.000000Z"
  }
}
```

**Errors:** `401`, `403`, `404` (`Job category not found`), `422` validation.

#### Delete category

**Method and URL:** `DELETE /api/job-categories/{job_category}`

**Purpose:** Delete a category. **Authentication/role:** Sanctum, admin. **Headers:** Accept JSON + Bearer. No body.

**Success:** `200 OK`, `{"message":"Job category deleted successfully"}`.

**Errors:** `401`, `403`, `404` (`Job category not found`). Database FK behavior for categories used by jobs is set to null on delete.

### Skills

#### List skills

**Method and URL:** `GET /api/skills`

**Purpose:** Return all skills. **Authentication/role:** Sanctum, admin. **Headers:** Accept JSON + Bearer. No query/body.

**Success:** `200 OK`, raw JSON array; each model has `id`, `name`, `created_at`, `updated_at`.

```json
[{"id":3,"name":"PHP","created_at":"2026-10-01T12:00:00.000000Z","updated_at":"2026-10-01T12:00:00.000000Z"}]
```

**Errors:** `401`, `403`.

#### Show skill

**Method and URL:** `GET /api/skills/{skill}`

**Purpose:** Return one raw skill model. **Authentication/role:** Sanctum, admin. **Headers:** Accept JSON + Bearer. No query/body.

**Success:** `200 OK`, one skill model as above. **Errors:** `401`, `403`, `404` with `{"message":"Skill not found"}`.

#### Create skill

**Method and URL:** `POST /api/skills`

**Purpose:** Create a skill. **Authentication/role:** Sanctum, admin. **Headers:** JSON + Bearer. **Query:** none.

**Request body:** `name` required string, maximum 255, unique.

```json
{"name":"Laravel"}
```

**Success:** `201 Created`.

```json
{
  "message": "Skill created successfully",
  "skill": {
    "id": 3,
    "name": "Laravel",
    "created_at": "2026-10-01T12:00:00.000000Z",
    "updated_at": "2026-10-01T12:00:00.000000Z"
  }
}
```

**Errors:** `401`, `403`, `422` missing/invalid/duplicate name.

#### Update skill

**Method and URL:** `PUT /api/skills/{skill}` or `PATCH /api/skills/{skill}`

**Purpose:** Update a skill. **Authentication/role:** Sanctum, admin. **Headers:** JSON + Bearer. **Query:** none.

**Request body:** `name` required string max 255 and unique except current ID; PATCH still requires it.

**Success:** `200 OK`.

```json
{
  "message": "Skill updated successfully",
  "skill": {
    "id": 3,
    "name": "Laravel 12",
    "created_at": "2026-10-01T12:00:00.000000Z",
    "updated_at": "2026-10-10T12:00:00.000000Z"
  }
}
```

**Errors:** `401`, `403`, `404` (`Skill not found`), `422` validation.

#### Delete skill

**Method and URL:** `DELETE /api/skills/{skill}`

**Purpose:** Delete a skill. **Authentication/role:** Sanctum, admin. **Headers:** Accept JSON + Bearer. No body.

**Success:** `200 OK`, `{"message":"Skill deleted successfully"}`.

**Errors:** `401`, `403`, `404` (`Skill not found`).

There are no public/read-only category or skill routes in the current route configuration.

## 9. Health Check

### API health

**Method and URL:** `GET /api/test`

**Purpose:** Small API route health response. **Authentication:** Public. **Headers:** `Accept: application/json`. No query/body.

**Success:** `200 OK`, `{"message":"API is working"}`.

**Errors:** No endpoint-specific errors are defined.

## 10. Frontend Integration Examples

Set a frontend environment variable (example only):

```text
VITE_API_BASE_URL=http://127.0.0.1:8000/api
```

The host/port vary by environment. Vite-prefixed variables are bundled into frontend code and are public; never put backend credentials or secrets in them.

### Shared fetch helper

```js
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? "http://127.0.0.1:8000/api";

export async function apiFetch(path, { token, body, method = "GET" } = {}) {
  const headers = { Accept: "application/json" };
  if (body !== undefined) headers["Content-Type"] = "application/json";
  if (token) headers.Authorization = `Bearer ${token}`;

  const response = await fetch(`${API_BASE_URL}${path}`, {
    method,
    headers,
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const payload = response.status === 204 ? null : await response.json();
  if (!response.ok) {
    const error = new Error(payload?.message ?? `HTTP ${response.status}`);
    error.status = response.status;
    error.payload = payload;
    throw error;
  }
  return payload;
}
```

### 1. Register

```js
const registered = await apiFetch("/register", {
  method: "POST",
  body: {
    name: "Jordan Example",
    email: "jordan@example.test",
    password: "ExamplePass123",
    password_confirmation: "ExamplePass123",
    role: "job_seeker",
  },
});
const token = registered.token;
const role = registered.user.role;
```

### 2. Log in and extract token

```js
const session = await apiFetch("/login", {
  method: "POST",
  body: { email: "jordan@example.test", password: "ExamplePass123" },
});
const token = session.token;
const role = session.user.role;
```

### 3. Send an authenticated request

```js
const myProfile = await apiFetch("/job-seeker-profile", { token });
```

### 4. Load public jobs with filters

```js
const params = new URLSearchParams({
  q: "backend",
  category_id: "4",
  location: "Amman",
  employment_type: "full_time",
  per_page: "10",
  page: "1",
});
const page = await apiFetch(`/job-posts?${params}`);
const jobs = page.data;
const nextPage = page.meta.current_page < page.meta.last_page
  ? page.meta.current_page + 1
  : null;
```

### 5. Load a job detail

```js
const result = await apiFetch("/job-posts/25");
const job = result.data;
```

### 6. Create a job post as an employer

```js
const created = await apiFetch("/job-posts", {
  method: "POST",
  token,
  body: {
    category_id: 4,
    title: "Backend Engineer",
    description: "Build and maintain APIs.",
    contact_email: "hiring@example.test",
    status: "active",
    skills: [3, 7],
  },
});
const jobId = created.job_post.id;
```

### 7. Apply as a job seeker

The request requires no body; applicant identity is derived from the token.

```js
const applied = await apiFetch(`/job-posts/${jobId}/applications`, {
  method: "POST",
  token,
});
```

### 8. Display field validation errors

```js
try {
  await apiFetch("/register", { method: "POST", body: formValues });
} catch (error) {
  if (error.status === 422) {
    const fieldErrors = error.payload.errors ?? {};
    // Example: fieldErrors.email is an array of message strings.
    renderFieldErrors(fieldErrors);
  } else {
    throw error;
  }
}
```

### 9. Handle authentication, authorization, and not found

```js
try {
  return await apiFetch("/employer-profile", { token });
} catch (error) {
  if (error.status === 401) handleSignInRequired();
  else if (error.status === 403) handleRoleDenied();
  else if (error.status === 404) handleMissingOrUnavailableResource();
  else throw error;
}
```

### 10. Log out

```js
await apiFetch("/logout", { method: "POST", token });
// Remove the token from application state after success.
```

## 11. TypeScript Data Contracts

These interfaces model JSON-visible response fields. Timestamps are ISO-8601 strings in examples. Fields omitted by conditional resource loading are marked optional.

```ts
export type UserRole = "employer" | "job_seeker" | "admin";
export type EmploymentType = "full_time" | "part_time" | "remote" | "freelance" | "internship";
export type JobStatus = "active" | "closed";
export type JobApplicationStatus = "pending" | "reviewed" | "accepted" | "rejected";

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  role: UserRole;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface AuthResponse {
  message: string;
  user: AuthUser;
  token: string;
}

export interface JobCategorySummary {
  id: number | null;
  name: string | null;
}
export interface EmployerSummary { id: number; name: string; }
export interface SkillSummary { id: number; name: string; }

export interface JobPost {
  id: number;
  employer_id: number;
  category_id: number | null;
  title: string;
  description: string;
  requirements: string | null;
  location: string | null;
  employment_type: EmploymentType | null;
  salary_min: number | null;
  salary_max: number | null;
  salary_currency: string | null;
  contact_email: string | null;
  contact_phone: string | null;
  google_form_url: string | null;
  status: JobStatus;
  expires_at: string | null;
  created_at: string;
  updated_at: string;
  category?: JobCategorySummary | null;
  employer?: EmployerSummary | null;
  skills?: SkillSummary[];
}

export interface LaravelPage<T> {
  data: T[];
  links: { first: string | null; last: string | null; prev: string | null; next: string | null };
  meta: {
    current_page: number;
    from: number | null;
    last_page: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
    path: string;
    per_page: number;
    to: number | null;
    total: number;
  };
}

export interface EmployerProfile {
  id: number;
  user_id: number;
  employer_type: "individual" | "company" | "organization";
  organization_name: string | null;
  description: string | null;
  logo: string | null;
  address: string | null;
  website: string | null;
  created_at: string;
  updated_at: string;
}
export interface JobSeekerProfile {
  id: number;
  user_id: number;
  full_name: string | null;
  headline: string | null;
  bio: string | null;
  city: string | null;
  profile_image: string | null;
  created_at: string;
  updated_at: string;
}
export interface OwnProfileResponse<T> {
  user: Pick<AuthUser, "id" | "name" | "email" | "phone">;
  profile: T;
}

export interface JobApplication {
  id: number;
  status: JobApplicationStatus;
  created_at: string;
  updated_at: string;
  job_post?: { id: number; title: string };
  applicant?: { id: number; name: string; email: string };
}
export interface JobCategory {
  id: number;
  name: string;
  description: string | null;
  created_at: string;
  updated_at: string;
}
export interface Skill {
  id: number;
  name: string;
  created_at: string;
  updated_at: string;
}
export interface ValidationErrorResponse {
  message: string;
  errors: Record<string, string[]>;
}
```

Notes: `AuthUser.phone` is nullable. Resource nested fields appear only when that relationship is loaded; list jobs load all three relationships, detail jobs load category/employer only, and newly created/status-updated application resources load no relations. Category and skill endpoints return raw models/arrays, not resource `data` wrappers.

## 12. Endpoint Quick Reference

There are 28 registered Laravel route entries. `PUT/PATCH` share one resource route for each update path.

| Method | Exact path | Purpose | Access | Role | Body? | Typical success |
|---|---|---|---|---|---:|---:|
| `POST` | `/api/register` | Register and issue token | Public | None; self-select employer/job_seeker only | Yes | 201 |
| `POST` | `/api/login` | Authenticate and issue token | Public | None | Yes | 200 |
| `POST` | `/api/logout` | Revoke current token | Protected | Any authenticated | No | 200 |
| `GET` | `/api/test` | Health message | Public | None | No | 200 |
| `GET` | `/api/job-posts` | Search/list visible jobs | Public | None | No | 200 |
| `GET` | `/api/job-posts/{jobPost}` | View visible job | Public | None | No | 200 |
| `POST` | `/api/job-posts` | Create job | Protected | Employer | Yes | 201 |
| `PUT` | `/api/job-posts/{jobPost}` | Update owned job | Protected | Employer owner | Yes | 200 |
| `DELETE` | `/api/job-posts/{jobPost}` | Soft-delete owned job | Protected | Employer owner | No | 200 |
| `GET` | `/api/employer-profile` | Read own employer profile | Protected | Employer | No | 200 |
| `POST` | `/api/employer-profile` | Create own employer profile | Protected | Employer | Yes | 201 |
| `PUT` | `/api/employer-profile` | Update own employer profile | Protected | Employer | Yes (may be `{}`) | 200 |
| `GET` | `/api/job-seeker-profile` | Read own job-seeker profile | Protected | Job seeker | No | 200 |
| `POST` | `/api/job-seeker-profile` | Create own job-seeker profile | Protected | Job seeker | Yes (fields optional) | 201 |
| `PUT` | `/api/job-seeker-profile` | Update own job-seeker profile | Protected | Job seeker | Yes (may be `{}`) | 200 |
| `POST` | `/api/job-posts/{jobPost}/applications` | Apply to visible job | Protected | Job seeker | No | 201 |
| `GET` | `/api/employer/applications` | List applications for own jobs | Protected | Employer | No | 200 |
| `PATCH` | `/api/employer/applications/{jobApplication}` | Update application status | Protected | Employer of related job | Yes | 200 |
| `GET` | `/api/job-categories` | List categories | Protected | Admin | No | 200 |
| `POST` | `/api/job-categories` | Create category | Protected | Admin | Yes | 201 |
| `GET` | `/api/job-categories/{job_category}` | Read category | Protected | Admin | No | 200 |
| `PUT/PATCH` | `/api/job-categories/{job_category}` | Update category (`name` required) | Protected | Admin | Yes | 200 |
| `DELETE` | `/api/job-categories/{job_category}` | Delete category | Protected | Admin | No | 200 |
| `GET` | `/api/skills` | List skills | Protected | Admin | No | 200 |
| `POST` | `/api/skills` | Create skill | Protected | Admin | Yes | 201 |
| `GET` | `/api/skills/{skill}` | Read skill | Protected | Admin | No | 200 |
| `PUT/PATCH` | `/api/skills/{skill}` | Update skill (`name` required) | Protected | Admin | Yes | 200 |
| `DELETE` | `/api/skills/{skill}` | Delete skill | Protected | Admin | No | 200 |

## 13. Verification Notes and Limitations

The endpoint list was compared with `php artisan route:list --path=api` (28 route entries). The JSON route listing, controller actions, middleware, resources, models, enums, migrations, and feature assertions were inspected. The project report records a final MySQL suite of 40 passing tests and 267 assertions. The documentation examples are representative source-derived examples, not live responses captured from an HTTP server.

No route exists for password reset, email verification, token refresh, profile deletion, public category/skill browsing, or viewing a job seeker's own application history. No universal response wrapper exists: paginated resources use `data/links/meta`, single `JobPostResource` responses use `data`, while auth, profiles, category/skill resources, create-job, and application actions use their controller-defined shapes.
