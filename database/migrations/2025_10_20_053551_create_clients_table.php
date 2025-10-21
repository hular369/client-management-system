<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('email');
            $table->string('phone_number');
            $table->boolean('is_duplicate')->default(false);
            $table->string('duplicate_group_hash')->nullable();
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['company_name', 'email', 'phone_number']);
            $table->index('is_duplicate');
            $table->index('duplicate_group_hash');
        });
    }

    public function down()
    {
        Schema::dropIfExists('clients');
    }
};