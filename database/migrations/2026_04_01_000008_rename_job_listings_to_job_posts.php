<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hasJobListings = Schema::hasTable('job_listings');
        $hasJobPosts = Schema::hasTable('job_posts');
        $hasJobSkills = Schema::hasTable('job_skills');
        $hasJobPostSkills = Schema::hasTable('job_post_skills');

        if (($hasJobListings && $hasJobPosts) || ($hasJobSkills && $hasJobPostSkills)) {
            throw new \RuntimeException('Both legacy and current recruitment tables exist; resolve the duplicate tables before migrating. No recruitment tables were changed.');
        }

        if ($hasJobListings) {
            Schema::rename('job_listings', 'job_posts');
        }

        if (!$hasJobSkills) {
            return;
        }

        Schema::table('job_skills', function (Blueprint $table) {
            $table->dropForeign(['job_listing_id']);
            $table->dropForeign(['skill_id']);
            $table->dropPrimary(['job_listing_id', 'skill_id']);
        });

        Schema::rename('job_skills', 'job_post_skills');

        Schema::table('job_post_skills', function (Blueprint $table) {
            $table->renameColumn('job_listing_id', 'job_post_id');
        });

        Schema::table('job_post_skills', function (Blueprint $table) {
            $table->foreign('job_post_id')->references('id')->on('job_posts')->cascadeOnDelete();
            $table->foreign('skill_id')->references('id')->on('skills')->cascadeOnDelete();
            $table->primary(['job_post_id', 'skill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (
            (Schema::hasTable('job_post_skills') && Schema::hasTable('job_skills'))
            || (Schema::hasTable('job_posts') && Schema::hasTable('job_listings'))
        ) {
            throw new \RuntimeException('Both legacy and current recruitment tables exist; resolve the duplicate tables before rolling back. No recruitment tables were changed.');
        }

        if (Schema::hasTable('job_post_skills') && !Schema::hasTable('job_skills')) {
            Schema::table('job_post_skills', function (Blueprint $table) {
                $table->dropForeign(['job_post_id']);
                $table->dropForeign(['skill_id']);
                $table->dropPrimary(['job_post_id', 'skill_id']);
            });

            Schema::rename('job_post_skills', 'job_skills');

            Schema::table('job_skills', function (Blueprint $table) {
                $table->renameColumn('job_post_id', 'job_listing_id');
            });

            Schema::table('job_skills', function (Blueprint $table) {
                $table->foreign('job_listing_id')->references('id')->on('job_listings')->cascadeOnDelete();
                $table->foreign('skill_id')->references('id')->on('skills')->cascadeOnDelete();
                $table->primary(['job_listing_id', 'skill_id']);
            });
        }

        if (Schema::hasTable('job_posts') && !Schema::hasTable('job_listings')) {
            Schema::rename('job_posts', 'job_listings');
        }
    }
};
