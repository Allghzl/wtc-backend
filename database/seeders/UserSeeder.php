<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates 150-200 users with realistic Indonesian names.
     * All users get a consistent development password: 'password'
     * Email pattern: {name-slug}@example.test
     */
    public function run(): void
    {
        // Indonesian male first names
        $maleFirstNames = [
            'Ahmad', 'Muhammad', 'Dimas', 'Raka', 'Fajar', 'Rizky', 'Arief', 'Bayu',
            'Andi', 'Budi', 'Cahya', 'Dedi', 'Eko', 'Fauzan', 'Galih', 'Hadi',
            'Ilham', 'Joko', 'Kemal', 'Lukman', 'Mahendra', 'Nur', 'Omar', 'Prasetya',
            'Qori', 'Rama', 'Satria', 'Taufik', 'Umar', 'Wahyu', 'Yusuf', 'Zaki',
            'Aditya', 'Bagus', 'Candra', 'Doni', 'Efendi', 'Fahmi', 'Gilang', 'Hendra',
        ];

        // Indonesian female first names
        $femaleFirstNames = [
            'Siti', 'Nadia', 'Aulia', 'Putri', 'Dewi', 'Intan', 'Rahma', 'Lestari',
            'Ayu', 'Bella', 'Citra', 'Dina', 'Eka', 'Fitri', 'Gita', 'Hana',
            'Indah', 'Julia', 'Kartika', 'Laila', 'Maya', 'Nurul', 'Olivia', 'Permata',
            'Qonita', 'Rani', 'Safira', 'Tania', 'Ulfa', 'Wulan', 'Yasmin', 'Zahra',
            'Anisa', 'Bunga', 'Clarissa', 'Devi', 'Elsa', 'Farah', 'Gisela', 'Hasna',
        ];

        // Indonesian last names
        $lastNames = [
            'Pratama', 'Saputra', 'Wijaya', 'Santoso', 'Kusuma', 'Nugroho', 'Permana', 'Ramadhan',
            'Maulana', 'Hidayat', 'Setiawan', 'Putra', 'Wibowo', 'Hartono', 'Prasetyo', 'Firmansyah',
            'Adiputra', 'Mahardika', 'Kurniawan', 'Hakim', 'Rahman', 'Wahyudi', 'Hermawan', 'Gunawan',
            'Purnomo', 'Suryanto', 'Anwar', 'Saputri', 'Wulandari', 'Anggraini', 'Rahmawati', 'Lestari',
            'Maharani', 'Azzahra', 'Syafira', 'Hasanah', 'Khoiriyah', 'Safitri', 'Febriani', 'Oktaviani',
        ];

        $users = [];
        $usedNames = [];
        $usedEmails = [];
        $password = Hash::make('password');

        // Target: 180 users
        $targetCount = 180;
        $created = 0;

        while ($created < $targetCount) {
            // Randomly pick male or female name
            $isMale = rand(0, 1) === 0;
            $firstName = $isMale
                ? $maleFirstNames[array_rand($maleFirstNames)]
                : $femaleFirstNames[array_rand($femaleFirstNames)];

            $lastName = $lastNames[array_rand($lastNames)];
            $fullName = $firstName . ' ' . $lastName;

            // Skip if name already used
            if (in_array($fullName, $usedNames)) {
                continue;
            }

            $slug = Str::slug($fullName);
            $email = $slug . '@example.test';

            // Skip if email already used
            if (in_array($email, $usedEmails)) {
                continue;
            }

            $usedNames[] = $fullName;
            $usedEmails[] = $email;

            // Mostly local provider, some pinat for variety
            $provider = (rand(1, 10) <= 8) ? 'local' : 'pinat';

            $users[] = [
                'id' => (string) Str::uuid(),
                'puid' => $provider === 'pinat' ? (string) Str::uuid() : null,
                'name' => $fullName,
                'email' => $email,
                'password' => $password,
                'provider' => $provider,
                'avatar' => null,
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $created++;
        }

        // Bulk insert for performance
        foreach (array_chunk($users, 100) as $chunk) {
            User::insert($chunk);
        }

        $this->command->info('✅ Users seeded: ' . count($users));
        $this->command->info('   📧 Email pattern: {name-slug}@example.test');
        $this->command->info('   🔑 Password (all users): password');
        $this->command->info('   🔐 Provider distribution: ~80% local, ~20% pinat');
    }
}
