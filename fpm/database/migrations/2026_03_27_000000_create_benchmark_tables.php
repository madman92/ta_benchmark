<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::create('post_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45);
            $table->timestamp('viewed_at')->useCurrent();
            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_views');
        Schema::dropIfExists('posts');
    }
};
