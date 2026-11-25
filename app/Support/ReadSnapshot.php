<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;

class ReadSnapshot
{
    public static function run(Closure $read): mixed
    {
        // El llamador conserva el aislamiento de una transacción que ya haya iniciado.
        if (DB::transactionLevel() > 0) {
            return $read();
        }

        return DB::transaction(function () use ($read) {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');

            return $read();
        });
    }
}
