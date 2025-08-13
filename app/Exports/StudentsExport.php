<?php

namespace App\Exports;

use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;


class StudentsExport implements FromCollection
{

    protected $studentsData;

    public function __construct(Collection $studentsData)
    {
        $this->studentsData = $studentsData;
    }

    public function collection()
    {
        return $this->studentsData;
    }
    public function headings(): array
    {
        return [
            'Application Number',
            'Candidate Name',
            'Father Name',
            'Mother Name',
            'DOB',
            'Gender',
            'Email',
            'Phone',
            'State',
            'District',
            'Subdivision',
            'PIN',
            'Board',
            'Exam Name',
            'Bank Name',
            'Account No',
            'Subjects',
            'Total Marks',
            'Obtained Marks',
            'Payment Amount',
            'Payment Date',
            'Payment Status',
        ];
    }
}
