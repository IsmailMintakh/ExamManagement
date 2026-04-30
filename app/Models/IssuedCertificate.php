<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IssuedCertificate extends Model
{
    protected $table = 'certificates_issued';

    protected $fillable = [
        'exam_id', 'student_id', 'certificate_template_id', 'issued_by',
        'certificate_number', 'type', 'data', 'verification_code', 'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'issued_at' => 'datetime',
        ];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function template()
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
