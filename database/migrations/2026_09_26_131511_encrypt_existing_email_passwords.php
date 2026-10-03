<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('user_email_accounts')
            ->whereNotNull('encrypted_password')
            ->orderBy('id')
            ->each(function ($row) {
                $value = $row->encrypted_password;

                // Already encrypted? Skip.
                try {
                    Crypt::decryptString($value);
                    return;
                } catch (\Exception $e) {
                    // Not encrypted — continue below
                }

                // Encrypt the plain text
                try {
                    DB::table('user_email_accounts')
                        ->where('id', $row->id)
                        ->update([
                            'encrypted_password' => Crypt::encryptString($value),
                        ]);

                    Log::info("Encrypted password for email account #{$row->id}");
                } catch (\Exception $e) {
                    Log::error("Failed to encrypt password for account #{$row->id}: " . $e->getMessage());
                }
            });
    }

    public function down(): void
    {
        // No rollback — reverting encryption is not safe
    }
};