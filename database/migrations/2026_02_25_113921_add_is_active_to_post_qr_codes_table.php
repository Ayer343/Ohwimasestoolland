<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsActiveToPostQrCodesTable extends Migration
{
    public function up()
    {
        Schema::table('post_qr_codes', function (Blueprint $table) {
            if (!Schema::hasColumn('post_qr_codes', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('used');
            }
            
            if (!Schema::hasColumn('post_qr_codes', 'name')) {
                $table->string('name')->after('post_id');
            }
            
            if (!Schema::hasColumn('post_qr_codes', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            
            if (!Schema::hasColumn('post_qr_codes', 'code_type')) {
                $table->string('code_type')->default('static')->after('code');
            }
            
            if (!Schema::hasColumn('post_qr_codes', 'max_uses')) {
                $table->integer('max_uses')->nullable()->after('expires_at');
            }
            
            if (!Schema::hasColumn('post_qr_codes', 'uses_count')) {
                $table->integer('uses_count')->default(0)->after('max_uses');
            }
            
            if (!Schema::hasColumn('post_qr_codes', 'image_path')) {
                $table->string('image_path')->nullable()->after('metadata');
            }
            
            if (!Schema::hasColumn('post_qr_codes', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('image_path');
            }
        });
        
        // Add foreign key for created_by if it doesn't exist
        try {
            Schema::table('post_qr_codes', function (Blueprint $table) {
                $table->foreign('created_by', 'fk_qr_created_by')
                      ->references('id')
                      ->on('users')
                      ->onDelete('set null');
            });
        } catch (\Exception $e) {
            // Foreign key might already exist
        }
    }

    public function down()
    {
        Schema::table('post_qr_codes', function (Blueprint $table) {
            $columns = ['is_active', 'name', 'description', 'code_type', 
                       'max_uses', 'uses_count', 'image_path', 'created_by'];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('post_qr_codes', $column)) {
                    if ($column === 'created_by') {
                        $table->dropForeign(['created_by']);
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
}