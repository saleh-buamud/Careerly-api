<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $this->renameForeignKeys([
            ['job_listings_employer_id_foreign', 'job_posts_employer_id_foreign', 'employer_id', 'users', 'cascade'],
            ['job_listings_category_id_foreign', 'job_posts_category_id_foreign', 'category_id', 'job_categories', 'set null'],
        ]);
    }

    public function down(): void
    {
        $this->renameForeignKeys([
            ['job_posts_employer_id_foreign', 'job_listings_employer_id_foreign', 'employer_id', 'users', 'cascade'],
            ['job_posts_category_id_foreign', 'job_listings_category_id_foreign', 'category_id', 'job_categories', 'set null'],
        ]);
    }

    private function renameForeignKeys(array $renames): void
    {
        if (!Schema::hasTable('job_posts')) {
            return;
        }

        $foreignKeyNames = collect(Schema::getForeignKeys('job_posts'))
            ->pluck('name')
            ->all();

        foreach ($renames as [$oldName, $newName]) {
            if (in_array($oldName, $foreignKeyNames, true) && in_array($newName, $foreignKeyNames, true)) {
                throw new \RuntimeException("Both {$oldName} and {$newName} exist; no foreign keys were changed.");
            }
        }

        foreach ($renames as [$oldName, $newName, $column, $referencedTable, $onDelete]) {
            $foreignKeyNames = collect(Schema::getForeignKeys('job_posts'))
                ->pluck('name')
                ->all();

            if (!in_array($oldName, $foreignKeyNames, true)) {
                continue;
            }

            Schema::table('job_posts', function (Blueprint $table) use ($oldName) {
                $table->dropForeign($oldName);
            });

            Schema::table('job_posts', function (Blueprint $table) use ($newName, $column, $referencedTable, $onDelete) {
                $foreign = $table->foreign($column, $newName)
                    ->references('id')
                    ->on($referencedTable);

                if ($onDelete === 'cascade') {
                    $foreign->cascadeOnDelete();
                } else {
                    $foreign->nullOnDelete();
                }
            });
        }
    }
};