<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;

    /*
     * TODO (Alejandro): Define which attributes may be mass-assigned.
     *
     * You will need this when the controller updates a transaction from
     * validated request input. Look up Eloquent "$fillable" in the docs.
     *
     * Do not put the finished answer here until you have written it yourself.
     */
    protected $fillable = [
        'category',
        'reviewed_at',
    ];

    /*
     * TODO (Alejandro): Define attribute casting.
     *
     * Tip: amount should behave like a decimal, transaction_date like a date,
     * and reviewed_at like a datetime. Look up Eloquent "$casts".
     *
     * Do not put the finished answer here until you have written it yourself.
     */
    protected function casts(): array
    {
        return [
            //
        ];
    }
}
