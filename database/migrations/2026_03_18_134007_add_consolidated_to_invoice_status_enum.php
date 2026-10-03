<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddConsolidatedToInvoiceStatusEnum extends Migration
{
    public function up()
    {
        // For MySQL, you need to modify the enum
        DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('pending', 'paid', 'overdue', 'partial', 'cancelled', 'processing', 'consolidated') DEFAULT 'pending'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE invoices MODIFY COLUMN status ENUM('pending', 'paid', 'overdue', 'partial', 'cancelled', 'processing') DEFAULT 'pending'");
    }
}