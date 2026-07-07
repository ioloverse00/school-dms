<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentType;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Academic Documents
        DocumentType::create(['name' => 'Transcript of Records', 'category' => 'academic', 'weight' => 4]);
        DocumentType::create(['name' => 'Certificate of Enrollment', 'category' => 'academic', 'weight' => 2]);
        DocumentType::create(['name' => 'Diploma Copy', 'category' => 'academic', 'weight' => 3, 'alumni_only' => true]);
        DocumentType::create(['name' => 'Certificate of Graduation', 'category' => 'academic', 'weight' => 3]);
        DocumentType::create(['name' => 'Certificate of Grades', 'category' => 'academic', 'weight' => 2]);

        // Certification Documents
        DocumentType::create(['name' => 'Good Moral Certificate', 'category' => 'certification', 'weight' => 1]);
        DocumentType::create(['name' => 'Certificate of Attendance', 'category' => 'certification', 'weight' => 1]);
        DocumentType::create(['name' => 'Certificate of Completion', 'category' => 'certification', 'weight' => 2]);
        DocumentType::create(['name' => 'Certificate of Eligibility', 'category' => 'certification', 'weight' => 2]);
        DocumentType::create(['name' => 'Certificate of Honorable Dismissal', 'category' => 'certification', 'weight' => 2]);
        DocumentType::create(['name' => 'Certificate of Residency', 'category' => 'certification', 'weight' => 1]);
    }
}