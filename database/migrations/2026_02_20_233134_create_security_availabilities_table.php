<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSecurityAvailabilitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('security_availabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('date');
            $table->enum('status', ['available', 'unavailable', 'prefer'])->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Add indexes
            $table->index('user_id');
            $table->index('date');
            $table->unique(['user_id', 'date']); // Prevent duplicate entries for same user/date
            
            // Foreign key constraint
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('security_availabilities');
    }
}