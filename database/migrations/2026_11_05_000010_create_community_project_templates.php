<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_project_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_title', 100);
            $table->string('image_path', 2048);
            $table->timestamps();
        });
        Schema::table('community_project_proposals', function (Blueprint $table) {
            $table->foreignId('community_project_template_id')->nullable()->constrained()->restrictOnDelete();
        });
        DB::table('community_project_proposals')->whereNotNull('image_path')->orderBy('id')->each(function ($proposal) {
            $template = DB::table('community_project_templates')
                ->where('community_project_id', $proposal->community_project_id)
                ->where('image_path', $proposal->image_path)->value('id');
            $template ??= DB::table('community_project_templates')->insertGetId([
                'community_project_id' => $proposal->community_project_id,
                'uploaded_by' => $proposal->user_id,
                'source_title' => $proposal->title,
                'image_path' => $proposal->image_path,
                'created_at' => $proposal->created_at, 'updated_at' => $proposal->updated_at,
            ]);
            DB::table('community_project_proposals')->where('id', $proposal->id)
                ->update(['community_project_template_id' => $template]);
        });
    }

    public function down(): void
    {
        Schema::table('community_project_proposals', fn (Blueprint $table) => $table->dropConstrainedForeignId('community_project_template_id'));
        Schema::dropIfExists('community_project_templates');
    }
};
