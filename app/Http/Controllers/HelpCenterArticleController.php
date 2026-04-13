<?php

namespace App\Http\Controllers;

use App\Http\Resources\Knowledge\PublicKnowledgeArticleResource;
use App\Models\KnowledgeArticle;
use App\Support\Knowledge\PublicKnowledgeRouteData;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class HelpCenterArticleController extends Controller
{
    public function show(
        KnowledgeArticle $knowledgeArticle,
        PublicKnowledgeRouteData $routeData,
    ): Response {
        $request = request();
        $article = $routeData->resolveArticle($request, $knowledgeArticle);
        $relatedArticles = $routeData->relatedArticles($article, $request);

        return Inertia::render('help-center/Article', [
            'canRegister' => Features::enabled(Features::registration()),
            'article' => (new PublicKnowledgeArticleResource($article))->resolve($request),
            'relatedArticles' => PublicKnowledgeArticleResource::collection($relatedArticles)->resolve($request),
        ]);
    }
}
