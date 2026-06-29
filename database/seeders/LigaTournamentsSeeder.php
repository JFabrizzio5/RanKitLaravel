<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class LigaTournamentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::where('email', '18jangel18@gmail.com')->first();
        
        if (!$user) {
            $this->command->error('No se encontró el usuario 18jangel18@gmail.com. Creando uno de prueba...');
            $userId = DB::table('users')->insertGetId([
                'name' => 'Jangel Admin',
                'email' => '18jangel18@gmail.com',
                'password' => bcrypt('password123'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $userId = $user->id;
        }

        $tournaments = [
            [
                'user_id' => $userId,
                'name' => 'Rankit League - Semana 1',
                'slug' => 'rankit-league-w1',
                'is_private' => 1,
                'scoring_format' => 'classic',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId,
                'name' => 'Rankit League - Semana 2',
                'slug' => 'rankit-league-w2',
                'is_private' => 1,
                'scoring_format' => 'classic',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId,
                'name' => 'Rankit League - Semana 3 (Repechaje)',
                'slug' => 'rankit-league-w3',
                'is_private' => 1,
                'scoring_format' => 'elimination',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $userId,
                'name' => 'Rankit League - GRAN FINAL',
                'slug' => 'rankit-league-w4',
                'is_private' => 1,
                'scoring_format' => 'classic',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($tournaments as $t) {
            DB::table('tournaments')->updateOrInsert(
                ['slug' => $t['slug']],
                $t
            );
        }

        $this->command->info('Torneos de Liga (Rankit League) creados exitosamente para 18jangel18@gmail.com');
    }
}
