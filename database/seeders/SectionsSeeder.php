<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;

class SectionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sections = [
            [
                'name' => 'افتتاحية العدد',
                'slug' => 'editorial-opening',
                'description' => 'افتتاحية العدد',
            ],
            [
                'name' => 'قاموس المصطلحات',
                'slug' => 'glossary',
                'description' => 'قاموس المصطلحات',
            ],
            [
                'name' => 'بروفايل (شخصيات صوفية )',
                'slug' => 'profiles',
                'description' => 'بروفايل (شخصيات صوفية )',
            ],
            [
                'name' => 'بالأرقام (إحصائيات وتحليلات)',
                'slug' => 'stats',
                'description' => 'بالأرقام (إحصائيات وتحليلات)',
            ],
            [
                'name' => 'خبر وتعليق (أخبار الصوفية)',
                'slug' => 'news',
                'description' => 'خبر وتعليق (أخبار الصوفية)',
            ],
            [
                'name' => 'شبهات تحت المجهر (شبهات وردود)',
                'slug' => 'refutations',
                'description' => 'شبهات تحت المجهر (شبهات وردود)',
            ],
            [
                'name' => 'من الأرشيف (وثائق ومحاضر)',
                'slug' => 'archive',
                'description' => 'من الأرشيف (وثائق ومحاضر)',
            ],
            [
                'name' => 'محطات تاريخية (تاريخ الصوفية)',
                'slug' => 'history',
                'description' => 'محطات تاريخية (تاريخ الصوفية)',
            ],
            [
                'name' => 'مكتبة العدد (مؤلفات مفيدة)',
                'slug' => 'library',
                'description' => 'مكتبة العدد (مؤلفات مفيدة)',
            ],
        ];

        foreach ($sections as $section) {
            Section::updateOrCreate(
                ['slug' => $section['slug']],
                [
                    'name' => $section['name'],
                    'description' => $section['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
