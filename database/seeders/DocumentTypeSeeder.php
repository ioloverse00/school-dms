<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentType;

class DocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Academic Documents
        DocumentType::firstOrCreate(['name' => 'Transcript of Records'], ['category' => 'academic', 'weight' => 4]);
        DocumentType::firstOrCreate(['name' => 'Certificate of Enrollment'], ['category' => 'academic', 'weight' => 2]);
        DocumentType::firstOrCreate(['name' => 'Diploma Copy'], ['category' => 'academic', 'weight' => 3, 'alumni_only' => true]);
        DocumentType::firstOrCreate(['name' => 'Certificate of Graduation'], ['category' => 'academic', 'weight' => 3]);
        DocumentType::firstOrCreate(['name' => 'Certificate of Grades'], ['category' => 'academic', 'weight' => 2]);

        // Certification Documents
        DocumentType::firstOrCreate(['name' => 'Good Moral Certificate'], ['category' => 'certification', 'weight' => 1]);
        DocumentType::firstOrCreate(['name' => 'Certificate of Attendance'], ['category' => 'certification', 'weight' => 1]);
        DocumentType::firstOrCreate(['name' => 'Certificate of Completion'], ['category' => 'certification', 'weight' => 2]);
        DocumentType::firstOrCreate(['name' => 'Certificate of Eligibility'], ['category' => 'certification', 'weight' => 2]);
        DocumentType::firstOrCreate(['name' => 'Certificate of Honorable Dismissal'], ['category' => 'certification', 'weight' => 2]);
        DocumentType::firstOrCreate(['name' => 'Certificate of Residency'], ['category' => 'certification', 'weight' => 1]);
    }
}