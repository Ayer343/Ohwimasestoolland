<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rotation_group_members', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('rotation_group_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            
            // Member role in group
            $table->enum('role', ['member', 'leader', 'deputy'])->default('member');
            
            // Membership dates
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            
            // Preference and rotation tracking
            $table->decimal('preference_score', 3, 1)->nullable()->default(5.0);
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            
            // Status
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            
            // Rotation stats
            $table->integer('rotation_count')->default(0);
            $table->timestamp('last_rotation_date')->nullable();
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Unique constraint to prevent duplicate members
            $table->unique(['rotation_group_id', 'user_id'], 'group_member_unique');
            
            // Indexes
            $table->index('status');
            $table->index('role');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rotation_group_members');
    }
};