<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Brand rename: NetSlip → VendSlip. Seeders only run on fresh installs, so text that was already
 * written to the database (template slugs and names, plan highlights, the super-admin's display
 * name) is renamed here. Issued slips are untouched: their signatures do not cover these fields.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const array TEMPLATE_SLUGS = [
        'netslip-classic-executive' => 'vendslip-classic-executive',
        'netslip-minimalist-social' => 'vendslip-minimalist-social',
    ];

    public function up(): void
    {
        $this->rename(self::TEMPLATE_SLUGS, 'NetSlip', 'VendSlip');
    }

    public function down(): void
    {
        $this->rename(array_flip(self::TEMPLATE_SLUGS), 'VendSlip', 'NetSlip');
    }

    /** @param array<string, string> $slugs */
    private function rename(array $slugs, string $from, string $to): void
    {
        DB::transaction(function () use ($slugs, $from, $to): void {
            foreach ($slugs as $old => $new) {
                if (! DB::table('templates')->where('slug', $new)->exists()) {
                    DB::table('templates')->where('slug', $old)->update(['slug' => $new]);
                }
            }
            foreach (DB::table('templates')->where('name', 'like', "%{$from}%")->orWhere('description', 'like', "%{$from}%")->get(['id', 'name', 'description']) as $row) {
                DB::table('templates')->where('id', $row->id)->update([
                    'name' => str_replace($from, $to, (string) $row->name),
                    'description' => $row->description === null ? null : str_replace($from, $to, (string) $row->description),
                ]);
            }
            foreach (DB::table('subscription_plans')->get(['id', 'highlights']) as $plan) {
                if (is_string($plan->highlights) && str_contains($plan->highlights, $from)) {
                    DB::table('subscription_plans')->where('id', $plan->id)->update(['highlights' => str_replace($from, $to, $plan->highlights)]);
                }
            }
            DB::table('users')->where('name', "{$from} Super Admin")->update(['name' => "{$to} Super Admin"]);
        });
    }
};
