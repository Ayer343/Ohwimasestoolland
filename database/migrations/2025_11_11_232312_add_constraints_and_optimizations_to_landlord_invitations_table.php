<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations for additional constraints and optimizations
     */
    public function up(): void
    {
        // ✅ 1. Add table comment for documentation
        DB::statement("ALTER TABLE landlord_invitations COMMENT 'Stores landlord invitation tokens with comprehensive tracking and validation'");

        // ✅ 2. Create a stored procedure for invitation cleanup (optional but powerful)
        $this->createCleanupProcedure();

        // ✅ 3. Add regular columns instead of generated columns (MySQL doesn't allow NOW() in generated columns)
        if (!Schema::hasColumn('landlord_invitations', 'days_until_expiration')) {
            Schema::table('landlord_invitations', function (Blueprint $table) {
                // Regular integer column - will be calculated in application code
                $table->integer('days_until_expiration')->nullable()->after('expires_at')
                    ->comment('Days until expiration (calculated in application)');
            });
        }
        
        if (!Schema::hasColumn('landlord_invitations', 'invitation_age_days')) {
            Schema::table('landlord_invitations', function (Blueprint $table) {
                // Regular integer column - will be calculated in application code
                $table->integer('invitation_age_days')->nullable()->after('days_until_expiration')
                    ->comment('Invitation age in days (calculated in application)');
            });
        }

        // ✅ 4. Create database triggers for data integrity
        $this->createTriggers();

        // ✅ 5. Create database views for common queries
        $this->createViews();
    }

    /**
     * Reverse the migrations
     */
    public function down(): void
    {
        // Drop views first
        DB::statement('DROP VIEW IF EXISTS active_landlord_invitations');
        DB::statement('DROP VIEW IF EXISTS landlord_invitation_stats');
        DB::statement('DROP VIEW IF EXISTS expiring_invitations');

        // Drop procedure
        DB::statement('DROP PROCEDURE IF EXISTS cleanup_expired_invitations');

        // Drop columns if they exist
        if (Schema::hasColumn('landlord_invitations', 'days_until_expiration')) {
            Schema::table('landlord_invitations', function (Blueprint $table) {
                $table->dropColumn('days_until_expiration');
            });
        }
        
        if (Schema::hasColumn('landlord_invitations', 'invitation_age_days')) {
            Schema::table('landlord_invitations', function (Blueprint $table) {
                $table->dropColumn('invitation_age_days');
            });
        }

        // Remove table comment
        DB::statement("ALTER TABLE landlord_invitations COMMENT ''");
    }

    /**
     * Create stored procedure for automated cleanup
     */
    private function createCleanupProcedure()
    {
        $procedureSql = "
        CREATE PROCEDURE cleanup_expired_invitations()
        BEGIN
            DECLARE processed_count INT DEFAULT 0;
            DECLARE error_occurred INT DEFAULT 0;
            DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET error_occurred = 1;
            
            START TRANSACTION;
            
            -- Mark expired invitations
            UPDATE landlord_invitations 
            SET status = 'expired',
                updated_at = NOW()
            WHERE status = 'sent' 
            AND expires_at <= NOW();
            
            SET processed_count = ROW_COUNT();
            
            -- Clean up very old failed/cancelled invitations (older than 90 days)
            DELETE FROM landlord_invitations 
            WHERE status IN ('failed', 'cancelled')
            AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND deleted_at IS NULL;
            
            IF error_occurred = 0 THEN
                COMMIT;
                SELECT CONCAT('Successfully processed ', processed_count, ' expired invitations') as result;
            ELSE
                ROLLBACK;
                SELECT 'Error occurred during cleanup' as result;
            END IF;
        END;
        ";

        // Only create if it doesn't exist
        DB::unprepared("DROP PROCEDURE IF EXISTS cleanup_expired_invitations");
        DB::unprepared($procedureSql);
    }

    /**
     * Create database triggers for data integrity
     */
    private function createTriggers()
    {
        // Trigger 1: Ensure accepted_at is after sent_at
        $trigger1 = "
        CREATE TRIGGER before_landlord_invitation_update
        BEFORE UPDATE ON landlord_invitations
        FOR EACH ROW
        BEGIN
            IF NEW.accepted_at IS NOT NULL AND NEW.sent_at IS NOT NULL THEN
                IF NEW.accepted_at < NEW.sent_at THEN
                    SET NEW.accepted_at = NEW.sent_at;
                END IF;
            END IF;
            
            -- Auto-update status to expired if past expiry
            IF NEW.status = 'sent' AND NEW.expires_at <= NOW() THEN
                SET NEW.status = 'expired';
            END IF;
            
            -- Update last_attempt_at when attempts change
            IF NEW.attempts != OLD.attempts THEN
                SET NEW.last_attempt_at = NOW();
            END IF;
        END;
        ";

        // Trigger 2: Prevent invalid status transitions
        $trigger2 = "
        CREATE TRIGGER validate_landlord_invitation_status
        BEFORE UPDATE ON landlord_invitations
        FOR EACH ROW
        BEGIN
            DECLARE invalid_transition CONDITION FOR SQLSTATE '45000';
            
            -- Once accepted, cannot change to other statuses
            IF OLD.status = 'accepted' AND NEW.status != 'accepted' THEN
                SIGNAL invalid_transition SET MESSAGE_TEXT = 'Cannot change status from accepted';
            END IF;
            
            -- Once cancelled, cannot change to other statuses
            IF OLD.status = 'cancelled' AND NEW.status != 'cancelled' THEN
                SIGNAL invalid_transition SET MESSAGE_TEXT = 'Cannot change status from cancelled';
            END IF;
        END;
        ";

        // Create triggers
        DB::unprepared("DROP TRIGGER IF EXISTS before_landlord_invitation_update");
        DB::unprepared("DROP TRIGGER IF EXISTS validate_landlord_invitation_status");
        
        DB::unprepared($trigger1);
        DB::unprepared($trigger2);
    }

    /**
     * Create database views for common queries (without generated column conflicts)
     */
    private function createViews()
    {
        // View 1: Active invitations with useful metadata
        $view1 = "
        CREATE VIEW active_landlord_invitations AS
        SELECT 
            li.id,
            li.property_id,
            li.landlord_id,
            li.token,
            li.invited_by,
            li.channels,
            li.status,
            li.invitation_type,
            li.custom_message,
            li.sent_at,
            li.accepted_at,
            li.expires_at,
            li.last_attempt_at,
            li.attempts,
            li.failure_reason,
            li.created_at,
            li.updated_at,
            li.deleted_at,
            p.property_name,
            p.zone,
            p.street_name,
            l.name as landlord_name,
            l.email as landlord_email,
            l.phone as landlord_phone,
            u.name as inviter_name,
            DATEDIFF(li.expires_at, NOW()) as days_until_expiration,
            CASE 
                WHEN li.status = 'sent' AND li.expires_at <= DATE_ADD(NOW(), INTERVAL 1 DAY) THEN 'expiring_soon'
                WHEN li.status = 'sent' THEN 'active'
                ELSE 'inactive'
            END as invitation_health
        FROM landlord_invitations li
        JOIN properties p ON li.property_id = p.id
        JOIN users l ON li.landlord_id = l.id
        JOIN users u ON li.invited_by = u.id
        WHERE li.deleted_at IS NULL
        AND li.status IN ('pending', 'sent')
        AND (li.expires_at IS NULL OR li.expires_at > NOW());
        ";

        // View 2: Invitation statistics
        $view2 = "
        CREATE VIEW landlord_invitation_stats AS
        SELECT 
            DATE(created_at) as invitation_date,
            COUNT(*) as total_invitations,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_invitations,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_invitations,
            SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_invitations,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_invitations,
            ROUND(SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) * 100.0 / 
                  NULLIF(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END), 0), 2) as acceptance_rate
        FROM landlord_invitations
        WHERE deleted_at IS NULL
        GROUP BY DATE(created_at)
        ORDER BY invitation_date DESC;
        ";

        // View 3: Expiring soon invitations
        $view3 = "
        CREATE VIEW expiring_invitations AS
        SELECT 
            li.id,
            li.property_id,
            li.landlord_id,
            li.token,
            li.status,
            li.sent_at,
            li.expires_at,
            li.attempts,
            li.created_at,
            p.property_name,
            l.name as landlord_name,
            l.email as landlord_email,
            DATEDIFF(li.expires_at, NOW()) as days_remaining
        FROM landlord_invitations li
        JOIN properties p ON li.property_id = p.id
        JOIN users l ON li.landlord_id = l.id
        WHERE li.deleted_at IS NULL
        AND li.status = 'sent'
        AND li.expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 DAY)
        ORDER BY li.expires_at ASC;
        ";

        // Create views
        DB::unprepared("DROP VIEW IF EXISTS active_landlord_invitations");
        DB::unprepared("DROP VIEW IF EXISTS landlord_invitation_stats");
        DB::unprepared("DROP VIEW IF EXISTS expiring_invitations");
        
        DB::unprepared($view1);
        DB::unprepared($view2);
        DB::unprepared($view3);
    }
};