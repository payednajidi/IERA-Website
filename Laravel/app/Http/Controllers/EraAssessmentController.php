<?php

namespace App\Http\Controllers;

use App\Models\EraAssessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class EraAssessmentController extends Controller
{
    public function index()
    {
        $assessments = EraAssessment::query()
            ->orderByDesc('created_at')
            ->get([
                'id',
                'assessor_name',
                'assessment_date',
                'department',
                'created_at',
                'updated_at',
            ]);

        return response()->json([
            'assessments' => $assessments,
        ]);
    }

    public function show(int $assessmentId)
    {
        $assessment = EraAssessment::with([
            'processes' => fn ($query) => $query->orderBy('id'),
            'processes.tasks' => fn ($query) => $query->orderBy('row_number')->orderBy('id'),
            'photoGroups' => fn ($query) => $query->orderBy('id'),
            'photoGroups.photos' => fn ($query) => $query->orderBy('id'),
        ])->findOrFail($assessmentId);

        return response()->json($this->serializeAssessment($assessment));
    }

    public function update(Request $request, int $assessmentId)
    {
        $assessment = EraAssessment::with([
            'processes' => fn ($query) => $query->orderBy('id'),
            'processes.tasks' => fn ($query) => $query->orderBy('row_number')->orderBy('id'),
            'photoGroups' => fn ($query) => $query->orderBy('id'),
            'photoGroups.photos' => fn ($query) => $query->orderBy('id'),
        ])->findOrFail($assessmentId);

        $validated = $request->validate([
            'assessor_name' => 'sometimes|required|string|max:255',
            'assessment_date' => 'sometimes|required|string|max:255',
            'department' => 'sometimes|required|string|max:255',
            'working_hours' => 'nullable|string|max:255',
            'breaks' => 'nullable|string|max:255',
            'processes' => 'sometimes|array|min:1',
            'processes.*.id' => 'required|integer',
            'processes.*.name' => 'required|string|max:255',
            'processes.*.tasks' => 'required|array|min:1',
            'processes.*.tasks.*.id' => 'required|integer',
            'processes.*.tasks.*.title' => 'required|string|max:255',
            'processes.*.tasks.*.description' => 'nullable|string',
            'processes.*.tasks.*.worker_activities' => 'nullable|string',
            'processes.*.tasks.*.row_number' => 'required|integer|min:1',
            'photo_groups' => 'sometimes|array',
            'photo_groups.*.id' => 'required|integer',
            'photo_groups.*.title' => 'nullable|string|max:255',
            'photo_groups.*.description' => 'nullable|string',
            'photo_groups.*.keep_photo_ids' => 'nullable|array',
            'photo_groups.*.keep_photo_ids.*' => 'integer',
            'photo_groups.*.new_photos' => 'nullable|array',
            'photo_groups.*.new_photos.*' => 'file|image|max:10240',
        ]);

        if (array_key_exists('processes', $validated)) {
            $processLookup = $assessment->processes->keyBy(fn ($process) => (int) $process->id);

            foreach ($validated['processes'] as $processPayload) {
                $processId = (int) $processPayload['id'];
                if (!$processLookup->has($processId)) {
                    return response()->json([
                        'message' => "Process ID {$processId} does not belong to this assessment.",
                    ], 422);
                }

                $taskLookup = $processLookup->get($processId)->tasks->keyBy(fn ($task) => (int) $task->id);
                foreach ($processPayload['tasks'] as $taskPayload) {
                    $taskId = (int) $taskPayload['id'];
                    if (!$taskLookup->has($taskId)) {
                        return response()->json([
                            'message' => "Task ID {$taskId} does not belong to process ID {$processId}.",
                        ], 422);
                    }
                }
            }
        }

        if (array_key_exists('photo_groups', $validated)) {
            $photoGroupLookup = $assessment->photoGroups->keyBy(fn ($group) => (int) $group->id);

            foreach ($validated['photo_groups'] as $photoGroupPayload) {
                $photoGroupId = (int) $photoGroupPayload['id'];
                if (!$photoGroupLookup->has($photoGroupId)) {
                    return response()->json([
                        'message' => "Photo group ID {$photoGroupId} does not belong to this assessment.",
                    ], 422);
                }

                $group = $photoGroupLookup->get($photoGroupId);
                $existingPhotoIds = $group->photos->pluck('id')->map(fn ($id) => (int) $id)->all();
                $keptPhotoIds = collect($photoGroupPayload['keep_photo_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $invalidPhotoIds = array_diff($keptPhotoIds, $existingPhotoIds);
                if (!empty($invalidPhotoIds)) {
                    return response()->json([
                        'message' => "Invalid photo IDs for photo group ID {$photoGroupId}.",
                    ], 422);
                }
            }
        }

        DB::transaction(function () use ($assessment, $validated, $request) {
            $updatableKeys = ['assessor_name', 'assessment_date', 'department', 'working_hours', 'breaks'];
            foreach ($updatableKeys as $key) {
                if (array_key_exists($key, $validated)) {
                    $assessment->{$key} = $validated[$key];
                }
            }
            $assessment->save();

            if (array_key_exists('processes', $validated)) {
                $processLookup = $assessment->processes->keyBy(fn ($process) => (int) $process->id);

                foreach ($validated['processes'] as $processPayload) {
                    $process = $processLookup->get((int) $processPayload['id']);
                    $process->name = $processPayload['name'];
                    $process->save();

                    $taskLookup = $process->tasks->keyBy(fn ($task) => (int) $task->id);
                    foreach ($processPayload['tasks'] as $taskPayload) {
                        $task = $taskLookup->get((int) $taskPayload['id']);
                        $task->title = $taskPayload['title'];
                        $task->description = $taskPayload['description'] ?? null;
                        $task->worker_activities = $taskPayload['worker_activities'] ?? null;
                        $task->row_number = (int) $taskPayload['row_number'];
                        $task->save();
                    }
                }
            }

            if (array_key_exists('photo_groups', $validated)) {
                $photoGroupLookup = $assessment->photoGroups->keyBy(fn ($group) => (int) $group->id);

                foreach ($validated['photo_groups'] as $photoGroupIndex => $photoGroupPayload) {
                    $photoGroup = $photoGroupLookup->get((int) $photoGroupPayload['id']);
                    $photoGroup->title = $photoGroupPayload['title'] ?? null;
                    $photoGroup->description = $photoGroupPayload['description'] ?? null;
                    $photoGroup->save();

                    $keptPhotoIds = collect($photoGroupPayload['keep_photo_ids'] ?? [])
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all();

                    $photosToDelete = $photoGroup->photos()
                        ->whereNotIn('id', $keptPhotoIds)
                        ->get();

                    foreach ($photosToDelete as $photo) {
                        if (!empty($photo->file_path)) {
                            Storage::disk('public')->delete($photo->file_path);
                        }
                        $photo->delete();
                    }

                    if ($request->hasFile("photo_groups.$photoGroupIndex.new_photos")) {
                        foreach ($request->file("photo_groups.$photoGroupIndex.new_photos") as $photoFile) {
                            $path = $photoFile->store('era_photos', 'public');
                            $photoGroup->photos()->create([
                                'file_path' => $path,
                            ]);
                        }
                    }
                }
            }
        });

        $assessment->refresh()->load([
            'processes' => fn ($query) => $query->orderBy('id'),
            'processes.tasks' => fn ($query) => $query->orderBy('row_number')->orderBy('id'),
            'photoGroups' => fn ($query) => $query->orderBy('id'),
            'photoGroups.photos' => fn ($query) => $query->orderBy('id'),
        ]);

        return response()->json([
            'message' => 'ERA assessment updated successfully.',
            ...$this->serializeAssessment($assessment),
        ]);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $assessment = EraAssessment::create([
                'assessor_name' => $request->assessor_name,
                'assessment_date' => $request->assessment_date,
                'department' => $request->department,
                'working_hours' => $request->working_hours,
                'breaks' => $request->breaks,
            ]);

            $createdTasks = [];

            if ($request->has('processes')) {
                foreach ($request->processes as $processData) {
                    $process = $assessment->processes()->create([
                        'name' => $processData['name'] ?? null,
                    ]);

                    if (isset($processData['tasks'])) {
                        foreach ($processData['tasks'] as $taskData) {
                            $task = $process->tasks()->create([
                                'title' => $taskData['title'] ?? null,
                                'description' => $taskData['description'] ?? null,
                                'worker_activities' => $taskData['worker_activities'] ?? null,
                                'row_number' => $taskData['row_number'] ?? null,
                            ]);

                            $createdTasks[] = $task;
                        }
                    }
                }
            }

            if ($request->has('photo_groups')) {
                foreach ($request->photo_groups as $index => $groupData) {
                    $task = $createdTasks[$index] ?? null;

                    $photoGroup = $assessment->photoGroups()->create([
                        'task_id' => $task?->id,
                        'title' => $groupData['title'] ?? null,
                        'description' => $groupData['description'] ?? null,
                    ]);

                    if ($request->hasFile("photo_groups.$index.photos")) {
                        foreach ($request->file("photo_groups.$index.photos") as $photoFile) {
                            $path = $photoFile->store('era_photos', 'public');
                            $photoGroup->photos()->create([
                                'file_path' => $path,
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'ERA Assessment created successfully',
                'id' => $assessment->id,
                'assessment' => $assessment->load(
                    'processes.tasks',
                    'photoGroups.photos'
                ),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function serializeAssessment(EraAssessment $assessment): array
    {
        $taskMetaMap = [];
        foreach ($assessment->processes as $process) {
            foreach ($process->tasks as $task) {
                $taskMetaMap[(int) $task->id] = [
                    'title' => $task->title,
                    'description' => $task->description,
                ];
            }
        }

        $processes = $assessment->processes->map(function ($process) {
            return [
                'id' => (int) $process->id,
                'assessment_id' => (int) $process->assessment_id,
                'name' => $process->name,
                'tasks' => $process->tasks->map(function ($task) {
                    return [
                        'id' => (int) $task->id,
                        'process_id' => (int) $task->process_id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'worker_activities' => $task->worker_activities,
                        'row_number' => (int) $task->row_number,
                    ];
                })->values(),
            ];
        })->values();

        $apiBaseUrl = rtrim(request()->getSchemeAndHttpHost(), '/');

        $photoGroups = $assessment->photoGroups->map(function ($group) use ($taskMetaMap, $apiBaseUrl) {
            $photos = $group->photos->map(function ($photo) use ($apiBaseUrl) {
                $relativePath = ltrim((string) $photo->file_path, '/');

                return [
                    'id' => (int) $photo->id,
                    'file_path' => $photo->file_path,
                    'url' => $apiBaseUrl . '/storage/' . $relativePath,
                ];
            })->values();

            return [
                'id' => (int) $group->id,
                'assessment_id' => (int) $group->assessment_id,
                'task_id' => $group->task_id ? (int) $group->task_id : null,
                'task_title' => $group->task_id && array_key_exists((int) $group->task_id, $taskMetaMap)
                    ? $taskMetaMap[(int) $group->task_id]['title']
                    : null,
                'task_description' => $group->task_id && array_key_exists((int) $group->task_id, $taskMetaMap)
                    ? $taskMetaMap[(int) $group->task_id]['description']
                    : null,
                'title' => $group->title,
                'description' => $group->description,
                'photos' => $photos,
            ];
        })->values();

        return [
            'assessment' => [
                'id' => (int) $assessment->id,
                'assessor_name' => $assessment->assessor_name,
                'assessment_date' => $assessment->assessment_date,
                'department' => $assessment->department,
                'working_hours' => $assessment->working_hours,
                'breaks' => $assessment->breaks,
                'created_at' => $assessment->created_at,
                'updated_at' => $assessment->updated_at,
            ],
            'processes' => $processes,
            'photo_groups' => $photoGroups,
        ];
    }
}
