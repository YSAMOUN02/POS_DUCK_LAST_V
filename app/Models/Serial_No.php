<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * One counter row per document type — the single source of transaction numbers.
 *
 * Everything used to derive its next number by reading the newest row of the
 * target table and adding one. That re-issued a number as soon as the newest
 * document was deleted, and two users saving at the same moment read the same
 * "last" row and minted the same number.
 *
 * Returns are deliberately absent from TYPES: a purchase return or sale return
 * posts against the ORIGINAL document number, so it never draws a new one.
 */
class Serial_No extends Model
{
    protected $table = 'serial_no'; // 👈 important (not default plural)

    protected $fillable = [
        'prefix',
        'type',
        'current_no',
        'last_reset_date',
    ];

    protected $casts = [
        'last_reset_date' => 'date',
    ];

    /**
     * type => [prefix, zero-padding width]
     *
     * Formatted as PREFIX + 2-digit year + '-' + padded counter, e.g. SO26-0001.
     */
    public const TYPES = [
        'sale_order'    => ['SO',   4],
        'invoice'       => ['INV',  4],
        'delivery_note' => ['DN',   4],
        'purchase'      => ['GRN',  4],
        'transfer'      => ['TO',   4],
        'adjustment'    => ['ADJ',  4],
        'expense'       => ['EXP',  4],
    ];

    /**
     * Issue the next number for a document type.
     *
     * The counter row is locked for the duration, so concurrent saves queue
     * instead of colliding. Safe inside an outer transaction — Laravel nests it
     * as a savepoint.
     */
    public static function next(string $type): string
    {
        if (! isset(self::TYPES[$type])) {
            throw new InvalidArgumentException(
                "Unknown document type '{$type}'. Known types: " . implode(', ', array_keys(self::TYPES))
            );
        }

        [$prefix, $width] = self::TYPES[$type];

        return DB::transaction(function () use ($type, $prefix, $width) {
            $year = Carbon::now()->format('y');

            $serial = static::where('type', $type)->lockForUpdate()->first();

            if (! $serial) {
                static::create([
                    'prefix'          => $prefix,
                    'type'            => $type,
                    'current_no'      => 0,
                    'last_reset_date' => now(),
                ]);

                // Re-read under the lock, so the increment below is still
                // serialised if two requests created the row at once.
                $serial = static::where('type', $type)->lockForUpdate()->first();
            }

            // The year is part of the number, so the counter restarts each year
            // — otherwise SO27- would carry on from where SO26- left off.
            if ($serial->last_reset_date && $serial->last_reset_date->format('y') !== $year) {
                $serial->current_no = 0;
            }

            $serial->current_no += 1;
            $serial->prefix = $prefix;   // keep the stored prefix in step with TYPES
            $serial->last_reset_date = now();
            $serial->save();

            return $prefix . $year . '-' . str_pad($serial->current_no, $width, '0', STR_PAD_LEFT);
        });
    }
}
