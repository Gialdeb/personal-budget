<?php

namespace App\Support\ContextualHelp;

use App\Http\Resources\ContextualHelp\CurrentContextualHelpResource;
use App\Models\ContextualHelpEntry;
use App\Models\KnowledgeArticle;
use App\Supports\Locale\LocaleResolver;
use Illuminate\Http\Request;

class CurrentContextualHelpResolver
{
    public function __construct(
        protected LocaleResolver $localeResolver,
        protected ContextualHelpCache $contextualHelpCache,
    ) {}

    /**
     * @return array<int, array{key: string, label: string, description: string, route_names: array<int, string>}>
     */
    public function options(): array
    {
        return [
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'description' => 'Panoramica iniziale con indicatori, trend e controlli rapidi del periodo.',
                'route_names' => ['dashboard', 'dashboard.data'],
            ],
            [
                'key' => 'transactions',
                'label' => 'Transactions',
                'description' => 'Registro movimenti, filtro periodo e operazioni quotidiane sui movimenti.',
                'route_names' => ['transactions.index', 'transactions.show'],
            ],
            [
                'key' => 'categories',
                'label' => 'Categories',
                'description' => 'Gestione categorie personali e struttura gerarchica usata nei movimenti.',
                'route_names' => ['categories.edit'],
            ],
            [
                'key' => 'banks',
                'label' => 'Banks',
                'description' => 'Anagrafica banche personali collegate ai conti e gestione delle fonti bancarie.',
                'route_names' => ['banks.edit'],
            ],
            [
                'key' => 'tracked-items',
                'label' => 'Tracked Items',
                'description' => 'Riferimenti, entita tracciate e tassonomia usata per collegare movimenti e ricorrenze.',
                'route_names' => ['tracked-items.edit'],
            ],
            [
                'key' => 'accounts',
                'label' => 'Accounts',
                'description' => 'Gestione conti, stato attivo, conto predefinito e vincoli di bilancio di base.',
                'route_names' => ['accounts.edit'],
            ],
            [
                'key' => 'years',
                'label' => 'Years',
                'description' => 'Gestione annualita disponibili, anno attivo e chiusura dei periodi storici.',
                'route_names' => ['years.edit'],
            ],
            [
                'key' => 'shared-categories',
                'label' => 'Shared Categories',
                'description' => 'Categorie condivise collegate agli account condivisi.',
                'route_names' => ['shared-categories.edit'],
            ],
            [
                'key' => 'exports',
                'label' => 'Exports',
                'description' => 'Scelta dataset/formato e download dei dati esportabili.',
                'route_names' => ['exports.edit'],
            ],
            [
                'key' => 'support',
                'label' => 'Support',
                'description' => 'Invio richieste di supporto dopo aver consultato la guida pubblica.',
                'route_names' => ['support.index'],
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function supportedPageKeys(): array
    {
        return collect($this->options())
            ->pluck('key')
            ->all();
    }

    public function resolvePageKey(Request $request): ?string
    {
        foreach ($this->options() as $option) {
            if ($request->routeIs(...$option['route_names'])) {
                return $option['key'];
            }
        }

        return null;
    }

    public function resolveForRequest(Request $request): ?ContextualHelpEntry
    {
        $pageKey = $this->resolvePageKey($request);

        if ($pageKey === null) {
            return null;
        }

        $request->attributes->set(
            'contextual_help_locale',
            $this->localeResolver->current($request),
        );
        $request->attributes->set(
            'contextual_help_fallback_locale',
            $this->localeResolver->fallback(),
        );

        return ContextualHelpEntry::query()
            ->published()
            ->where('page_key', $pageKey)
            ->with([
                'translations',
                'knowledgeArticle.translations',
                'knowledgeArticle.section.translations',
            ])
            ->ordered()
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolvePayloadForRequest(Request $request): ?array
    {
        $pageKey = $this->resolvePageKey($request);

        if ($pageKey === null) {
            return null;
        }

        $locale = $this->localeResolver->current($request);

        return $this->contextualHelpCache->remember(
            $pageKey,
            $locale,
            function () use ($request): ?array {
                $entry = $this->resolveForRequest($request);

                if ($entry === null) {
                    return null;
                }

                return (new CurrentContextualHelpResource($entry))
                    ->resolve($request);
            },
        );
    }

    public function publicKnowledgeArticle(?KnowledgeArticle $knowledgeArticle): ?KnowledgeArticle
    {
        if ($knowledgeArticle === null) {
            return null;
        }

        if (! $knowledgeArticle->is_published || ! (bool) $knowledgeArticle->section?->is_published) {
            return null;
        }

        return $knowledgeArticle;
    }
}
