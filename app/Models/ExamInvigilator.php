<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamInvigilator extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id', 'exam_schedule_id', 'exam_room_id', 'user_id', 'role',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function schedule()
    {
        return $this->belongsTo(ExamSchedule::class, 'exam_schedule_id');
    }

    public function room()
    {
        return $this->belongsTo(ExamRoom::class, 'exam_room_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
