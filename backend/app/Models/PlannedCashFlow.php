<?php

namespace App\Models;

use App\Enums\Bucket;
use Database\Factories\PlannedCashFlowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlannedCashFlow extends Model
{
    /** @use HasFactory<PlannedCashFlowFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'amount_cents',
        'kind',
        'due_on',
        'is_essential',
        'bucket',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'due_on' => 'date',
            'is_essential' => 'boolean',
            'bucket' => Bucket::class,
        ];
    }
}
