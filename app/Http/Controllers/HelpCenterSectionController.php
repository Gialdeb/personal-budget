<?php

namespace App\Http\Controllers;

use App\Http\Resources\Knowledge\PublicKnowledgeSectionResource;
use App\Models\KnowledgeSection;
use App\Support\Knowledge\PublicKnowledgeRouteData;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class HelpCenterSectionController extends Controller
{
    public function show(
        KnowledgeSection $knowledgeSection,
        PublicKnowledgeRouteData $routeData,
    ): Response {
        $request = request();
        $section = $routeData->resolveSection($request, $knowledgeSection);

        return Inertia::render('help-center/Section', [
            'canRegister' => Features::enabled(Features::registration()),
            'section' => (new PublicKnowledgeSectionResource($section))->resolve($request),
        ]);
    }
}
