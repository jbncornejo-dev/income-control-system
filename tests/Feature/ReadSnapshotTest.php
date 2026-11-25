<?php

namespace Tests\Feature;

use App\Support\ReadSnapshot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReadSnapshotTest extends TestCase
{
    public function test_a_concurrent_insert_does_not_change_an_export_snapshot(): void
    {
        $table = 'snapshot_probe_'.bin2hex(random_bytes(4));
        Schema::create($table, fn (Blueprint $blueprint) => $blueprint->integer('value'));
        config(['database.connections.snapshot_writer' => config('database.connections.'.config('database.default'))]);
        try {
            DB::table($table)->insert(['value' => 1]);
            $counts = ReadSnapshot::run(function () use ($table) {
                $before = DB::table($table)->count();
                $this->assertSame('on', DB::selectOne('SHOW transaction_read_only')->transaction_read_only);
                DB::connection('snapshot_writer')->table($table)->insert(['value' => 2]);

                return [$before, DB::table($table)->count()];
            });
            $this->assertSame([1, 1], $counts);
            $this->assertSame(2, DB::table($table)->count());
            $this->assertSame(0, DB::transactionLevel());
        } finally {
            DB::purge('snapshot_writer');
            Schema::drop($table);
        }
    }

    public function test_an_existing_transaction_keeps_its_isolation_and_ownership(): void
    {
        DB::beginTransaction();
        try {
            $isolation = DB::selectOne('SHOW transaction_isolation')->transaction_isolation;
            $result = ReadSnapshot::run(fn () => DB::selectOne('SHOW transaction_isolation')->transaction_isolation);
            $this->assertSame($isolation, $result);
            $this->assertSame(1, DB::transactionLevel());
        } finally {
            DB::rollBack();
        }
    }
}
