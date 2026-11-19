<?php

namespace Database\Seeders;

use App\Models\BandwidthLimit;
use App\Models\InternetPackage;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /** name, minutes, MB, devices, price, down kbps, up kbps  (all editable in the UI) */
    private const DEFAULTS = [
        ['Hourly', 60, 500, 1, 0.50, 4000, 2000],
        ['Standard', 180, 1024, 1, 1.00, 5000, 2500],
        ['Premium', 360, 3072, 1, 2.00, 6000, 3000],
        ['Daily', 1440, 5120, 2, 3.00, 8000, 4000],
        ['Weekly', 10080, 20480, 2, 10.00, 10000, 5000],
        ['Monthly', 43200, 51200, 3, 30.00, 10000, 5000],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $i => [$name, $mins, $mb, $devices, $price, $down, $up]) {
            $pkg = InternetPackage::firstOrCreate(
                ['slug' => strtolower($name)],
                [
                    'name' => $name, 'duration_minutes' => $mins, 'data_limit_mb' => $mb,
                    'max_devices' => $devices, 'price' => $price, 'sort_order' => $i, 'is_active' => true,
                ]
            );
            BandwidthLimit::firstOrCreate(
                ['scope' => 'package', 'internet_package_id' => $pkg->id],
                ['download_kbps' => $down, 'upload_kbps' => $up]
            );
        }
    }
}
