<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeacherPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_teacher_earning_id',
        'amount',
        'payment_date',
        'reference',
        'notes',
    ];

    public function batchTeacherEarning()
    {
        return $this->belongsTo(BatchTeacherEarning::class);
    }

    public function batch()
    {
        return $this->batchTeacherEarning->batch();
    }

    public function teacher()
    {
        return $this->batchTeacherEarning->teacher();
    }
}
