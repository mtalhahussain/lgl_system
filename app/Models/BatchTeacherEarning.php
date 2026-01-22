<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BatchTeacherEarning extends Model
{
    use HasFactory;

    public function teacherPayments()
    {
        return $this->hasMany(\App\Models\TeacherPayment::class);
    }
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'teacher_id',
        'salary_type',
        'salary_amount',
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
