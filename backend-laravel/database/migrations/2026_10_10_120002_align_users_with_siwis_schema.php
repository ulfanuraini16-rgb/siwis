<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'name')) {
            if (Schema::hasColumn('users', 'nama')) {
                DB::table('users')
                    ->where(function ($query) {
                        $query->whereNull('nama')->orWhere('nama', '');
                    })
                    ->update(['nama' => DB::raw('name')]);
                Schema::table('users', fn (Blueprint $table) => $table->dropColumn('name'));
            } else {
                Schema::table('users', fn (Blueprint $table) => $table->renameColumn('name', 'nama'));
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 120)->change();
            $table->string('nama', 120)->change();
        });

        $legacyColumns = array_values(array_filter(
            ['email_verified_at', 'remember_token'],
            fn (string $column) => Schema::hasColumn('users', $column),
        ));

        if ($legacyColumns) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn($legacyColumns));
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 255)->change();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->string('name', 255)->nullable();
        });

        DB::table('users')->update(['name' => DB::raw('nama')]);
    }
};