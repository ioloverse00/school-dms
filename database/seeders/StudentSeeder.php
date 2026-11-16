<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo seeders are restricted to local and testing environments.');
        }

        $students = [
            // Regular students
            ['student_id' => '210113001', 'name' => 'Reyes, Maria Santos',         'email' => 'reyes.maria@school.edu',       'course' => 'BS Computer Science',       'type' => 'regular',    'year' => null],
            ['student_id' => '210113002', 'name' => 'Cruz, Jose Manuel',           'email' => 'cruz.jose@school.edu',         'course' => 'BS Information Technology', 'type' => 'regular',    'year' => null],
            ['student_id' => '210113003', 'name' => 'Garcia, Ana Liza',            'email' => 'garcia.ana@school.edu',        'course' => 'BS Nursing',                 'type' => 'regular',    'year' => null],
            ['student_id' => '210113004', 'name' => 'Mendoza, Carlo Miguel',       'email' => 'mendoza.carlo@school.edu',     'course' => 'BS Education',               'type' => 'regular',    'year' => null],
            ['student_id' => '210113005', 'name' => 'Torres, Jasmine Rose',        'email' => 'torres.jasmine@school.edu',    'course' => 'BS Psychology',              'type' => 'regular',    'year' => null],
            ['student_id' => '210113006', 'name' => 'Lim, Patrick Andrei',         'email' => 'lim.patrick@school.edu',       'course' => 'BS Accountancy',             'type' => 'regular',    'year' => null],
            ['student_id' => '210113007', 'name' => 'Dela Cruz, Hannah Grace',     'email' => 'delacruz.hannah@school.edu',   'course' => 'BS Computer Science',       'type' => 'regular',    'year' => null],
            ['student_id' => '210113008', 'name' => 'Villanueva, Renz Carlo',      'email' => 'villanueva.renz@school.edu',   'course' => 'BS Civil Engineering',       'type' => 'regular',    'year' => null],
            ['student_id' => '210113009', 'name' => 'Santos, Lovely Anne',         'email' => 'santos.lovely@school.edu',     'course' => 'BS Nursing',                 'type' => 'regular',    'year' => null],
            ['student_id' => '210113010', 'name' => 'Aquino, Mark Jeffry',         'email' => 'aquino.mark@school.edu',       'course' => 'BS Information Technology', 'type' => 'regular',    'year' => null],
            ['student_id' => '210113011', 'name' => 'Bautista, Kyla Marie',        'email' => 'bautista.kyla@school.edu',     'course' => 'BS Psychology',              'type' => 'regular',    'year' => null],
            ['student_id' => '210113012', 'name' => 'Castillo, Diego Reyes',       'email' => 'castillo.diego@school.edu',    'course' => 'BS Accountancy',             'type' => 'regular',    'year' => null],
            ['student_id' => '210113013', 'name' => 'Ramos, Trisha Joy',           'email' => 'ramos.trisha@school.edu',      'course' => 'BS Education',               'type' => 'regular',    'year' => null],
            ['student_id' => '210113014', 'name' => 'Flores, Ivan Luis',           'email' => 'flores.ivan@school.edu',       'course' => 'BS Civil Engineering',       'type' => 'regular',    'year' => null],
            ['student_id' => '210113015', 'name' => 'Navarro, Shaina Mae',         'email' => 'navarro.shaina@school.edu',    'course' => 'BS Computer Science',       'type' => 'regular',    'year' => null],
            ['student_id' => '210113016', 'name' => 'Fernandez, Angelo King',      'email' => 'fernandez.angelo@school.edu',  'course' => 'BS Information Technology', 'type' => 'regular',    'year' => null],

            // Graduating students
            ['student_id' => '200113017', 'name' => 'Morales, Bianca Nicole',      'email' => 'morales.bianca@school.edu',    'course' => 'BS Computer Science',       'type' => 'graduating', 'year' => null],
            ['student_id' => '200113018', 'name' => 'Pascual, Nico Javier',        'email' => 'pascual.nico@school.edu',      'course' => 'BS Nursing',                 'type' => 'graduating', 'year' => null],
            ['student_id' => '200113019', 'name' => 'Aguilar, Christine Joy',      'email' => 'aguilar.christine@school.edu', 'course' => 'BS Accountancy',             'type' => 'graduating', 'year' => null],
            ['student_id' => '200113020', 'name' => 'Espinosa, Luis Gabriel',      'email' => 'espinosa.luis@school.edu',     'course' => 'BS Civil Engineering',       'type' => 'graduating', 'year' => null],
            ['student_id' => '200113021', 'name' => 'Domingo, Alyssa Faye',        'email' => 'domingo.alyssa@school.edu',    'course' => 'BS Psychology',              'type' => 'graduating', 'year' => null],
            ['student_id' => '200113022', 'name' => 'Concepcion, Ryan Joseph',     'email' => 'concepcion.ryan@school.edu',   'course' => 'BS Education',               'type' => 'graduating', 'year' => null],
            ['student_id' => '200113023', 'name' => 'Valdez, Patricia Mae',        'email' => 'valdez.patricia@school.edu',   'course' => 'BS Information Technology', 'type' => 'graduating', 'year' => null],
            ['student_id' => '200113024', 'name' => 'Gutierrez, Adrian Rex',       'email' => 'gutierrez.adrian@school.edu',  'course' => 'BS Computer Science',       'type' => 'graduating', 'year' => null],
            ['student_id' => '200113025', 'name' => 'Salazar, Nicole Anne',        'email' => 'salazar.nicole@school.edu',    'course' => 'BS Nursing',                 'type' => 'graduating', 'year' => null],
            ['student_id' => '200113026', 'name' => 'Ocampo, Jericho Paul',        'email' => 'ocampo.jericho@school.edu',    'course' => 'BS Accountancy',             'type' => 'graduating', 'year' => null],
            ['student_id' => '200113027', 'name' => 'Miranda, Sophia Claire',      'email' => 'miranda.sophia@school.edu',    'course' => 'BS Civil Engineering',       'type' => 'graduating', 'year' => null],
            ['student_id' => '200113028', 'name' => 'Sandoval, Elijah James',      'email' => 'sandoval.elijah@school.edu',   'course' => 'BS Education',               'type' => 'graduating', 'year' => null],
            ['student_id' => '200113029', 'name' => 'Cabrera, Angelica Faith',     'email' => 'cabrera.angelica@school.edu',  'course' => 'BS Psychology',              'type' => 'graduating', 'year' => null],
            ['student_id' => '200113030', 'name' => 'Reyes, Dominic Xavier',       'email' => 'reyes.dominic@school.edu',     'course' => 'BS Information Technology', 'type' => 'graduating', 'year' => null],
            ['student_id' => '200113031', 'name' => 'Dela Rosa, Camille Anne',     'email' => 'delarosa.camille@school.edu',  'course' => 'BS Computer Science',       'type' => 'graduating', 'year' => null],
            ['student_id' => '200113032', 'name' => 'Perez, Aldrich Miguel',       'email' => 'perez.aldrich@school.edu',     'course' => 'BS Accountancy',             'type' => 'graduating', 'year' => null],

            // Alumni
            ['student_id' => '180113033', 'name' => 'Jimenez, Rona Belle',         'email' => 'jimenez.rona@school.edu',      'course' => 'BS Computer Science',       'type' => 'alumni',     'year' => 2022],
            ['student_id' => '180113034', 'name' => 'Marquez, Kevin Dale',         'email' => 'marquez.kevin@school.edu',     'course' => 'BS Nursing',                 'type' => 'alumni',     'year' => 2022],
            ['student_id' => '180113035', 'name' => 'Sta. Ana, Ericka Joy',        'email' => 'staana.ericka@school.edu',     'course' => 'BS Education',               'type' => 'alumni',     'year' => 2023],
            ['student_id' => '180113036', 'name' => 'Velasco, Jerome Anton',       'email' => 'velasco.jerome@school.edu',    'course' => 'BS Accountancy',             'type' => 'alumni',     'year' => 2021],
            ['student_id' => '180113037', 'name' => 'Hernandez, Lara Mae',         'email' => 'hernandez.lara@school.edu',    'course' => 'BS Civil Engineering',       'type' => 'alumni',     'year' => 2023],
            ['student_id' => '180113038', 'name' => 'Lopez, Emmanuel John',        'email' => 'lopez.emmanuel@school.edu',    'course' => 'BS Psychology',              'type' => 'alumni',     'year' => 2022],
            ['student_id' => '180113039', 'name' => 'Dizon, Kristine Paula',       'email' => 'dizon.kristine@school.edu',    'course' => 'BS Information Technology', 'type' => 'alumni',     'year' => 2021],
            ['student_id' => '180113040', 'name' => 'Tolentino, Arvin Marc',       'email' => 'tolentino.arvin@school.edu',   'course' => 'BS Computer Science',       'type' => 'alumni',     'year' => 2020],
            ['student_id' => '180113041', 'name' => 'Padilla, Camilla Rose',       'email' => 'padilla.camilla@school.edu',   'course' => 'BS Nursing',                 'type' => 'alumni',     'year' => 2023],
            ['student_id' => '180113042', 'name' => 'Soriano, Rafael Miguel',      'email' => 'soriano.rafael@school.edu',    'course' => 'BS Accountancy',             'type' => 'alumni',     'year' => 2020],
            ['student_id' => '180113043', 'name' => 'Vergara, Sheila Grace',       'email' => 'vergara.sheila@school.edu',    'course' => 'BS Education',               'type' => 'alumni',     'year' => 2021],
            ['student_id' => '180113044', 'name' => 'Buenaventura, Alec John',     'email' => 'buenaventura.alec@school.edu', 'course' => 'BS Civil Engineering',       'type' => 'alumni',     'year' => 2022],
            ['student_id' => '180113045', 'name' => 'Macapagal, Diana Lynn',       'email' => 'macapagal.diana@school.edu',   'course' => 'BS Psychology',              'type' => 'alumni',     'year' => 2020],
            ['student_id' => '180113046', 'name' => 'Ejercito, Ronald Jay',        'email' => 'ejercito.ronald@school.edu',   'course' => 'BS Information Technology', 'type' => 'alumni',     'year' => 2023],
            ['student_id' => '180113047', 'name' => 'Alcantara, Mariz Faith',      'email' => 'alcantara.mariz@school.edu',   'course' => 'BS Computer Science',       'type' => 'alumni',     'year' => 2021],
            ['student_id' => '180113048', 'name' => 'Evangelista, Sean Patrick',   'email' => 'evangelista.sean@school.edu',  'course' => 'BS Nursing',                 'type' => 'alumni',     'year' => 2020],
            ['student_id' => '210113049', 'name' => 'Mercado, Lovely Grace',       'email' => 'mercado.lovely@school.edu',    'course' => 'BS Psychology',              'type' => 'regular',    'year' => null],
            ['student_id' => '200113050', 'name' => 'Delos Santos, Marco Paulo',   'email' => 'delossantos.marco@school.edu', 'course' => 'BS Civil Engineering',       'type' => 'graduating', 'year' => null],
        ];

        foreach ($students as $data) {
            $user = User::create([
                'student_id'     => $data['student_id'],
                'name'           => $data['name'],
                'email'          => $data['email'],
                'password'       => Hash::make(\Illuminate\Support\Str::random(40)),
                'course'         => $data['course'],
                'student_type'   => $data['type'],
                'year_graduated' => $data['year'],
            ]);
            $user->assignRole('student');
        }
    }
}
