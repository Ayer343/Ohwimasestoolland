<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('admin_billing_records')) {
            // Create table if it doesn't exist (full schema with foreign keys)
            Schema::create('admin_billing_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('developer_setting_id')->constrained('developer_settings')->onDelete('cascade');
                $table->foreignId('super_admin_id')->constrained('users')->onDelete('cascade');
                $table->string('agreement_number')->unique()->nullable(); // Make nullable initially
                
                // Financial Information
                $table->decimal('amount', 10, 2);
                $table->decimal('amount_received', 10, 2)->default(0);
                $table->string('currency', 3)->default('GHS');
                $table->enum('billing_frequency', ['one_time', 'weekly', 'monthly', 'quarterly', 'yearly'])->default('monthly');
                
                // Agreement Details
                $table->text('description');
                $table->text('notes')->nullable();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                
                // PRIMARY SUPER ADMIN FIELDS - NEW
                $table->boolean('is_primary_for_billing')->default(false)->after('status');
                $table->string('billing_contact_name')->nullable()->after('is_primary_for_billing');
                $table->string('billing_contact_email')->nullable()->after('billing_contact_name');
                $table->string('billing_contact_phone')->nullable()->after('billing_contact_email');
                $table->timestamp('primary_assigned_at')->nullable()->after('billing_contact_phone');
                $table->foreignId('primary_assigned_by')->nullable()->after('primary_assigned_at')->constrained('users')->onDelete('set null');
                
                // PDF Agreement Information
                $table->string('agreement_pdf_path')->nullable();
                $table->string('signed_agreement_pdf_path')->nullable();
                $table->timestamp('agreement_generated_at')->nullable();
                $table->foreignId('agreement_generated_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('signing_invitation_sent_at')->nullable();
                $table->foreignId('signing_invitation_sent_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('signing_completed_at')->nullable();
                
                // Status Information
                $table->enum('status', ['pending', 'active', 'completed', 'terminated', 'superseded', 'rejected', 'cancelled', 'draft'])->default('pending');
                $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'overdue'])->default('unpaid');
                
                // Request Information
                $table->foreignId('requested_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('requested_at')->nullable();
                
                // Agreement Information
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->timestamp('agreed_at')->nullable();
                $table->foreignId('agreed_by')->nullable()->constrained('users')->onDelete('set null');
                
                // Rejection Information
                $table->timestamp('rejected_at')->nullable();
                $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
                $table->text('rejection_reason')->nullable();
                
                // Termination Information
                $table->date('termination_date')->nullable();
                $table->foreignId('terminated_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('terminated_at')->nullable();
                $table->text('termination_reason')->nullable();
                
                // Change Tracking
                $table->text('change_reason')->nullable();
                $table->date('effective_date')->nullable();
                $table->foreignId('previous_agreement_id')->nullable()->constrained('admin_billing_records')->onDelete('set null');
                
                // Signature Tracking
                $table->timestamp('developer_signed_at')->nullable();
                $table->timestamp('super_admin_signed_at')->nullable();
                $table->timestamp('last_signature_attempt_at')->nullable();
                $table->integer('signature_attempt_count')->default(0);
                
                // Payment Information - COMPLETE SET
                $table->string('invoice_number')->nullable();
                $table->string('payment_method')->default('bank_transfer');
                
                // Bank Transfer Fields
                $table->string('payment_account_name')->nullable();
                $table->string('payment_account_number')->nullable();
                $table->string('payment_bank_name')->nullable();
                $table->string('payment_bank_branch')->nullable();
                
                // Mobile Money Fields
                $table->string('payment_mobile_number')->nullable();
                $table->string('payment_mobile_network')->nullable();
                $table->string('payment_mobile_account_name')->nullable();
                
                // Additional fields
                $table->string('category')->nullable()->default('other');
                $table->json('metadata')->nullable();
                $table->json('signature_metadata')->nullable();
                
                // Agreement Terms
                $table->json('terms')->nullable();
                $table->boolean('auto_renew')->default(true);
                $table->integer('renewal_notice_days')->default(30);
                $table->date('next_renewal_date')->nullable();
                
                // Audit fields
                $table->timestamp('last_payment_reminder_sent')->nullable();
                $table->integer('payment_reminder_count')->default(0);
                
                // NEW FIELDS FOR UPDATED WORKFLOW
                $table->string('billing_month')->nullable()->after('start_date');
                $table->date('last_invoice_generated_at')->nullable();
                $table->date('next_invoice_date')->nullable();
                $table->decimal('total_paid_to_date', 10, 2)->default(0);
                $table->json('shared_payment_history')->nullable();
                
                $table->timestamps();
                $table->softDeletes();
                
                // Indexes
                $table->index(['developer_setting_id', 'status']);
                $table->index(['super_admin_id', 'status']);
                $table->index('agreement_number');
                $table->index('start_date');
                $table->index('requested_by');
                $table->index(['status', 'payment_status']);
                $table->index('agreed_at');
                $table->index('signing_invitation_sent_at');
                $table->index('payment_method');
                $table->index('payment_mobile_network');
                $table->index('billing_month');
                $table->index('next_invoice_date');
                
                // PRIMARY SUPER ADMIN INDEXES
                $table->index('is_primary_for_billing');
                $table->index('billing_contact_email');
                $table->index(['is_primary_for_billing', 'status']);
                $table->index(['developer_setting_id', 'is_primary_for_billing']);
            });
            
            // After creating table, set agreement_number to not null
            DB::statement('ALTER TABLE admin_billing_records MODIFY COLUMN agreement_number VARCHAR(255) NOT NULL');
            
        } else {
            // For existing table, add columns one by one with proper checks
            
            // Get existing columns
            $existingColumns = Schema::getColumnListing('admin_billing_records');
            
            // PHASE 1: Add PRIMARY SUPER ADMIN FIELDS first (most important)
            Schema::table('admin_billing_records', function (Blueprint $table) use ($existingColumns) {
                // Primary super admin fields
                if (!in_array('is_primary_for_billing', $existingColumns)) {
                    $table->boolean('is_primary_for_billing')->default(false)->after('status');
                }
                
                if (!in_array('billing_contact_name', $existingColumns)) {
                    $table->string('billing_contact_name')->nullable()->after('is_primary_for_billing');
                }
                
                if (!in_array('billing_contact_email', $existingColumns)) {
                    $table->string('billing_contact_email')->nullable()->after('billing_contact_name');
                }
                
                if (!in_array('billing_contact_phone', $existingColumns)) {
                    $table->string('billing_contact_phone')->nullable()->after('billing_contact_email');
                }
                
                if (!in_array('primary_assigned_at', $existingColumns)) {
                    $table->timestamp('primary_assigned_at')->nullable()->after('billing_contact_phone');
                }
                
                if (!in_array('primary_assigned_by', $existingColumns)) {
                    $table->unsignedBigInteger('primary_assigned_by')->nullable()->after('primary_assigned_at');
                }
                
                // Signature tracking fields
                if (!in_array('developer_signed_at', $existingColumns)) {
                    $table->timestamp('developer_signed_at')->nullable();
                }
                
                if (!in_array('super_admin_signed_at', $existingColumns)) {
                    $table->timestamp('super_admin_signed_at')->nullable();
                }
                
                if (!in_array('last_signature_attempt_at', $existingColumns)) {
                    $table->timestamp('last_signature_attempt_at')->nullable();
                }
                
                if (!in_array('signature_attempt_count', $existingColumns)) {
                    $table->integer('signature_attempt_count')->default(0);
                }
            });
            
            // PHASE 2: Add basic columns without constraints first
            Schema::table('admin_billing_records', function (Blueprint $table) use ($existingColumns) {
                // Add agreement_number first (without unique constraint)
                if (!in_array('agreement_number', $existingColumns)) {
                    $table->string('agreement_number')->nullable()->after('id');
                }
                
                // Add other basic columns
                $basicColumns = [
                    'invoice_number' => function() use ($table) {
                        $table->string('invoice_number')->nullable();
                    },
                    'category' => function() use ($table) {
                        $table->string('category')->nullable()->default('other');
                    },
                    'amount' => function() use ($table) {
                        $table->decimal('amount', 10, 2)->default(0);
                    },
                    'amount_received' => function() use ($table) {
                        $table->decimal('amount_received', 10, 2)->default(0);
                    },
                    'currency' => function() use ($table) {
                        $table->string('currency', 3)->default('GHS');
                    },
                    'description' => function() use ($table) {
                        $table->text('description')->nullable();
                    },
                    'notes' => function() use ($table) {
                        $table->text('notes')->nullable();
                    },
                    'start_date' => function() use ($table) {
                        $table->date('start_date')->nullable();
                    },
                    'end_date' => function() use ($table) {
                        $table->date('end_date')->nullable();
                    },
                    'payment_status' => function() use ($table) {
                        $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'overdue'])->default('unpaid');
                    },
                    'billing_frequency' => function() use ($table) {
                        $table->enum('billing_frequency', ['one_time', 'weekly', 'monthly', 'quarterly', 'yearly'])
                              ->default('monthly');
                    },
                    // Payment method fields
                    'payment_method' => function() use ($table) {
                        $table->string('payment_method')->default('bank_transfer');
                    },
                    'payment_mobile_number' => function() use ($table) {
                        $table->string('payment_mobile_number')->nullable();
                    },
                    'payment_mobile_network' => function() use ($table) {
                        $table->string('payment_mobile_network')->nullable();
                    },
                    'payment_mobile_account_name' => function() use ($table) {
                        $table->string('payment_mobile_account_name')->nullable();
                    },
                    'payment_account_name' => function() use ($table) {
                        $table->string('payment_account_name')->nullable();
                    },
                    'payment_account_number' => function() use ($table) {
                        $table->string('payment_account_number')->nullable();
                    },
                    'payment_bank_name' => function() use ($table) {
                        $table->string('payment_bank_name')->nullable();
                    },
                    'payment_bank_branch' => function() use ($table) {
                        $table->string('payment_bank_branch')->nullable();
                    },
                    'metadata' => function() use ($table) {
                        $table->json('metadata')->nullable();
                    },
                    'signature_metadata' => function() use ($table) {
                        $table->json('signature_metadata')->nullable();
                    },
                    'agreement_pdf_path' => function() use ($table) {
                        $table->string('agreement_pdf_path')->nullable();
                    },
                    'signed_agreement_pdf_path' => function() use ($table) {
                        $table->string('signed_agreement_pdf_path')->nullable();
                    },
                    'agreement_generated_at' => function() use ($table) {
                        $table->timestamp('agreement_generated_at')->nullable();
                    },
                    'signing_invitation_sent_at' => function() use ($table) {
                        $table->timestamp('signing_invitation_sent_at')->nullable();
                    },
                    'signing_completed_at' => function() use ($table) {
                        $table->timestamp('signing_completed_at')->nullable();
                    },
                    'terms' => function() use ($table) {
                        $table->json('terms')->nullable();
                    },
                    'auto_renew' => function() use ($table) {
                        $table->boolean('auto_renew')->default(true);
                    },
                    'renewal_notice_days' => function() use ($table) {
                        $table->integer('renewal_notice_days')->default(30);
                    },
                    'next_renewal_date' => function() use ($table) {
                        $table->date('next_renewal_date')->nullable();
                    },
                    'last_payment_reminder_sent' => function() use ($table) {
                        $table->timestamp('last_payment_reminder_sent')->nullable();
                    },
                    'payment_reminder_count' => function() use ($table) {
                        $table->integer('payment_reminder_count')->default(0);
                    },
                ];
                
                // Add basic columns
                foreach ($basicColumns as $columnName => $closure) {
                    if (!in_array($columnName, $existingColumns)) {
                        $closure();
                    }
                }
                
                // Add soft deletes if missing
                if (!in_array('deleted_at', $existingColumns)) {
                    $table->softDeletes();
                }
            });
            
            // PHASE 3: Add NEW WORKFLOW columns
            Schema::table('admin_billing_records', function (Blueprint $table) use ($existingColumns) {
                $newWorkflowColumns = [
                    'billing_month' => function() use ($table) {
                        $table->string('billing_month')->nullable()->after('start_date');
                    },
                    'last_invoice_generated_at' => function() use ($table) {
                        $table->timestamp('last_invoice_generated_at')->nullable();
                    },
                    'next_invoice_date' => function() use ($table) {
                        $table->date('next_invoice_date')->nullable();
                    },
                    'total_paid_to_date' => function() use ($table) {
                        $table->decimal('total_paid_to_date', 10, 2)->default(0);
                    },
                    'shared_payment_history' => function() use ($table) {
                        $table->json('shared_payment_history')->nullable();
                    },
                ];
                
                foreach ($newWorkflowColumns as $columnName => $closure) {
                    if (!in_array($columnName, $existingColumns)) {
                        $closure();
                    }
                }
            });
            
            // PHASE 4: Add user reference columns (foreign keys added later)
            Schema::table('admin_billing_records', function (Blueprint $table) use ($existingColumns) {
                $userColumns = [
                    'requested_by' => function() use ($table) {
                        $table->unsignedBigInteger('requested_by')->nullable();
                    },
                    'requested_at' => function() use ($table) {
                        $table->timestamp('requested_at')->nullable();
                    },
                    'created_by' => function() use ($table) {
                        $table->unsignedBigInteger('created_by')->nullable();
                    },
                    'agreed_at' => function() use ($table) {
                        $table->timestamp('agreed_at')->nullable();
                    },
                    'agreed_by' => function() use ($table) {
                        $table->unsignedBigInteger('agreed_by')->nullable();
                    },
                    'rejected_at' => function() use ($table) {
                        $table->timestamp('rejected_at')->nullable();
                    },
                    'rejected_by' => function() use ($table) {
                        $table->unsignedBigInteger('rejected_by')->nullable();
                    },
                    'rejection_reason' => function() use ($table) {
                        $table->text('rejection_reason')->nullable();
                    },
                    'termination_date' => function() use ($table) {
                        $table->date('termination_date')->nullable();
                    },
                    'terminated_by' => function() use ($table) {
                        $table->unsignedBigInteger('terminated_by')->nullable();
                    },
                    'terminated_at' => function() use ($table) {
                        $table->timestamp('terminated_at')->nullable();
                    },
                    'termination_reason' => function() use ($table) {
                        $table->text('termination_reason')->nullable();
                    },
                    'change_reason' => function() use ($table) {
                        $table->text('change_reason')->nullable();
                    },
                    'effective_date' => function() use ($table) {
                        $table->date('effective_date')->nullable();
                    },
                    'previous_agreement_id' => function() use ($table) {
                        $table->unsignedBigInteger('previous_agreement_id')->nullable();
                    },
                    'agreement_generated_by' => function() use ($table) {
                        $table->unsignedBigInteger('agreement_generated_by')->nullable();
                    },
                    'signing_invitation_sent_by' => function() use ($table) {
                        $table->unsignedBigInteger('signing_invitation_sent_by')->nullable();
                    },
                ];
                
                foreach ($userColumns as $columnName => $closure) {
                    if (!in_array($columnName, $existingColumns)) {
                        $closure();
                    }
                }
            });
            
            // PHASE 5: Update status enum if needed
            if (Schema::hasColumn('admin_billing_records', 'status')) {
                try {
                    $columnInfo = DB::selectOne("
                        SELECT COLUMN_TYPE 
                        FROM INFORMATION_SCHEMA.COLUMNS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'admin_billing_records' 
                        AND COLUMN_NAME = 'status'
                    ");
                    
                    if ($columnInfo && strpos($columnInfo->COLUMN_TYPE, "'draft'") === false) {
                        DB::statement("
                            ALTER TABLE admin_billing_records 
                            MODIFY COLUMN status ENUM(
                                'pending', 
                                'active', 
                                'completed', 
                                'terminated', 
                                'superseded', 
                                'rejected', 
                                'cancelled', 
                                'draft'
                            ) DEFAULT 'pending'
                        ");
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to update status enum: ' . $e->getMessage());
                }
            }
            
            // PHASE 6: Add indexes
            try {
                Schema::table('admin_billing_records', function (Blueprint $table) use ($existingColumns) {
                    // Single column indexes
                    $singleIndexes = [
                        'agreement_number',
                        'start_date',
                        'agreed_at',
                        'signing_invitation_sent_at',
                        'requested_by',
                        'payment_method',
                        'payment_mobile_network',
                        'billing_month',
                        'next_invoice_date',
                        'last_invoice_generated_at',
                        // PRIMARY SUPER ADMIN INDEXES
                        'is_primary_for_billing',
                        'billing_contact_email',
                    ];
                    
                    foreach ($singleIndexes as $column) {
                        if (in_array($column, $existingColumns) || $column === 'agreement_number') {
                            try {
                                $table->index($column);
                            } catch (\Exception $e) {
                                // Index might already exist
                            }
                        }
                    }
                    
                    // Composite indexes
                    $compositeIndexes = [
                        ['developer_setting_id', 'status'],
                        ['super_admin_id', 'status'],
                        ['status', 'payment_status'],
                        ['developer_setting_id', 'billing_month'],
                        ['status', 'billing_month'],
                        // PRIMARY SUPER ADMIN COMPOSITE INDEXES
                        ['is_primary_for_billing', 'status'],
                        ['developer_setting_id', 'is_primary_for_billing'],
                        ['is_primary_for_billing', 'billing_month'],
                    ];
                    
                    foreach ($compositeIndexes as $indexColumns) {
                        $allExist = true;
                        foreach ($indexColumns as $col) {
                            if (!in_array($col, $existingColumns) && $col !== 'agreement_number' && $col !== 'is_primary_for_billing') {
                                $allExist = false;
                                break;
                            }
                        }
                        
                        if ($allExist) {
                            try {
                                $table->index($indexColumns);
                            } catch (\Exception $e) {
                                // Index might already exist
                            }
                        }
                    }
                });
            } catch (\Exception $e) {
                Log::warning('Failed to add indexes: ' . $e->getMessage());
            }
            
            // PHASE 7: Add unique constraint to agreement_number AFTER all columns are added
            if (Schema::hasColumn('admin_billing_records', 'agreement_number')) {
                try {
                    // First, update any null values to prevent unique constraint violation
                    DB::table('admin_billing_records')
                        ->whereNull('agreement_number')
                        ->update([
                            'agreement_number' => DB::raw("CONCAT('AGR-', LPAD(id, 6, '0'), '-', DATE_FORMAT(created_at, '%Y%m%d'))")
                        ]);
                    
                    // Then add unique constraint
                    Schema::table('admin_billing_records', function (Blueprint $table) {
                        $table->unique('agreement_number');
                    });
                    
                    // Finally, make it not null
                    DB::statement('ALTER TABLE admin_billing_records MODIFY COLUMN agreement_number VARCHAR(255) NOT NULL');
                    
                } catch (\Exception $e) {
                    Log::warning('Failed to add unique constraint to agreement_number: ' . $e->getMessage());
                }
            }
            
            // PHASE 8: Initialize next_invoice_date for existing active agreements
            if (Schema::hasColumn('admin_billing_records', 'next_invoice_date')) {
                DB::table('admin_billing_records')
                    ->where('status', 'active')
                    ->whereNull('next_invoice_date')
                    ->update([
                        'next_invoice_date' => DB::raw("DATE_ADD(COALESCE(start_date, created_at), INTERVAL 1 MONTH)")
                    ]);
            }
            
            // PHASE 9: Add foreign keys (separate try-catch for each to isolate issues)
            $foreignKeys = [
                'agreement_generated_by' => ['users', 'set null'],
                'signing_invitation_sent_by' => ['users', 'set null'],
                'requested_by' => ['users', 'set null'],
                'created_by' => ['users', 'cascade'],
                'agreed_by' => ['users', 'set null'],
                'rejected_by' => ['users', 'set null'],
                'terminated_by' => ['users', 'set null'],
                'previous_agreement_id' => ['admin_billing_records', 'set null'],
                'primary_assigned_by' => ['users', 'set null'], // NEW FOREIGN KEY
            ];
            
            foreach ($foreignKeys as $column => [$referencedTable, $onDelete]) {
                if (Schema::hasColumn('admin_billing_records', $column) && Schema::hasTable($referencedTable)) {
                    try {
                        // Check if foreign key already exists
                        $foreignKeyExists = DB::selectOne("
                            SELECT CONSTRAINT_NAME 
                            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                            WHERE TABLE_SCHEMA = DATABASE() 
                            AND TABLE_NAME = 'admin_billing_records' 
                            AND COLUMN_NAME = ? 
                            AND REFERENCED_TABLE_NAME IS NOT NULL
                        ", [$column]);
                        
                        if (!$foreignKeyExists) {
                            Schema::table('admin_billing_records', function (Blueprint $table) use ($column, $referencedTable, $onDelete) {
                                $table->foreign($column)
                                      ->references('id')
                                      ->on($referencedTable)
                                      ->onDelete($onDelete);
                            });
                        }
                    } catch (\Exception $e) {
                        Log::warning("Failed to add foreign key for {$column}: " . $e->getMessage());
                    }
                }
            }
            
            // PHASE 10: Ensure foreign keys for required columns exist
            $requiredForeignKeys = [
                'developer_setting_id' => ['developer_settings', 'cascade'],
                'super_admin_id' => ['users', 'cascade'],
            ];
            
            foreach ($requiredForeignKeys as $column => [$referencedTable, $onDelete]) {
                if (Schema::hasColumn('admin_billing_records', $column) && Schema::hasTable($referencedTable)) {
                    try {
                        $foreignKeyExists = DB::selectOne("
                            SELECT CONSTRAINT_NAME 
                            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                            WHERE TABLE_SCHEMA = DATABASE() 
                            AND TABLE_NAME = 'admin_billing_records' 
                            AND COLUMN_NAME = ? 
                            AND REFERENCED_TABLE_NAME IS NOT NULL
                        ", [$column]);
                        
                        if (!$foreignKeyExists) {
                            Schema::table('admin_billing_records', function (Blueprint $table) use ($column, $referencedTable, $onDelete) {
                                $table->foreign($column)
                                      ->references('id')
                                      ->on($referencedTable)
                                      ->onDelete($onDelete);
                            });
                        }
                    } catch (\Exception $e) {
                        Log::warning("Failed to add required foreign key for {$column}: " . $e->getMessage());
                    }
                }
            }
            
            // PHASE 11: Initialize primary super admin data if any agreements exist
            if (Schema::hasColumn('admin_billing_records', 'is_primary_for_billing')) {
                // If there's no primary assigned but there are active agreements, set the first active agreement as primary
                $hasPrimary = DB::table('admin_billing_records')
                    ->where('is_primary_for_billing', true)
                    ->exists();
                
                if (!$hasPrimary) {
                    $firstActiveAgreement = DB::table('admin_billing_records')
                        ->where('status', 'active')
                        ->orderBy('created_at', 'asc')
                        ->first();
                    
                    if ($firstActiveAgreement) {
                        DB::table('admin_billing_records')
                            ->where('id', $firstActiveAgreement->id)
                            ->update([
                                'is_primary_for_billing' => true,
                                'primary_assigned_at' => now(),
                                'billing_contact_name' => DB::raw("COALESCE(billing_contact_name, (SELECT name FROM users WHERE id = super_admin_id))"),
                                'billing_contact_email' => DB::raw("COALESCE(billing_contact_email, (SELECT email FROM users WHERE id = super_admin_id))"),
                            ]);
                        
                        Log::info('Auto-assigned primary super admin for billing', [
                            'agreement_id' => $firstActiveAgreement->id,
                            'super_admin_id' => $firstActiveAgreement->super_admin_id
                        ]);
                    }
                }
            }
        }
    }

    public function down()
    {
        // Only drop the table if we're rolling back the initial creation
        if (Schema::hasTable('admin_billing_records')) {
            // Check if this is the full table or just columns were added
            $hasBasicColumns = Schema::hasColumns('admin_billing_records', ['agreement_number', 'amount', 'description']);
            
            if ($hasBasicColumns) {
                // We're rolling back column additions, not dropping the entire table
                
                // Remove unique constraint from agreement_number first
                try {
                    Schema::table('admin_billing_records', function (Blueprint $table) {
                        $table->dropUnique(['agreement_number']);
                    });
                } catch (\Exception $e) {
                    Log::warning('Failed to drop agreement_number unique constraint: ' . $e->getMessage());
                }
                
                // Remove foreign keys
                $foreignKeysToDrop = [
                    'admin_billing_records_agreement_generated_by_foreign',
                    'admin_billing_records_signing_invitation_sent_by_foreign',
                    'admin_billing_records_requested_by_foreign',
                    'admin_billing_records_created_by_foreign',
                    'admin_billing_records_agreed_by_foreign',
                    'admin_billing_records_rejected_by_foreign',
                    'admin_billing_records_terminated_by_foreign',
                    'admin_billing_records_previous_agreement_id_foreign',
                    'admin_billing_records_primary_assigned_by_foreign', // NEW
                ];
                
                foreach ($foreignKeysToDrop as $foreignKey) {
                    try {
                        Schema::table('admin_billing_records', function (Blueprint $table) use ($foreignKey) {
                            $table->dropForeign($foreignKey);
                        });
                    } catch (\Exception $e) {
                        // Foreign key might not exist
                    }
                }
                
                // Remove indexes
                $indexesToDrop = [
                    'admin_billing_records_agreement_number_index',
                    'admin_billing_records_start_date_index',
                    'admin_billing_records_agreed_at_index',
                    'admin_billing_records_signing_invitation_sent_at_index',
                    'admin_billing_records_requested_by_index',
                    'admin_billing_records_payment_method_index',
                    'admin_billing_records_payment_mobile_network_index',
                    'admin_billing_records_billing_month_index',
                    'admin_billing_records_next_invoice_date_index',
                    'admin_billing_records_last_invoice_generated_at_index',
                    'admin_billing_records_developer_setting_id_status_index',
                    'admin_billing_records_super_admin_id_status_index',
                    'admin_billing_records_status_payment_status_index',
                    'admin_billing_records_developer_setting_id_billing_month_index',
                    'admin_billing_records_status_billing_month_index',
                    'admin_billing_records_agreement_number_unique',
                    // PRIMARY SUPER ADMIN INDEXES
                    'admin_billing_records_is_primary_for_billing_index',
                    'admin_billing_records_billing_contact_email_index',
                    'admin_billing_records_is_primary_for_billing_status_index',
                    'admin_billing_records_developer_setting_id_is_primary_for_billing_index',
                    'admin_billing_records_is_primary_for_billing_billing_month_index',
                ];
                
                foreach ($indexesToDrop as $index) {
                    try {
                        Schema::table('admin_billing_records', function (Blueprint $table) use ($index) {
                            $table->dropIndex($index);
                        });
                    } catch (\Exception $e) {
                        // Index might not exist
                    }
                }
                
                // Remove columns in small batches
                $columnsToDrop = [
                    // PRIMARY SUPER ADMIN FIELDS
                    'is_primary_for_billing',
                    'billing_contact_name',
                    'billing_contact_email',
                    'billing_contact_phone',
                    'primary_assigned_at',
                    'primary_assigned_by',
                    
                    // Signature tracking fields
                    'developer_signed_at',
                    'super_admin_signed_at',
                    'last_signature_attempt_at',
                    'signature_attempt_count',
                    
                    // Basic agreement info
                    'agreement_number',
                    'invoice_number',
                    'category',
                    
                    // Financial columns
                    'amount',
                    'amount_received',
                    'currency',
                    'billing_frequency',
                    
                    // Agreement details
                    'description',
                    'notes',
                    'start_date',
                    'end_date',
                    
                    // NEW WORKFLOW COLUMNS
                    'billing_month',
                    'last_invoice_generated_at',
                    'next_invoice_date',
                    'total_paid_to_date',
                    'shared_payment_history',
                    
                    // PDF Agreement columns
                    'agreement_pdf_path',
                    'signed_agreement_pdf_path',
                    'agreement_generated_at',
                    'agreement_generated_by',
                    'signing_invitation_sent_at',
                    'signing_invitation_sent_by',
                    'signing_completed_at',
                    
                    // Status columns
                    'payment_status',
                    
                    // User reference columns
                    'requested_by',
                    'requested_at',
                    'created_by',
                    'agreed_at',
                    'agreed_by',
                    'rejected_at',
                    'rejected_by',
                    'rejection_reason',
                    
                    // Termination columns
                    'termination_date',
                    'terminated_by',
                    'terminated_at',
                    'termination_reason',
                    
                    // Change tracking
                    'change_reason',
                    'effective_date',
                    'previous_agreement_id',
                    
                    // Payment method columns
                    'payment_method',
                    'payment_mobile_number',
                    'payment_mobile_network',
                    'payment_mobile_account_name',
                    'payment_account_name',
                    'payment_account_number',
                    'payment_bank_name',
                    'payment_bank_branch',
                    
                    // Metadata columns
                    'metadata',
                    'signature_metadata',
                    
                    // Terms and renewal
                    'terms',
                    'auto_renew',
                    'renewal_notice_days',
                    'next_renewal_date',
                    
                    // Audit fields
                    'last_payment_reminder_sent',
                    'payment_reminder_count',
                ];
                
                // Drop columns in very small batches to avoid issues
                foreach ($columnsToDrop as $column) {
                    if (Schema::hasColumn('admin_billing_records', $column)) {
                        try {
                            Schema::table('admin_billing_records', function (Blueprint $table) use ($column) {
                                $table->dropColumn($column);
                            });
                        } catch (\Exception $e) {
                            Log::warning("Failed to drop column {$column}: " . $e->getMessage());
                        }
                    }
                }
                
                // Remove soft deletes if it was added
                if (Schema::hasColumn('admin_billing_records', 'deleted_at')) {
                    try {
                        Schema::table('admin_billing_records', function (Blueprint $table) {
                            $table->dropSoftDeletes();
                        });
                    } catch (\Exception $e) {
                        Log::warning('Failed to drop soft deletes: ' . $e->getMessage());
                    }
                }
            } else {
                // This appears to be the initial table creation - drop the entire table
                Schema::dropIfExists('admin_billing_records');
            }
        }
    }
};