<?php

// DOCUMENTO_RECTOR §4 Grupo B — core_people

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_people', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 16)->nullable();
            $table->string('document_number', 32);
            $table->string('first_name')->nullable();
            $table->string('second_name')->nullable();
            $table->string('first_surname')->nullable();
            $table->string('second_surname')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 32)->nullable();
            $table->string('marital_status', 32)->nullable();
            $table->string('address')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city_code', 16)->nullable();
            $table->string('city_name')->nullable();
            $table->string('department_code', 16)->nullable();
            $table->string('department_name')->nullable();
            $table->string('phone1', 32)->nullable();
            $table->string('phone2', 32)->nullable();
            $table->string('cellphone', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('birth_city')->nullable();
            $table->string('birth_department')->nullable();
            $table->boolean('is_foreigner')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('document_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_people');
    }
};
