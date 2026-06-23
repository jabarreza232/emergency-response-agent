<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmergencyFacilitiesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Data Dummy Pemadam Kebakaran (Damkar Depok)
        DB::table('fire_stations')->insert([
            [
                'name' => 'Mako Damkar Kota Depok',
                'address' => 'Boulevard Grand Depok City, Kota Depok',
                'phone' => '021-77827280',
                'latitude' => -6.410943,
                'longitude' => 106.829012,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'UPT Damkar Cimanggis',
                'address' => 'Jl. Raya Bogor Km. 33, Cimanggis, Depok',
                'phone' => '021-87745313',
                'latitude' => -6.376121,
                'longitude' => 106.862534,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'UPT Damkar Cinere',
                'address' => 'Jl. Cinere Raya, Kec. Cinere, Depok',
                'phone' => '021-7543025',
                'latitude' => -6.342111,
                'longitude' => 106.787612,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        // 2. Data Dummy Polisi (Polres & Polsek Depok)
        DB::table('police_stations')->insert([
            [
                'name' => 'Polres Metro Depok',
                'address' => 'Jl. Margonda Raya No.14, Pancoran Mas, Depok',
                'phone' => '021-77202554',
                'latitude' => -6.398623,
                'longitude' => 106.825145,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Polsek Sukmajaya',
                'address' => 'Jl. Bahagia Raya No.1, Sukmajaya, Depok',
                'phone' => '021-77826623',
                'latitude' => -6.397143,
                'longitude' => 106.845612,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Polsek Beji',
                'address' => 'Jl. K.H.M. Usman No.35, Beji, Depok',
                'phone' => '021-7775535',
                'latitude' => -6.375545,
                'longitude' => 106.820899,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
