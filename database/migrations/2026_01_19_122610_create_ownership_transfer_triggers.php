<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create triggers/procedures for data integrity (for databases that support them)
        if (DB::connection()->getDriverName() === 'mysql') {
            // Create a trigger to update property ownership history when transfer is completed
            DB::unprepared('
                CREATE TRIGGER after_transfer_completed 
                AFTER UPDATE ON property_ownership_transfers
                FOR EACH ROW
                BEGIN
                    IF NEW.status = "completed" AND OLD.status != "completed" THEN
                        -- Update property ownership fields
                        UPDATE properties 
                        SET 
                            previous_landlord_id = NEW.current_landlord_id,
                            landlord_id = NEW.new_landlord_id,
                            ownership_transferred_at = NEW.completed_at,
                            ownership_transfer_count = ownership_transfer_count + 1
                        WHERE id = NEW.property_id;
                        
                        -- Log to ownership history
                        INSERT INTO property_ownership_history 
                        (property_id, previous_landlord_id, new_landlord_id, transfer_id, 
                         transfer_date, sale_amount, document_type, document_reference,
                         previous_owner_name, new_owner_name, created_at, updated_at)
                        VALUES 
                        (NEW.property_id, NEW.current_landlord_id, NEW.new_landlord_id, NEW.id,
                         NEW.transfer_date, NEW.sale_amount, NEW.document_type, NEW.document_reference,
                         (SELECT name FROM users WHERE id = NEW.current_landlord_id),
                         NEW.new_owner_name, NOW(), NOW());
                    END IF;
                END
            ');
            
            // Create trigger to prevent multiple pending transfers for same property
            DB::unprepared('
                CREATE TRIGGER before_transfer_insert 
                BEFORE INSERT ON property_ownership_transfers
                FOR EACH ROW
                BEGIN
                    DECLARE pending_count INT;
                    
                    SELECT COUNT(*) INTO pending_count 
                    FROM property_ownership_transfers 
                    WHERE property_id = NEW.property_id 
                    AND status IN ("pending", "approved");
                    
                    IF pending_count > 0 THEN
                        SIGNAL SQLSTATE "45000"
                        SET MESSAGE_TEXT = "Cannot create transfer request: Property already has a pending transfer";
                    END IF;
                END
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS after_transfer_completed');
            DB::unprepared('DROP TRIGGER IF EXISTS before_transfer_insert');
        }
    }
};