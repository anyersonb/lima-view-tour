<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hotfix (2026-09-24): title_en/excerpt_en/body_en/title_pt/excerpt_pt/body_pt
 * were created NOT NULL with no DB default by 2026_06_29_000010. The
 * English/Portuguese tabs in BlogPostResource.php are optional by design
 * (only the Spanish tab is ->required()), so saving a Spanish-only post makes
 * Filament send NULL for these six columns and MySQL rejects the INSERT:
 * "SQLSTATE[23000]: ... Column 'title_en' cannot be null" (500 on
 * /admin/blog-posts/create).
 *
 * The model's locale-aware accessors (getTitleAttribute, getExcerptAttribute,
 * getBodyAttribute, getMetaTitleAttribute, getMetaDescriptionAttribute) already
 * fall back to Spanish via `?:`, which treats NULL exactly like '' — so
 * nothing else needs to change on the read side for the fallback to keep
 * working once these columns can actually hold NULL.
 *
 * A new migration is used instead of editing 2026_06_29_000010 because that
 * one already ran in production; changing it in place would not alter the
 * live schema and would desync fresh installs from prod.
 */
return new class extends Migration
{
    /** Column => Blueprint column-type method, matching the original migration exactly. */
    protected const COLUMNS = [
        'title_en' => 'string',
        'excerpt_en' => 'text',
        'body_en' => 'longText',
        'title_pt' => 'string',
        'excerpt_pt' => 'text',
        'body_pt' => 'longText',
    ];

    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            foreach (self::COLUMNS as $column => $type) {
                $table->{$type}($column)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            foreach (self::COLUMNS as $column => $type) {
                $table->{$type}($column)->nullable(false)->change();
            }
        });
    }
};
