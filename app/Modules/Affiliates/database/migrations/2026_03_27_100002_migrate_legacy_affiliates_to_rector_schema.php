<?php

// DOCUMENTO_RECTOR §4 Grupo B — migración affiliates → core_people + afl_affiliates; pila_liquidations.affiliate_id → afl_affiliates

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('affiliates')) {
            return;
        }

        DB::transaction(function (): void {
            if (Schema::hasTable('pila_liquidations') && Schema::hasColumn('pila_liquidations', 'affiliate_id')) {
                Schema::table('pila_liquidations', function (Blueprint $table) {
                    $table->dropForeign(['affiliate_id']);
                });
            }

            $rows = DB::table('affiliates')->orderBy('id')->get();

            foreach ($rows as $row) {
                $personId = DB::table('core_people')->insertGetId([
                    'document_number' => $row->document_number,
                    'first_name' => $row->first_name,
                    'first_surname' => $row->last_name,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);

                DB::table('afl_affiliates')->insert([
                    'id' => $row->id,
                    'person_id' => $personId,
                    'client_type' => 'SERVICONLI',
                    'status_id' => null,
                    'has_discount' => false,
                    'is_type_51' => false,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }

            Schema::drop('affiliates');

            if (Schema::hasTable('pila_liquidations') && Schema::hasColumn('pila_liquidations', 'affiliate_id')) {
                Schema::table('pila_liquidations', function (Blueprint $table) {
                    $table->foreign('affiliate_id')->references('id')->on('afl_affiliates')->nullOnDelete();
                });
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('affiliates')) {
            return;
        }

        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->string('document_number', 32)->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('pila_liquidations') && Schema::hasColumn('pila_liquidations', 'affiliate_id')) {
            Schema::table('pila_liquidations', function (Blueprint $table) {
                $table->dropForeign(['affiliate_id']);
            });
        }

        DB::transaction(function (): void {
            $affiliates = DB::table('afl_affiliates')->orderBy('id')->get();
            foreach ($affiliates as $a) {
                $person = DB::table('core_people')->where('id', $a->person_id)->first();
                if ($person === null) {
                    continue;
                }
                DB::table('affiliates')->insert([
                    'id' => $a->id,
                    'document_number' => $person->document_number,
                    'first_name' => $person->first_name,
                    'last_name' => $person->first_surname,
                    'created_at' => $a->created_at,
                    'updated_at' => $a->updated_at,
                ]);
            }
        });

        if (Schema::hasTable('pila_liquidations') && Schema::hasColumn('pila_liquidations', 'affiliate_id')) {
            Schema::table('pila_liquidations', function (Blueprint $table) {
                $table->foreign('affiliate_id')->references('id')->on('affiliates')->nullOnDelete();
            });
        }
    }
};
