<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchExam extends Model
{
    /** Fixed CEFR section names, in display order. */
    public const SECTIONS = ['Hören', 'Lesen', 'Schreiben', 'Sprechen'];

    protected $fillable = ['batch_id', 'name', 'type', 'exam_date', 'status'];

    protected function casts(): array
    {
        return ['exam_date' => 'date'];
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function sections()
    {
        return $this->hasMany(ExamSection::class)->orderBy('sort');
    }

    public function results()
    {
        return $this->hasMany(StudentExamResult::class);
    }

    /**
     * Create the four fixed sections for this exam.
     * $marks is keyed by section name => ['max' => x, 'pass' => y].
     */
    public function seedDefaultSections(array $marks): void
    {
        foreach (self::SECTIONS as $i => $name) {
            $this->sections()->create([
                'name' => $name,
                'max_marks' => $marks[$name]['max'],
                'passing_marks' => $marks[$name]['pass'],
                'sort' => $i + 1,
            ]);
        }
    }
}
