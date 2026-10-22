<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_studi', function (Blueprint $table) {
            $table->id();
            $table->enum('jenjang', ['D3', 'S1', 'Profesi', 'S2', 'S3']);
            $table->string('nama', 150);
            $table->string('fakultas', 150)->nullable();
            $table->timestamps();
            $table->unique(['jenjang', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_studi');
    }
};
