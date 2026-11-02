<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SettingController extends Controller
{
    /**
     * 设置列表
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $request->validate([
            'keyword' => ['nullable', 'string', 'max:150'],
            'type' => ['nullable', 'integer', 'between:0,5'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $query = Setting::query()
            ->whereBetween('type', [1, 5]);

        if ($request->filled('keyword')) {
            $keyword = $request->input('keyword');

            $query->where(function ($query) use ($keyword) {
                $query->where('setting_key', 'like', "%{$keyword}%")
                    ->orWhere('name', 'like', "%{$keyword}%");
            });
        }

        $type = $request->integer('type', 0);

        if ($type > 0) {
            $query->where('type', $type);
        }

        $data = $query->orderByDesc('id')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'message' => '获取成功',
            'data' => $data,
        ]);
    }

    /**
     * 新增设置
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $data = $this->validateSetting($request);
        $setting = Setting::create($data);
        return response()->json([
            'message' => '创建成功',
            'data' => $setting,
        ], 201);
    }

    /**
     * 设置详情
     */
    public function show(Setting $setting)
    {
        return response()->json([
            'message' => '获取成功',
            'data' => $setting,
        ]);
    }

    /**
     * 修改设置
     *
     * @param Request $request
     * @param Setting $setting
     * @return JsonResponse
     */
    public function update(Request $request, Setting $setting)
    {
        $data = $this->validateSetting($request, $setting);
        $setting->update($data);
        return response()->json([
            'message' => '修改成功',
            'data' => $setting->fresh(),
        ]);
    }

    /**
     * 删除设置
     *
     * @param Setting $setting
     * @return JsonResponse
     */
    public function destroy(Setting $setting)
    {
        $setting->delete();
        return response()->json([
            'message' => '删除成功',
        ]);
    }

    /**
     * 验证设置数据
     *
     * @param Request $request
     * @param Setting|null $setting
     * @return array
     */
    private function validateSetting(Request $request, ?Setting $setting = null)
    {
        //type=5时, json_value最终保存的数组结构如下：
        // {
        //     "fields": [
        //         {"title": "列 1", "key": "field_1"},
        //         {"title": "列 2", "key": "field_2"},
        //         {"title": "列 3", "key": "field_3"},
        //         {"title": "列 4", "key": "field_4"},
        //     ],
        //     "data": [
        //         {"field_1": "value1", "field_2": "value2", "field_3": "value3", "field_4": "value4"},
        //         {"field_1": "value1x", "field_2": "value2y", "field_3": "value3c", "field_4": "value4z"}
        //     ]
        // }
        // type=6的的情况不会进来这里, 需另外单独处理, 因为type=6是特殊结构, 当前的常规增/删/改查/列表, 不受理这种特殊结构

        $type = (int) $request->input('type');

        $rules = [
            'setting_key' => [
                'required',
                'string',
                'max:100',
                Rule::unique('settings', 'setting_key')
                    ->ignore($setting?->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'integer', 'between:1,5'],
        ];

        if ($type === 5) {
            $jsonValue = $request->input('json_value');

            if (is_array($jsonValue)) {
                if (is_array($jsonValue['fields'] ?? null)) {
                    $jsonValue['fields'] = array_map(
                        static function ($field) {
                            if (!is_array($field)) {
                                return $field;
                            }

                            return [
                                'key' => $field['key'] ?? null,
                                'title' => $field['title'] ?? null,
                            ];
                        },
                        $jsonValue['fields'],
                    );
                }

                if (is_array($jsonValue['data'] ?? null)) {
                    foreach ($jsonValue['data'] as $rowIndex => $row) {
                        if (!is_array($row)) {
                            continue;
                        }

                        foreach ($row as $key => $value) {
                            if ($value === null) {
                                $jsonValue['data'][$rowIndex][$key] = '';
                            }
                        }
                    }
                }

                $request->merge(['json_value' => $jsonValue]);
            }

            $rules['json_value'] = ['required', 'array'];
            $rules['json_value.fields'] = ['required', 'array', 'min:1'];
            $rules['json_value.fields.*'] = ['required', 'array:title,key'];
            $rules['json_value.fields.*.title'] = ['required', 'string', 'max:150'];
            $rules['json_value.fields.*.key'] = [
                'required',
                'string',
                'max:100',
                'distinct:strict',
            ];
            $rules['json_value.data'] = ['present', 'array'];
            $rules['json_value.data.*'] = ['required', 'array'];
            $rules['json_value.data.*.*'] = ['string'];
            $rules['setting_value'] = ['prohibited'];
        } else {
            $rules['setting_value'] = match ($type) {
                1 => ['required', 'string', 'max:4096'],
                2 => ['required', 'integer'],
                3 => ['required', 'boolean'],
                4 => ['required', 'numeric'],
                default => ['prohibited'],
            };

            $rules['json_value'] = ['prohibited'];
        }

        $data = $request->validate($rules);

        if ($type === 5) {
            $fieldKeys = array_column($data['json_value']['fields'], 'key');

            foreach ($data['json_value']['data'] as $rowIndex => $row) {
                $rowKeys = array_map('strval', array_keys($row));

                if (
                    count($rowKeys) !== count($fieldKeys)
                    || array_diff($fieldKeys, $rowKeys) !== []
                    || array_diff($rowKeys, $fieldKeys) !== []
                ) {
                    throw ValidationException::withMessages([
                        "json_value.data.$rowIndex" => '每条数据必须且只能包含 fields 中定义的字段。',
                    ]);
                }

                $orderedRow = [];

                foreach ($fieldKeys as $fieldKey) {
                    $orderedRow[$fieldKey] = $row[$fieldKey];
                }

                $data['json_value']['data'][$rowIndex] = $orderedRow;
            }

            $data['setting_value'] = null;
        } else {
            if ($type === 3) {
                $data['setting_value'] = (string) (int) $data['setting_value'];
            } else {
                $data['setting_value'] = (string) $data['setting_value'];
            }

            $data['json_value'] = null;
        }

        return $data;
    }
}
