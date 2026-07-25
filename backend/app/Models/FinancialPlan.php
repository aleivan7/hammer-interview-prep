<?php

namespace App\Models;

use Database\Factories\FinancialPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialPlan extends Model
{
    /** @use HasFactory<FinancialPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'needs_percent',
        'wants_percent',
        'savings_percent',
        'safety_buffer_cents',
        'monthly_income_cents',
    ];

    protected function casts(): array
    {
        return [
            'needs_percent' => 'integer',
            'wants_percent' => 'integer',
            'savings_percent' => 'integer',
            'safety_buffer_cents' => 'integer',
            'monthly_income_cents' => 'integer',
        ];
    }
}
