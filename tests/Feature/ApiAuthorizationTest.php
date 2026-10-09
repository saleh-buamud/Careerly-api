<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\JobCategory;
use App\Models\JobPost;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_list_and_view_job_posts(): void
    {
        $category = JobCategory::create(['name' => 'Engineering']);
        $post = $this->createJobPost(
            User::factory()->create(['role' => UserRole::Employer]),
            $category
        );

        $this->getJson('/api/job-posts')
            ->assertOk()
            ->assertJsonPath('data.0.id', $post->id);

        $this->getJson("/api/job-posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $post->id);
    }

    public function test_guests_cannot_create_update_or_delete_job_posts(): void
    {
        $category = JobCategory::create(['name' => 'Engineering']);
        $post = $this->createJobPost(
            User::factory()->create(['role' => UserRole::Employer]),
            $category
        );

        $this->postJson('/api/job-posts', $this->validJobPostData($category))->assertUnauthorized();
        $this->putJson("/api/job-posts/{$post->id}", $this->validJobPostData($category))->assertUnauthorized();
        $this->deleteJson("/api/job-posts/{$post->id}")->assertUnauthorized();
    }

    public function test_job_seekers_cannot_create_update_or_delete_job_posts(): void
    {
        $category = JobCategory::create(['name' => 'Engineering']);
        $post = $this->createJobPost(
            User::factory()->create(['role' => UserRole::Employer]),
            $category
        );

        Sanctum::actingAs(User::factory()->create(['role' => UserRole::JobSeeker]));

        $this->postJson('/api/job-posts', $this->validJobPostData($category))->assertForbidden();
        $this->putJson("/api/job-posts/{$post->id}", $this->validJobPostData($category))->assertForbidden();
        $this->deleteJson("/api/job-posts/{$post->id}")->assertForbidden();
    }

    public function test_employers_can_create_valid_job_posts(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $category = JobCategory::create(['name' => 'Engineering']);
        Sanctum::actingAs($employer);

        $this->postJson('/api/job-posts', $this->validJobPostData($category))
            ->assertCreated()
            ->assertJsonPath('job_post.title', 'Backend Engineer');

        $this->assertDatabaseHas('job_posts', [
            'employer_id' => $employer->id,
            'category_id' => $category->id,
            'title' => 'Backend Engineer',
        ]);
    }

    public function test_employers_can_update_and_delete_their_own_job_posts(): void
    {
        $employer = User::factory()->create(['role' => UserRole::Employer]);
        $category = JobCategory::create(['name' => 'Engineering']);
        $post = $this->createJobPost($employer, $category);
        Sanctum::actingAs($employer);

        $this->putJson("/api/job-posts/{$post->id}", $this->validJobPostData($category, [
            'title' => 'Updated Engineer',
        ]))
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Engineer');

        $this->assertDatabaseHas('job_posts', [
            'id' => $post->id,
            'title' => 'Updated Engineer',
        ]);

        $this->deleteJson("/api/job-posts/{$post->id}")->assertOk();
        $this->assertSoftDeleted('job_posts', ['id' => $post->id]);
    }

    public function test_employers_cannot_update_or_delete_another_employers_job_post(): void
    {
        $category = JobCategory::create(['name' => 'Engineering']);
        $post = $this->createJobPost(
            User::factory()->create(['role' => UserRole::Employer]),
            $category
        );
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employer]));

        $this->putJson("/api/job-posts/{$post->id}", $this->validJobPostData($category, [
            'title' => 'Unauthorized update',
        ]))->assertForbidden();
        $this->deleteJson("/api/job-posts/{$post->id}")->assertForbidden();

        $this->assertDatabaseHas('job_posts', [
            'id' => $post->id,
            'title' => 'Backend Engineer',
            'deleted_at' => null,
        ]);
    }

    public function test_guests_cannot_manage_job_categories_or_skills(): void
    {
        $category = JobCategory::create(['name' => 'Engineering']);
        $skill = Skill::create(['name' => 'PHP']);
        $requests = array_merge(
            $this->managementRequests('job-categories', $category->id, 'name'),
            $this->managementRequests('skills', $skill->id, 'name')
        );

        foreach ($requests as $request) {
            $request()->assertUnauthorized();
        }
    }

    public function test_non_admin_users_cannot_manage_job_categories_or_skills(): void
    {
        $category = JobCategory::create(['name' => 'Engineering']);
        $skill = Skill::create(['name' => 'PHP']);
        $requests = array_merge(
            $this->managementRequests('job-categories', $category->id, 'name'),
            $this->managementRequests('skills', $skill->id, 'name')
        );

        foreach ([UserRole::Employer, UserRole::JobSeeker] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            foreach ($requests as $request) {
                $request()->assertForbidden();
            }
        }
    }

    public function test_admins_can_perform_job_category_and_skill_crud(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $category = JobCategory::create(['name' => 'Engineering']);
        $skill = Skill::create(['name' => 'PHP']);

        $this->getJson('/api/job-categories')->assertOk();
        $this->getJson("/api/job-categories/{$category->id}")->assertOk();
        $createdCategory = $this->postJson('/api/job-categories', [
            'name' => 'Design',
            'description' => 'Product design roles',
        ])->assertCreated();
        $this->putJson("/api/job-categories/{$category->id}", [
            'name' => 'Software Engineering',
        ])->assertOk();
        $this->deleteJson('/api/job-categories/' . $createdCategory->json('category.id'))->assertOk();

        $this->getJson('/api/skills')->assertOk();
        $this->getJson("/api/skills/{$skill->id}")->assertOk();
        $createdSkill = $this->postJson('/api/skills', ['name' => 'Laravel'])->assertCreated();
        $this->putJson("/api/skills/{$skill->id}", ['name' => 'PHP 8'])->assertOk();
        $this->deleteJson('/api/skills/' . $createdSkill->json('skill.id'))->assertOk();

        $this->assertDatabaseHas('job_categories', ['id' => $category->id, 'name' => 'Software Engineering']);
        $this->assertDatabaseMissing('job_categories', ['name' => 'Design']);
        $this->assertDatabaseHas('skills', ['id' => $skill->id, 'name' => 'PHP 8']);
        $this->assertDatabaseMissing('skills', ['name' => 'Laravel']);
    }

    public function test_invalid_job_post_data_returns_validation_errors(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employer]));
        $category = JobCategory::create(['name' => 'Engineering']);

        $this->postJson('/api/job-posts', $this->validJobPostData($category, [
            'title' => '',
            'contact_email' => 'not-an-email',
            'status' => 'draft',
        ]))->assertUnprocessable()->assertJsonValidationErrors([
                    'title',
                    'contact_email',
                    'status',
                ]);
    }

    public function test_job_post_creation_requires_at_least_one_contact_method(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => UserRole::Employer]));
        $category = JobCategory::create(['name' => 'Engineering']);

        $this->postJson('/api/job-posts', $this->validJobPostData($category, [
            'contact_email' => null,
            'contact_phone' => null,
            'google_form_url' => null,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['contact_email']);
    }

    private function createJobPost(User $employer, JobCategory $category): JobPost
    {
        return JobPost::create([
            'employer_id' => $employer->id,
            ...$this->validJobPostData($category),
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

    private function managementRequests(string $resource, int $id, string $field): array
    {
        $base = "/api/{$resource}";

        return [
            fn() => $this->getJson($base),
            fn() => $this->getJson("{$base}/{$id}"),
            fn() => $this->postJson($base, [$field => "Unauthorized {$resource}"]),
            fn() => $this->putJson("{$base}/{$id}", [$field => "Unauthorized update"]),
            fn() => $this->deleteJson("{$base}/{$id}"),
        ];
    }
}
