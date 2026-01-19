<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'course_id',
        'batch_id',
        'certificate_number',
        'issued_date',
        'final_grade',
        'grade_letter',
        'certificate_url',
        'is_printed',
        'printed_date',
        'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'printed_date' => 'date',
            'final_grade' => 'decimal:2',
            'is_printed' => 'boolean',
        ];
    }

    // Relationships
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    // Scopes
    public function scopePrinted($query)
    {
        return $query->where('is_printed', true);
    }

    public function scopeUnprinted($query)
    {
        return $query->where('is_printed', false);
    }

    public function scopeByLevel($query, $level)
    {
        return $query->whereHas('course', function ($q) use ($level) {
            $q->where('level', $level);
        });
    }

    // Helper methods
    public static function generateCertificateNumber($courseLevel, $year = null)
    {
        $year = $year ?? now()->year;
        $prefix = "GLD-{$courseLevel}-{$year}";
        
        $lastNumber = static::where('certificate_number', 'like', "{$prefix}%")
            ->latest('id')
            ->value('certificate_number');

        if ($lastNumber) {
            $lastSequence = (int) substr($lastNumber, -4);
            $newSequence = $lastSequence + 1;
        } else {
            $newSequence = 1;
        }

        return $prefix . '-' . str_pad($newSequence, 4, '0', STR_PAD_LEFT);
    }

    public static function createCertificate($studentId, $courseId, $batchId, $finalGrade, $issuedBy)
    {
        $course = Course::find($courseId);
        if (!$course) return null;

        $certificateNumber = self::generateCertificateNumber($course->level);
        
        $gradeLetter = self::calculateGradeLetter($finalGrade);

        return self::create([
            'student_id' => $studentId,
            'course_id' => $courseId,
            'batch_id' => $batchId,
            'certificate_number' => $certificateNumber,
            'issued_date' => now(),
            'final_grade' => $finalGrade,
            'grade_letter' => $gradeLetter,
            'issued_by' => $issuedBy,
        ]);
    }

    public static function calculateGradeLetter($grade)
    {
        if ($grade >= 90) return 'A+';
        if ($grade >= 80) return 'A';
        if ($grade >= 70) return 'B+';
        if ($grade >= 60) return 'B';
        if ($grade >= 50) return 'C+';
        if ($grade >= 40) return 'C';
        return 'F';
    }

    public function markAsPrinted()
    {
        $this->update([
            'is_printed' => true,
            'printed_date' => now(),
        ]);
    }

    public function generatePdfUrl()
    {
        // This would integrate with a PDF generation service
        // For now, return a placeholder
        return "/certificates/{$this->certificate_number}.pdf";
    }

    public function getQrCodeData()
    {
        return [
            'certificate_number' => $this->certificate_number,
            'student_name' => $this->student->name,
            'course' => $this->course->name . ' - ' . $this->course->level,
            'issued_date' => $this->issued_date->format('Y-m-d'),
            'verification_url' => url("/verify-certificate/{$this->certificate_number}"),
        ];
    }
}