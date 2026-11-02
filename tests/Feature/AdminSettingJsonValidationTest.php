<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSettingJsonValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_type_five_saves_empty_cells_and_discards_extra_field_keys(): void
    {
        $payload = $this->validPayload();
        $payload['json_value']['fields'][0]['extra'] = '应被丢弃';

        $this->actingAs($this->createDeveloper(), 'admin')
            ->postJson('/api/admin/settings', $payload)
            ->assertCreated();

        $saved = Setting::query()
            ->where('setting_key', $payload['setting_key'])
            ->firstOrFail();

        $this->assertSame('', $saved->json_value['data'][0]['field_2']);
        $this->assertSame('', $saved->json_value['data'][1]['field_2']);
        $this->assertSame(
            ['key' => 'field_1', 'title' => '列 1'],
            $saved->json_value['fields'][0],
        );
        $this->assertSame(
            ['field_1', 'field_2', 'field_3', 'field_4'],
            array_keys($saved->json_value['data'][0]),
        );
    }

    public function test_type_five_rejects_a_field_without_key(): void
    {
        $payload = $this->validPayload();
        unset($payload['json_value']['fields'][0]['key']);

        $this->actingAs($this->createDeveloper(), 'admin')
            ->postJson('/api/admin/settings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('json_value.fields.0.key');
    }

    public function test_type_five_rejects_duplicate_field_keys(): void
    {
        $payload = $this->validPayload();
        $payload['json_value']['fields'][1]['key'] = 'field_1';

        $this->actingAs($this->createDeveloper(), 'admin')
            ->postJson('/api/admin/settings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('json_value.fields.1.key');
    }

    public function test_type_five_rejects_a_data_row_missing_a_field(): void
    {
        $payload = $this->validPayload();
        unset($payload['json_value']['data'][0]['field_2']);

        $this->actingAs($this->createDeveloper(), 'admin')
            ->postJson('/api/admin/settings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('json_value.data.0');
    }

    public function test_type_five_rejects_a_data_row_with_an_extra_field(): void
    {
        $payload = $this->validPayload();
        $payload['json_value']['data'][0]['unexpected'] = '多余字段';

        $this->actingAs($this->createDeveloper(), 'admin')
            ->postJson('/api/admin/settings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('json_value.data.0');
    }

    public function test_type_five_rejects_non_string_data_values(): void
    {
        $payload = $this->validPayload();
        $payload['json_value']['data'][0]['field_2'] = 123;

        $this->actingAs($this->createDeveloper(), 'admin')
            ->postJson('/api/admin/settings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('json_value.data.0.field_2');
    }

    private function validPayload(): array
    {
        return [
            'setting_key' => 'test_json_'.bin2hex(random_bytes(6)),
            'name' => 'JSON 格式测试',
            'type' => 5,
            'json_value' => [
                'fields' => [
                    ['key' => 'field_1', 'title' => '列 1'],
                    ['key' => 'field_2', 'title' => '列 2'],
                    ['key' => 'field_3', 'title' => '列 3'],
                    ['key' => 'field_4', 'title' => '列 4'],
                ],
                'data' => [
                    [
                        'field_1' => 'a',
                        'field_2' => '',
                        'field_3' => 'c',
                        'field_4' => 'd',
                    ],
                    [
                        'field_1' => 'a2',
                        'field_2' => '',
                        'field_3' => 'c2',
                        'field_4' => 'd2',
                    ],
                ],
            ],
        ];
    }

    private function createDeveloper(): Admin
    {
        $admin = Admin::create([
            'name' => '测试开发者',
            'email' => 'developer-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => 'password',
        ]);

        $role = new Role();
        $role->name = '测试开发者';
        $role->code = Role::DEVELOPER_CODE;
        $role->save();

        $admin->roles()->attach($role->id);

        return $admin->refresh();
    }
}
