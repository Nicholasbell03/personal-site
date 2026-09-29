<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateProjectDraft;
use App\Filament\Resources\Projects\ProjectResource as FilamentProjectResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projects) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->projects->paginated($request->integer('page', 1)));
    }

    public function featured(): JsonResponse
    {
        return response()->json($this->projects->featured());
    }

    public function show(string $slug): JsonResponse
    {
        return response()->json($this->projects->show($slug));
    }

    public function related(string $slug): JsonResponse
    {
        return response()->json($this->projects->related($slug));
    }

    /**
     * Create a project draft.
     *
     * Always creates a draft. `technologies` takes a list of technology ids to attach.
     */
    public function store(StoreProjectRequest $request, CreateProjectDraft $createProjectDraft): JsonResponse
    {
        $project = $createProjectDraft->execute($request->validated());

        return (new ProjectResource($project))
            ->additional(['admin_url' => FilamentProjectResource::getUrl('edit', ['record' => $project])])
            ->response()
            ->setStatusCode(201);
    }

    public function preview(string $slug): ProjectResource
    {
        return new ProjectResource($this->projects->findForPreview($slug));
    }
}
