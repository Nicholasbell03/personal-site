<?php

namespace App\Agents\Tools;

use App\Enums\UserContextKey;
use App\Models\UserContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetCVDetail implements Tool
{
    public function description(): string
    {
        return "Get Nick's full detailed CV/resume including work experience, education, skills, and qualifications. Use this when a user asks detailed questions about Nick's background, experience, or qualifications.";
    }

    public function handle(Request $request): string
    {
        $cv = UserContext::cached(UserContextKey::CvDetailed);

        if (! $cv) {
            return 'Detailed CV information is not currently available.';
        }

        return $cv;
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
