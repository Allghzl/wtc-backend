<?php

namespace Database\Seeders;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\Track;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use JsonException;

class WebCurriculumResourceSeeder extends Seeder
{
    /** @return array<int, array<string, mixed>> */
    private function read(string $name): array
    {
        try {
            return json_decode(
                File::get(__DIR__ . '/resources/web_curriculum/' . $name . '.json'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new \RuntimeException("Invalid curriculum resource: {$name}.json", 0, $e);
        }
    }

    public function run(): void
    {
        $tracks = $this->read('tracks');
        $modules = $this->read('modules');
        $lessons = $this->read('lessons');
        $trackIds = [];
        $moduleIds = [];

        DB::transaction(function () use ($tracks, $modules, $lessons, &$trackIds, &$moduleIds): void {
            foreach ($tracks as $track) {
                $model = Track::updateOrCreate(
                    ['slug' => $track['slug']],
                    [
                        'title' => $track['title'],
                        'description' => $track['description'],
                        'order' => $track['order'],
                    ]
                );
                $trackIds[$track['key']] = $model->id;
            }

            foreach ($modules as $module) {
                $model = Module::updateOrCreate(
                    ['slug' => $module['slug']],
                    [
                        'track_id' => $trackIds[$module['track_key']],
                        'title' => $module['title'],
                        'description' => $module['description'],
                        'metadata' => $module['metadata'],
                        'order' => $module['order'],
                    ]
                );
                $moduleIds[$module['key']] = $model->id;
            }

            foreach ($lessons as $lesson) {
                Lesson::updateOrCreate(
                    ['slug' => $lesson['slug']],
                    [
                        'module_id' => $moduleIds[$lesson['module_key']],
                        'title' => $lesson['title'],
                        'description' => $lesson['description'],
                        'content' => json_encode(
                            $lesson['content'],
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
                        ),
                        'video_url' => null,
                        'duration' => $lesson['duration'],
                        'order' => $lesson['order'],
                    ]
                );
            }
        });

        $this->command?->info(sprintf(
            'Curriculum seeded: %d tracks, %d modules, %d lessons.',
            count($tracks), count($modules), count($lessons)
        ));
    }
}
