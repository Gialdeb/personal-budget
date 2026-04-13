<?php

namespace App\Http\Controllers;

use App\Http\Resources\Knowledge\PublicKnowledgeSectionResource;
use App\Support\Knowledge\PublicKnowledgeRouteData;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class HelpCenterController extends Controller
{
    public function index(PublicKnowledgeRouteData $routeData): Response
    {
        $request = request();
        $sections = $routeData->indexSections($request);

        return Inertia::render('help-center/Index', [
            'canRegister' => Features::enabled(Features::registration()),
            'sections' => PublicKnowledgeSectionResource::collection($sections)->resolve($request),
            'articleCount' => $sections->sum(
                fn ($section): int => $section->articles
                    ->where('is_published', true)
                    ->count(),
            ),
        ]);
    }
}
