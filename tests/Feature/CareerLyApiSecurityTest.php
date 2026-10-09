<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\EmployerProfile;
use App\Models\JobCategory;
use App\Models\JobApplication;
use App\Models\JobPost;
use App\Models\JobSeekerProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CareerLyApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_routes_require_authentication(): void
    {
        $requests = [
            fn() => $this->getJson('/api/employer-profile'),
            fn() => $this->postJson('/api/employer-profile', []),
            fn() => $this->putJson('/api/employer-profile', []),
            fn() => $this->getJson('/api/job-seeker-profile'),
            fn() => $this->postJson('/api/job-seeker-profile', []),
            fn() => $this->putJson('/api/job-seeker-profile', []),
        ];

        foreach ($requests as $request) {
            $request()->assertUnauthorized();
        }
    }

    public function test_profile_reads_reject_the_wrong_role(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::JobSeeker]));
        $this->getJson('/api/employer-profile')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employer]));
        $this->getJson('/api/job-seeker-profile')->assertForbidden();
    }

    public function test_users_cannot_create_duplicate_role_profiles(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $employer->employerProfile()->create(['employer_type' => 'company']);

        $jobSeeker = User::factory()->create(['role' => UserRole::JobSeeker]);
        $jobSeeker->jobSeekerProfile()->create(['full_name' => 'Existing Candidate']);

        Sanctum::actingAs($employer);
        $this->postJson('/api/employer-profile', ['employer_type' => 'company'])->assertStatus(409);

        Sanctum::actingAs($jobSeeker);
        $this->postJson('/api/job-seeker-profile', ['full_name' => 'Second Candidate'])->assertStatus(409);
    }

    public function test_profile_updates_for_users_without_profiles_return_404(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employer]));
        $this->putJson('/api/employer-profile', ['organization_name' => 'New Name'])->assertNotFound();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::JobSeeker]));
        $this->putJson('/api/job-seeker-profile', ['full_name' => 'New Name'])->assertNotFound();
    }

    public function test_employer_profile_creation_validates_type_and_website(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employer]));

        $this->postJson('/api/employer-profile', ['employer_type' => 'invalid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employer_type');

        $this->postJson('/api/employer-profile', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('employer_type');

        $this->postJson('/api/employer-profile', [
            'employer_type' => 'company',
            'website' => 'abc',
        ])->assertUnprocessable()->assertJsonValidationErrors('website');
    }

    public function test_job_seeker_full_name_rejects_a_non_string_value(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::JobSeeker]));

        $this->postJson('/api/job-seeker-profile', ['full_name' => 123])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('full_name');
    }

    public function test_empty_profile_updates_return_success_without_changing_data(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $employerProfile = $employer->employerProfile()->create([
            'employer_type' => 'company',
            'organization_name' => 'CareerLy Test Co',
        ]);

        $jobSeeker = User::factory()->create(['role' => UserRole::JobSeeker]);
        $jobSeekerProfile = $jobSeeker->jobSeekerProfile()->create([
            'full_name' => 'Test Candidate',
            'city' => 'Amman',
        ]);

        Sanctum::actingAs($employer);
        $this->putEmptyJson('/api/employer-profile')->assertOk();

        Sanctum::actingAs($jobSeeker);
        $this->putEmptyJson('/api/job-seeker-profile')->assertOk();

        $this->assertDatabaseHas('employer_profiles', [
            'id' => $employerProfile->id,
            'organization_name' => 'CareerLy Test Co',
        ]);
        $this->assertDatabaseHas('job_seeker_profiles', [
            'id' => $jobSeekerProfile->id,
            'full_name' => 'Test Candidate',
            'city' => 'Amman',
        ]);
    }

    public function test_profile_payload_cannot_change_role_or_transfer_profile_ownership(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $otherEmployer = User::factory()->create(['role' => UserRole::Employer]);
        Sanctum::actingAs($employer);

        $createdEmployerProfile = $this->postJson('/api/employer-profile', [
            'employer_type' => 'company',
            'user_id' => $otherEmployer->id,
            'role' => 'admin',
        ])->assertCreated();

        $employerProfileId = $createdEmployerProfile->json('profile.id');
        Sanctum::actingAs($employer->fresh());
        $this->putJson('/api/employer-profile', [
            'organization_name' => 'Owned by authenticated employer',
            'user_id' => $otherEmployer->id,
            'role' => 'admin',
        ])->assertOk();

        $this->assertSame(UserRole::Employer, $employer->fresh()->role);
        $this->assertDatabaseHas('employer_profiles', [
            'id' => $employerProfileId,
            'user_id' => $employer->id,
        ]);
        $this->assertDatabaseMissing('employer_profiles', ['user_id' => $otherEmployer->id]);

        $jobSeeker = User::factory()->create(['role' => UserRole::JobSeeker]);
        $otherJobSeeker = User::factory()->create(['role' => UserRole::JobSeeker]);
        Sanctum::actingAs($jobSeeker);

        $createdJobSeekerProfile = $this->postJson('/api/job-seeker-profile', [
            'full_name' => 'Authenticated Candidate',
            'user_id' => $otherJobSeeker->id,
            'role' => 'admin',
        ])->assertCreated();

        $jobSeekerProfileId = $createdJobSeekerProfile->json('profile.id');
        Sanctum::actingAs($jobSeeker->fresh());
        $this->putJson('/api/job-seeker-profile', [
            'full_name' => 'Updated Candidate',
            'user_id' => $otherJobSeeker->id,
            'role' => 'admin',
        ])->assertOk();

        $this->assertSame(UserRole::JobSeeker, $jobSeeker->fresh()->role);
        $this->assertDatabaseHas('job_seeker_profiles', [
            'id' => $jobSeekerProfileId,
            'user_id' => $jobSeeker->id,
            'full_name' => 'Updated Candidate',
        ]);
        $this->assertDatabaseMissing('job_seeker_profiles', ['user_id' => $otherJobSeeker->id]);
    }

    public function test_registration_creates_a_user_and_does_not_expose_sensitive_user_fields(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'New Candidate',
            'email' => 'new-candidate@example.test',
            'password' => 'ValidPassword123',
            'password_confirmation' => 'ValidPassword123',
            'role' => 'job_seeker',
        ]);

        $response->assertCreated()
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('users', [
            'email' => 'new-candidate@example.test',
            'role' => 'job_seeker',
        ]);
        $this->assertDatabaseMissing('job_seeker_profiles', [
            'user_id' => $response->json('user.id'),
        ]);
    }

    public function test_registration_rejects_duplicate_email_mismatched_password_and_admin_role(): void
    {
        User::factory()->create(['email' => 'existing@example.test']);

        $this->postJson('/api/register', [
            'name' => 'Duplicate User',
            'email' => 'existing@example.test',
            'password' => 'ValidPassword123',
            'password_confirmation' => 'ValidPassword123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson('/api/register', [
            'name' => 'Mismatched Password',
            'email' => 'mismatch@example.test',
            'password' => 'ValidPassword123',
            'password_confirmation' => 'DifferentPassword123',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->postJson('/api/register', [
            'name' => 'Admin Attempt',
            'email' => 'admin-attempt@example.test',
            'password' => 'ValidPassword123',
            'password_confirmation' => 'ValidPassword123',
            'role' => 'admin',
        ])->assertUnprocessable()->assertJsonValidationErrors('role');
    }

    public function test_login_rejects_incorrect_password_and_unknown_email(): void
    {
        User::factory()->create([
            'email' => 'login-user@example.test',
            'password' => 'CorrectPassword123',
        ]);

        $this->postJson('/api/login', [
            'email' => 'login-user@example.test',
            'password' => 'CorrectPassword123',
        ])->assertOk()
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.remember_token');

        $this->postJson('/api/login', [
            'email' => 'login-user@example.test',
            'password' => 'IncorrectPassword123',
        ])->assertUnauthorized();

        $this->postJson('/api/login', [
            'email' => 'unknown@example.test',
            'password' => 'CorrectPassword123',
        ])->assertUnauthorized();
    }

    public function test_logout_revokes_only_the_current_bearer_token(): void
    {
        $user = User::factory()->create(['role' => UserRole::JobSeeker]);
        $currentToken = $user->createToken('current-device');
        $otherToken = $user->createToken('other-device');

        $this->withToken($currentToken->plainTextToken)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $currentToken->accessToken->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);

        $this->app['auth']->forgetGuards();
        $this->withToken($currentToken->plainTextToken)
            ->getJson('/api/job-seeker-profile')
            ->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken->plainTextToken)
            ->getJson('/api/job-seeker-profile')
            ->assertNotFound();
    }

    private function putEmptyJson(string $uri)
    {
        return $this->call(
            'PUT',
            $uri,
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            '{}'
        );
    }
    public function test_job_post_creation_requires_employer_role_and_required_fields(): void
    {
        $category = JobCategory::create(['name' => 'Audit Engineering']);
        $data = $this->validJobPostData($category);

        $this->postJson('/api/job-posts', $data)->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::JobSeeker]));
        $this->postJson('/api/job-posts', $data)->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employer]));
        $this->postJson('/api/job-posts', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'title', 'description', 'status']);
    }

    public function test_job_post_update_and_delete_enforce_ownership_without_mutation(): void
    {
        $category = JobCategory::create(['name' => 'Audit Ownership']);
        $owner = User::factory()->create(['role' => UserRole::Employer]);
        $post = $this->createJobPost($owner, $category);
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employer]));

        $this->putJson("/api/job-posts/{$post->id}", $this->validJobPostData($category, [
            'title' => 'Unauthorized title',
        ]))->assertForbidden();
        $this->deleteJson("/api/job-posts/{$post->id}")->assertForbidden();

        $this->assertDatabaseHas('job_posts', [
            'id' => $post->id,
            'employer_id' => $owner->id,
            'title' => 'Backend Engineer',
            'deleted_at' => null,
        ]);
    }

    public function test_nonexistent_job_posts_return_404(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employer]));

        $this->getJson('/api/job-posts/999999')->assertNotFound();
        $this->putJson('/api/job-posts/999999', [])->assertNotFound();
        $this->deleteJson('/api/job-posts/999999')->assertNotFound();
    }

    public function test_job_post_validation_rejects_missing_contact_invalid_category_and_employment_type(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employer]));
        $category = JobCategory::create(['name' => 'Audit Validation']);

        $this->postJson('/api/job-posts', $this->validJobPostData($category, [
            'contact_email' => null,
            'contact_phone' => null,
            'google_form_url' => null,
        ]))->assertUnprocessable()->assertJsonValidationErrors('contact_email');

        $this->postJson('/api/job-posts', $this->validJobPostData($category, [
            'category_id' => 999999,
        ]))->assertUnprocessable()->assertJsonValidationErrors('category_id');

        $this->postJson('/api/job-posts', $this->validJobPostData($category, [
            'employment_type' => 'unrecognized',
        ]))->assertUnprocessable()->assertJsonValidationErrors('employment_type');
    }

    public function test_public_job_post_endpoints_only_show_active_and_unexpired_posts(): void
    {
        $category = JobCategory::create(['name' => 'Audit Visibility']);
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $closedPost = $this->createJobPost($employer, $category, ['status' => 'closed']);
        $expiredPost = $this->createJobPost($employer, $category, [
            'expires_at' => now()->subDay(),
        ]);
        $activePost = $this->createJobPost($employer, $category, [
            'expires_at' => now()->addDay(),
        ]);
        $nonExpiringPost = $this->createJobPost($employer, $category, [
            'expires_at' => null,
        ]);

        $listResponse = $this->getJson('/api/job-posts')->assertOk();
        $listedIds = collect($listResponse->json('data'))->pluck('id')->all();
        $this->assertContains($activePost->id, $listedIds);
        $this->assertContains($nonExpiringPost->id, $listedIds);
        $this->assertNotContains($closedPost->id, $listedIds);
        $this->assertNotContains($expiredPost->id, $listedIds);

        $this->getJson("/api/job-posts/{$activePost->id}")->assertOk();
        $this->getJson("/api/job-posts/{$nonExpiringPost->id}")->assertOk();
        $this->getJson("/api/job-posts/{$closedPost->id}")->assertNotFound();
        $this->getJson("/api/job-posts/{$expiredPost->id}")->assertNotFound();
    }

    public function test_public_job_listing_filters_and_paginates_visible_posts(): void
    {
        $engineering = JobCategory::create(['name' => 'Search Engineering']);
        $design = JobCategory::create(['name' => 'Search Design']);
        $employer = User::factory()->create(['role' => UserRole::Employer]);

        $ammanEngineer = $this->createJobPost($employer, $engineering, [
            'title' => 'Backend Engineer',
            'description' => 'Build APIs',
            'location' => 'Amman',
            'employment_type' => 'full_time',
            'expires_at' => now()->addDays(4),
        ]);
        $remoteDesigner = $this->createJobPost($employer, $design, [
            'title' => 'Product Designer',
            'description' => 'Design interfaces',
            'location' => 'Remote',
            'employment_type' => 'remote',
            'expires_at' => null,
        ]);
        $londonEngineer = $this->createJobPost($employer, $engineering, [
            'title' => 'Platform Engineer',
            'description' => 'Operate services',
            'location' => 'London',
            'employment_type' => 'full_time',
            'expires_at' => now()->addDays(4),
        ]);
        $this->createJobPost($employer, $engineering, [
            'title' => 'Closed Engineer',
            'status' => 'closed',
        ]);
        $this->createJobPost($employer, $engineering, [
            'title' => 'Expired Engineer',
            'expires_at' => now()->subDay(),
        ]);

        $keywordIds = collect($this->getJson('/api/job-posts?q=Engineer')->assertOk()->json('data'))
            ->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$ammanEngineer->id, $londonEngineer->id], $keywordIds);

        $categoryIds = collect($this->getJson("/api/job-posts?category_id={$engineering->id}")->assertOk()->json('data'))
            ->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$ammanEngineer->id, $londonEngineer->id], $categoryIds);

        $locationIds = collect($this->getJson('/api/job-posts?location=Amman')->assertOk()->json('data'))
            ->pluck('id')->all();
        $this->assertSame([$ammanEngineer->id], $locationIds);

        $employmentIds = collect($this->getJson('/api/job-posts?employment_type=remote')->assertOk()->json('data'))
            ->pluck('id')->all();
        $this->assertSame([$remoteDesigner->id], $employmentIds);

        $combinedIds = collect($this->getJson(
            "/api/job-posts?q=Engineer&category_id={$engineering->id}&location=Amman&employment_type=full_time"
        )->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$ammanEngineer->id], $combinedIds);

        $paginated = $this->getJson('/api/job-posts?per_page=1&page=2')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta']);
        $this->assertSame(2, $paginated->json('meta.current_page'));
        $this->assertSame(1, $paginated->json('meta.per_page'));
        $this->assertSame(3, $paginated->json('meta.total'));
        $this->assertCount(1, $paginated->json('data'));

        $this->getJson('/api/job-posts?q=no-matching-role')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_public_job_listing_rejects_invalid_search_parameters(): void
    {
        $this->getJson('/api/job-posts?per_page=101')->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
        $this->getJson('/api/job-posts?employment_type=teleport')->assertUnprocessable()
            ->assertJsonValidationErrors('employment_type');
        $this->getJson('/api/job-posts?category_id=999999')->assertUnprocessable()
            ->assertJsonValidationErrors('category_id');
    }

    public function test_only_job_seekers_can_apply_and_client_cannot_choose_applicant(): void
    {
        $category = JobCategory::create(['name' => 'Applications Access']);
        $post = $this->createJobPost(
            User::factory()->create(['role' => UserRole::Employer]),
            $category
        );
        $jobSeeker = User::factory()->create(['role' => UserRole::JobSeeker]);
        $otherJobSeeker = User::factory()->create(['role' => UserRole::JobSeeker]);
        $uri = "/api/job-posts/{$post->id}/applications";

        $this->postJson($uri)->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employer]));
        $this->postJson($uri)->assertForbidden();

        Sanctum::actingAs($jobSeeker);
        $this->postJson($uri, ['job_seeker_id' => $otherJobSeeker->id])
            ->assertCreated()
            ->assertJsonPath('application.status', 'pending');

        $this->assertDatabaseHas('job_applications', [
            'job_post_id' => $post->id,
            'job_seeker_id' => $jobSeeker->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseMissing('job_applications', [
            'job_post_id' => $post->id,
            'job_seeker_id' => $otherJobSeeker->id,
        ]);

        $this->postJson($uri)->assertStatus(409);
        $this->assertDatabaseCount('job_applications', 1);
    }

    public function test_job_application_database_constraint_prevents_duplicate_applications(): void
    {
        $category = JobCategory::create(['name' => 'Applications Unique']);
        $post = $this->createJobPost(
            User::factory()->create(['role' => UserRole::Employer]),
            $category
        );
        $jobSeeker = User::factory()->create(['role' => UserRole::JobSeeker]);

        JobApplication::create([
            'job_post_id' => $post->id,
            'job_seeker_id' => $jobSeeker->id,
            'status' => 'pending',
        ]);

        $this->expectException(QueryException::class);
        JobApplication::create([
            'job_post_id' => $post->id,
            'job_seeker_id' => $jobSeeker->id,
            'status' => 'pending',
        ]);
    }

    public function test_mysql_application_schema_has_expected_unique_index_defaults_and_foreign_keys(): void
    {
        $schema = config('database.connections.mysql_testing.database');
        $uniqueIndex = DB::selectOne(
            "SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'job_applications' AND NON_UNIQUE = 0
             GROUP BY INDEX_NAME
             HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') = ?",
            [$schema, 'job_post_id,job_seeker_id']
        );
        $this->assertNotNull($uniqueIndex);

        $statusColumn = DB::selectOne(
            "SELECT COLUMN_TYPE, COLUMN_DEFAULT, IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'job_applications' AND COLUMN_NAME = 'status'",
            [$schema]
        );
        $this->assertSame('varchar(255)', $statusColumn->COLUMN_TYPE);
        $this->assertSame('pending', $statusColumn->COLUMN_DEFAULT);
        $this->assertSame('NO', $statusColumn->IS_NULLABLE);

        $expiryColumn = DB::selectOne(
            "SELECT COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'job_posts' AND COLUMN_NAME = 'expires_at'",
            [$schema]
        );
        $this->assertSame('timestamp', $expiryColumn->COLUMN_TYPE);
        $this->assertSame('YES', $expiryColumn->IS_NULLABLE);

        $foreignKeys = DB::select(
            "SELECT kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, constraints_info.DELETE_RULE
                         FROM information_schema.KEY_COLUMN_USAGE AS kcu
             JOIN information_schema.REFERENTIAL_CONSTRAINTS AS constraints_info
                             ON constraints_info.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                            AND constraints_info.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
                         WHERE kcu.TABLE_SCHEMA = ? AND kcu.TABLE_NAME = 'job_applications'
                         ORDER BY kcu.COLUMN_NAME",
            [$schema]
        );
        $this->assertCount(2, $foreignKeys);
        $this->assertSame('job_posts', $foreignKeys[0]->REFERENCED_TABLE_NAME);
        $this->assertSame('CASCADE', $foreignKeys[0]->DELETE_RULE);
        $this->assertSame('users', $foreignKeys[1]->REFERENCED_TABLE_NAME);
        $this->assertSame('CASCADE', $foreignKeys[1]->DELETE_RULE);
    }

    public function test_mysql_job_application_foreign_keys_cascade_for_test_records(): void
    {
        $category = JobCategory::create(['name' => 'MySQL Application Cascades']);
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $jobSeeker = User::factory()->create(['role' => UserRole::JobSeeker]);
        $firstPost = $this->createJobPost($employer, $category);
        $firstApplication = JobApplication::create([
            'job_post_id' => $firstPost->id,
            'job_seeker_id' => $jobSeeker->id,
        ]);

        DB::table('job_posts')->where('id', $firstPost->id)->delete();
        $this->assertDatabaseMissing('job_applications', ['id' => $firstApplication->id]);

        $secondPost = $this->createJobPost($employer, $category, ['title' => 'Second Cascade Test']);
        $secondApplication = JobApplication::create([
            'job_post_id' => $secondPost->id,
            'job_seeker_id' => $jobSeeker->id,
        ]);

        $jobSeeker->delete();
        $this->assertDatabaseMissing('job_applications', ['id' => $secondApplication->id]);
    }

    public function test_closed_and_expired_jobs_reject_applications(): void
    {
        $category = JobCategory::create(['name' => 'Applications Eligibility']);
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $closedPost = $this->createJobPost($employer, $category, ['status' => 'closed']);
        $expiredPost = $this->createJobPost($employer, $category, [
            'expires_at' => now()->subDay(),
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::JobSeeker]));
        $this->postJson("/api/job-posts/{$closedPost->id}/applications")->assertNotFound();
        $this->postJson("/api/job-posts/{$expiredPost->id}/applications")->assertNotFound();
        $this->assertDatabaseCount('job_applications', 0);
    }

    public function test_employers_can_view_only_owned_applications_and_update_allowed_statuses(): void
    {
        $category = JobCategory::create(['name' => 'Applications Privacy']);
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $otherEmployer = User::factory()->create(['role' => UserRole::Employer]);
        $ownedPost = $this->createJobPost($employer, $category);
        $otherPost = $this->createJobPost($otherEmployer, $category, ['title' => 'Other Employer Role']);
        $applicant = User::factory()->create([
            'role' => UserRole::JobSeeker,
            'name' => 'Private Candidate',
            'email' => 'private-candidate@example.test',
            'phone' => '555-0100',
        ]);
        $applicant->jobSeekerProfile()->create([
            'full_name' => 'Private Candidate Profile',
            'headline' => 'Confidential headline',
            'city' => 'Confidential City',
        ]);
        $ownedApplication = JobApplication::create([
            'job_post_id' => $ownedPost->id,
            'job_seeker_id' => $applicant->id,
            'status' => 'pending',
        ]);
        $otherApplication = JobApplication::create([
            'job_post_id' => $otherPost->id,
            'job_seeker_id' => User::factory()->create(['role' => UserRole::JobSeeker])->id,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employer);
        $response = $this->getJson('/api/employer/applications?per_page=1')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('data.0.applicant.name', 'Private Candidate')
            ->assertJsonPath('data.0.applicant.email', 'private-candidate@example.test')
            ->assertJsonPath('meta.total', 1);
        $this->assertSame([$ownedApplication->id], collect($response->json('data'))->pluck('id')->all());
        $response->assertJsonMissingPath('data.0.applicant.phone')
            ->assertJsonMissingPath('data.0.applicant.city')
            ->assertJsonMissingPath('data.0.applicant.headline');

        $this->patchJson("/api/employer/applications/{$ownedApplication->id}", ['status' => 'reviewed'])
            ->assertOk()
            ->assertJsonPath('application.status', 'reviewed');
        $this->patchJson("/api/employer/applications/{$otherApplication->id}", ['status' => 'reviewed'])
            ->assertNotFound();

        Sanctum::actingAs($applicant);
        $this->getJson('/api/employer/applications')->assertForbidden();
        $this->patchJson("/api/employer/applications/{$ownedApplication->id}", ['status' => 'accepted'])
            ->assertForbidden();
    }

    public function test_job_application_status_validation_and_transitions_are_enforced(): void
    {
        $category = JobCategory::create(['name' => 'Applications Status']);
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $post = $this->createJobPost($employer, $category);
        $createApplication = function (string $status) use ($post): JobApplication {
            return JobApplication::create([
                'job_post_id' => $post->id,
                'job_seeker_id' => User::factory()->create(['role' => UserRole::JobSeeker])->id,
                'status' => $status,
            ]);
        };
        $pendingToReviewed = $createApplication('pending');
        $pendingToAccepted = $createApplication('pending');
        $pendingToRejected = $createApplication('pending');
        $reviewedToAccepted = $createApplication('reviewed');
        $reviewedToRejected = $createApplication('reviewed');
        $acceptedTerminal = $createApplication('accepted');
        $rejectedTerminal = $createApplication('rejected');
        Sanctum::actingAs($employer);

        $this->patchJson("/api/employer/applications/{$pendingToReviewed->id}", ['status' => 'unknown'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->patchJson("/api/employer/applications/{$pendingToReviewed->id}", ['status' => 'reviewed'])
            ->assertOk()
            ->assertJsonPath('application.status', 'reviewed');
        $this->patchJson("/api/employer/applications/{$pendingToReviewed->id}", ['status' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('application.status', 'accepted');

        foreach ([
            [$pendingToAccepted, 'accepted'],
            [$pendingToRejected, 'rejected'],
            [$reviewedToAccepted, 'accepted'],
            [$reviewedToRejected, 'rejected'],
        ] as [$application, $nextStatus]) {
            $this->patchJson("/api/employer/applications/{$application->id}", ['status' => $nextStatus])
                ->assertOk()
                ->assertJsonPath('application.status', $nextStatus);
        }

        $this->patchJson("/api/employer/applications/{$acceptedTerminal->id}", ['status' => 'rejected'])
            ->assertStatus(409);
        $this->patchJson("/api/employer/applications/{$rejectedTerminal->id}", ['status' => 'accepted'])
            ->assertStatus(409);
        $this->patchJson("/api/employer/applications/{$reviewedToAccepted->id}", ['status' => 'pending'])
            ->assertStatus(409);

        $this->assertDatabaseHas('job_applications', [
            'id' => $acceptedTerminal->id,
            'status' => 'accepted',
        ]);
    }

    public function test_admin_only_category_and_skill_routes_allow_admin_create(): void
    {
        $this->getJson('/api/job-categories')->assertUnauthorized();
        $this->getJson('/api/skills')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employer]));
        $this->postJson('/api/job-categories', ['name' => 'Unauthorized Category'])->assertForbidden();
        $this->postJson('/api/skills', ['name' => 'Unauthorized Skill'])->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->postJson('/api/job-categories', ['name' => 'Authorized Category'])->assertCreated();
        $this->postJson('/api/skills', ['name' => 'Authorized Skill'])->assertCreated();
    }

    private function createJobPost(User $employer, JobCategory $category, array $overrides = []): JobPost
    {
        return JobPost::create([
            'employer_id' => $employer->id,
            ...$this->validJobPostData($category, $overrides),
        ]);
    }

    private function validJobPostData(JobCategory $category, array $overrides = []): array
    {
        return array_replace([
            'category_id' => $category->id,
            'title' => 'Backend Engineer',
            'description' => 'Build and maintain APIs.',
            'contact_email' => 'hiring@example.test',
            'status' => 'active',
        ], $overrides);
    }
}