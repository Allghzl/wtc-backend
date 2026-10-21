<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `order` is unique per parent, so the unique rule needs a `where` clause. An
 * empty column name there (e.g. the old `->withoutTrashed(false)`) produced
 * `"" is null` and Postgres rejected the query with SQLSTATE 42601.
 */
class LessonOrderValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'teacher']);
        $this->teacher = User::factory()->withProfile()->create();
        $this->teacher->profile->roles()->attach($role);

        $this->module = Module::factory()->create(['order' => 1]);
    }

    public function test_it_updates_a_lesson_with_its_own_order(): void
    {
        $lesson = Lesson::factory()->create(['module_id' => $this->module->id, 'order' => 1]);

        $this->actingAs($this->teacher)
            ->patchJson("/api/lessons/{$lesson->slug}", ['order' => 1, 'title' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed');
    }

    public function test_it_rejects_a_duplicate_order_within_the_module(): void
    {
        Lesson::factory()->create(['module_id' => $this->module->id, 'order' => 1]);
        $lesson = Lesson::factory()->create(['module_id' => $this->module->id, 'order' => 2]);

        $this->actingAs($this->teacher)
            ->patchJson("/api/lessons/{$lesson->slug}", ['order' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order']);
    }

    public function test_it_updates_a_module_with_its_own_order(): void
    {
        $this->actingAs($this->teacher)
            ->patchJson("/api/modules/{$this->module->slug}", ['order' => 1, 'title' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed');
    }
}
