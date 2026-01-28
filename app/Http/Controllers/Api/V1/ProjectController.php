<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectSummaryResource;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ProjectController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->published()
            ->with('technologies')
            ->latestPublished()
            ->paginate(10);

        return ProjectSummaryResource::collection($projects);
    }

    public function featured(): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->published()
            ->with('technologies')
            ->featured()
            ->latestPublished()
            ->limit(3)
            ->get();

        return ProjectSummaryResource::collection($projects);
    }

    public function show(string $slug): ProjectResource
    {
        $project = Project::query()
            ->published()
            ->where('slug', $slug)
            ->with('technologies')
            ->firstOrFail();

        return new ProjectResource($project);
    }

    public function preview(string $slug): ProjectResource
    {
        $this->validatePreviewToken();

        $project = Project::query()
            ->where('slug', $slug)
            ->with('technologies')
            ->firstOrFail();

        return new ProjectResource($project);
    }

    private function validatePreviewToken(): void
    {
        $token = request()->query('token');
        $validToken = config('app.preview_token');

        if (! $token || ! $validToken || $token !== $validToken) {
            throw new AccessDeniedHttpException('Invalid preview token');
        }
    }
}
