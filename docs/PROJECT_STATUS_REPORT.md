# CareerLy Project Status Report

> Audit based on the repository contents reviewed on 2026-10-10. This report records what is present in code, what was exercised, and what remains unverified. No authoritative product requirements document was found in the reviewed project files, so absence from code is not presented as a confirmed contractual requirement.

## 1. Scope and Evidence

CareerLy is currently represented by a Laravel API backend and a minimal Laravel/Vite starter page. Evidence reviewed includes `routes/api.php`, `routes/web.php`, API controllers and middleware, Eloquent models, migrations, `composer.json`, `package.json`, and feature tests.

**بالعربية:**

يعتمد هذا التقرير على ملفات المشروع التي تمت مراجعتها بتاريخ 2026-10-10. يتكوّن CareerLy حاليًا من واجهة خلفية API باستخدام Laravel وصفحة ابتدائية بسيطة من Laravel/Vite. شملت المراجعة المسارات ووحدات التحكم والوسيط والنماذج وترحيلات قاعدة البيانات والاعتماديات والاختبارات. لم يُعثر على وثيقة رسمية لمتطلبات المنتج؛ لذلك لا يُفترض أن كل ميزة غير موجودة في الشيفرة مطلب مؤكد.

## 2. Route Inventory

| Method and path | Access | Implemented behavior | Evidence |
|---|---|---|---|
| `GET /` | Public | Renders the Laravel `welcome` view. | [routes/web.php](../routes/web.php), [welcome.blade.php](../resources/views/welcome.blade.php) |
| `GET /api/test` | Public | Returns a small API health message. | [routes/api.php](../routes/api.php) |
| `POST /api/register`, `POST /api/login` | Public | Validates registration/login data and returns a Sanctum token. Registration allows `employer` or `job_seeker`, defaulting to `job_seeker`. | [AuthController.php](../app/Http/Controllers/Api/AuthController.php) |
| `GET /api/job-posts`, `GET /api/job-posts/{jobPost}` | Public | Lists and shows job posts using a JSON resource. The list currently loads every non-soft-deleted post, without a status or expiry filter. | [JobPostController.php](../app/Http/Controllers/Api/JobPostController.php), [JobPostResource.php](../app/Http/Resources/JobPostResource.php) |
| `POST /api/job-posts`, `PUT /api/job-posts/{jobPost}`, `DELETE /api/job-posts/{jobPost}` | Sanctum-authenticated employer | Creates, updates, and soft-deletes posts. Update/delete check ownership. | [routes/api.php](../routes/api.php), [JobPostController.php](../app/Http/Controllers/Api/JobPostController.php) |
| `GET/POST/PUT/DELETE /api/job-categories` and item routes | Sanctum-authenticated admin | Category CRUD. | [routes/api.php](../routes/api.php), [JobCategoryController.php](../app/Http/Controllers/Api/JobCategoryController.php) |
| `GET/POST/PUT/DELETE /api/skills` and item routes | Sanctum-authenticated admin | Skill CRUD. | [routes/api.php](../routes/api.php), [SkillController.php](../app/Http/Controllers/Api/SkillController.php) |
| `GET /api/employer-profile`, `GET /api/job-seeker-profile` | Sanctum-authenticated; role checked by controller | Reads the authenticated user's matching profile; returns 404 if a profile record does not exist. | [EmployerProfileController.php](../app/Http/Controllers/Api/EmployerProfileController.php), [JobSeekerProfileController.php](../app/Http/Controllers/Api/JobSeekerProfileController.php) |

No logout, profile create/update, job application, saved-job, password-reset, or dedicated search/filter routes were found in the inspected route file.

**بالعربية:**

توفّر المسارات تسجيل المستخدمين والدخول، واستعراض الوظائف، وإدارة إعلانات صاحب العمل، وإدارة التصنيفات والمهارات للمشرف، وقراءة الملف الشخصي للمستخدم. وتوضح القائمة أن نقطة استعراض الوظائف لا تستبعد الإعلانات المغلقة أو المنتهية حاليًا. لم تظهر مسارات لتسجيل الخروج أو إنشاء/تعديل الملفات الشخصية أو التقديم للوظائف أو حفظها أو إعادة تعيين كلمة المرور أو البحث والتصفية المخصصين.

## 3. Data Model and Schema

- `users` stores account identity plus `phone` and a role (`employer`, `job_seeker`, or `admin`); the model casts role to `UserRole` and uses Sanctum API tokens.
- `employer_profiles` and `job_seeker_profiles` store role-specific profile fields and each has a unique user relationship.
- `job_posts` stores employer/category references, description, requirements, location, employment type, optional salary/contact details, status, expiry, timestamps, and soft deletion.
- `job_categories` and `skills` are lookup tables. `job_post_skills` links posts and skills with a composite primary key.
- Migrations also create Laravel's standard users, cache, queue, and Sanctum personal-access-token tables.
- Database configuration supports SQLite and MySQL. `config/database.php` defaults to SQLite when `DB_CONNECTION` is unset, while `.env.example` selects MySQL and the database name `careerly`. The configured driver and live connection depend on the local `.env` and installed database service.

Relevant evidence: [User.php](../app/Models/User.php), [JobPost.php](../app/Models/JobPost.php), [EmployerProfile.php](../app/Models/EmployerProfile.php), [JobSeekerProfile.php](../app/Models/JobSeekerProfile.php), and [database/migrations](../database/migrations).

**بالعربية:**

تتضمن قاعدة البيانات حسابات بأدوار مختلفة، وملفات منفصلة لأصحاب العمل والباحثين عن عمل، وإعلانات وظائف مرتبطة بالتصنيفات والمهارات. تدعم الإعلانات الحذف المنطقي وتحتوي حقولًا للحالة وتاريخ الانتهاء. يدعم الإعداد SQLite وMySQL؛ ويعتمد الاتصال الفعلي على ملف `.env` والخدمة المثبتة محليًا، لذلك لا يعني وجود إعداد MySQL أن الاتصال به قد تم التحقق منه.

## 4. Implementation Status

| Area | Evidence-based status | Remaining work |
|---|---|---|
| Account registration/login | Implemented in the API; token creation is present. | Add and test token revocation/logout and verify full account flows. |
| Role access and job ownership | Implemented with `auth:sanctum`, role middleware, and ownership checks. | Expand coverage to all edge cases and production authentication configuration. |
| Job post CRUD | Implemented for employers, with input validation and skill synchronization. | Decide and implement status/expiry visibility rules; consider pagination/search if required. Update uses required fields, so it is not a partial update. |
| Categories and skills | Admin-only CRUD controllers/routes exist. | Verify deletion behavior when related records exist and add broader tests. |
| Employer/job-seeker profiles | Data models/tables and authenticated read endpoints exist. | Profile creation and update flows are absent; registration does not create a matching profile row. |
| Frontend | Laravel welcome page and Vite CSS/JS entry points exist. | No CareerLy-specific user-facing application or API integration was found. |
| Automated tests | API authorization feature tests cover public access, roles, ownership, validation, and CRUD. | Broaden tests beyond authorization/job-post management and run the complete suite in the target environment. |

**بالعربية:**

توجد وظائف أساسية للتسجيل والدخول وإدارة إعلانات الوظائف والتصنيفات والمهارات، مع فحوص للصلاحيات والملكية. أما الملفات الشخصية فتُقرأ فقط ولا تُنشأ أثناء التسجيل، والواجهة الحالية ليست واجهة CareerLy مكتملة. كما يلزم تحديد قواعد ظهور الوظائف واختبار بقية المسارات والحالات قبل اعتبار هذه الوحدات مكتملة.

## 5. Test and Build Evidence

- The focused `ApiAuthorizationTest.php` feature suite passed: **11 tests, 0 failures**.
- This is not evidence that the entire test suite, MySQL setup, or deployment configuration passes.
- The Vite build was not run because that command was skipped. No frontend build result is claimed.
- Existing `ExampleTest` files are Laravel starter-level checks and are not evidence of broader CareerLy workflows.

**بالعربية:**

نجحت مجموعة الاختبارات المركزة `ApiAuthorizationTest.php` بعدد 11 اختبارًا دون إخفاق. لم يُشغّل كامل الاختبارات، ولم يتم التحقق من MySQL أو إعداد النشر. كما لم يُنفّذ بناء Vite، لذلك لا توجد نتيجة مؤكدة للبناء الأمامي.

## 6. Confirmed Problems and Unverified Risks

### Confirmed from the current code

- The public job-post listing does not filter by `status` or `expires_at`; closed or expired records may be included unless filtered elsewhere. No such filtering was found in the controller/resource path reviewed.
- Registration creates the user and token but does not create an `EmployerProfile` or `JobSeekerProfile`. Profile endpoints return 404 when the related row is absent, and no profile write routes were found.
- The shipped web route renders the Laravel welcome page; there is no CareerLy-specific frontend workflow in the inspected view/JS entry point.

### Unverified risks (not confirmed failures)

- Whether MySQL migrations and requests work in the intended local/production environment was not verified; only repository configuration was inspected.
- The frontend build and browser behavior were not verified.
- Product decisions such as which roles may see closed/expired jobs, whether applications are in scope, and required search/profile features cannot be confirmed without an authoritative requirements document.
- Only the focused API authorization suite was run. Full-suite, performance, security, and deployment checks remain outstanding.

**بالعربية:**

المشكلات المؤكدة من الشيفرة هي عدم تصفية قائمة الوظائف حسب الحالة أو تاريخ الانتهاء، وعدم إنشاء ملف شخصي عند التسجيل مع غياب مسارات تعديله، وبقاء الصفحة الرئيسية على قالب Laravel الافتراضي. أما عمل MySQL والبناء الأمامي ومتطلبات إظهار الوظائف أو التقديم فهي أمور غير متحقق منها، وليست إخفاقات مؤكدة.

## 7. Recommended Technical Actions

1. Confirm the product requirements and define job visibility rules, profile fields, and whether applications/search are in scope.
2. Implement and test job status/expiry filtering according to those rules.
3. Add profile creation/update workflows and decide whether registration should create an empty role-specific profile.
4. Build and integrate the CareerLy frontend with the existing API, or document the intended API-only scope.
5. Add missing workflow tests, then run the complete test suite and frontend build.
6. Verify migrations and runtime behavior against the intended MySQL environment and review production settings before release.

**بالعربية:**

ابدأ بتثبيت متطلبات المنتج وقواعد ظهور الوظائف، ثم عالج التصفية والملفات الشخصية. بعد ذلك أنشئ الواجهة المطلوبة أو وثّق أن المشروع API فقط، وأضف اختبارات التدفقات الناقصة. أخيرًا تحقّق من الاختبارات والبناء والترحيلات على بيئة MySQL المستهدفة قبل النشر.

## 8. Run Instructions and Verification Limits

The repository provides `composer setup`, `composer dev`, `composer test`, `npm run build`, and `npm run dev` scripts. The setup script installs Composer/npm dependencies, creates `.env` if absent, generates an app key, runs migrations, and builds assets. **These setup/dev/build sequences were not executed as part of this review.** Configure `.env` and a reachable database first; `.env.example` currently selects MySQL (`careerly`, user `root`, blank password), which may need local adjustment.

The focused feature tests were verified in this workspace through the test runner (11 passed). The Vite build, full `composer test` suite, fresh database migration, and long-running `composer dev` process were not verified.

**بالعربية:**

توفّر ملفات Composer أوامر الإعداد والتطوير والاختبار، كما يوفر npm أوامر البناء والتطوير. لم تُنفّذ أوامر الإعداد أو تشغيل الخادم أو البناء خلال هذه المراجعة؛ لذا يجب ضبط `.env` وقاعدة البيانات أولًا. التحقق المؤكد الوحيد هنا هو نجاح مجموعة اختبارات API المركزة (11 اختبارًا)، بينما البناء ومجموعة الاختبارات الكاملة والترحيل على قاعدة جديدة وتشغيل التطوير لم تُختبر.
    