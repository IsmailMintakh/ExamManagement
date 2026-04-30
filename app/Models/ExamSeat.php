<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamSeat extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id', 'exam_room_id', 'student_id',
        'seat_number', 'row_num', 'col_num',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function room()
    {
        return $this->belongsTo(ExamRoom::class, 'exam_room_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
