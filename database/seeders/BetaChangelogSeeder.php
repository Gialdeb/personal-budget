<?php

namespace Database\Seeders;

use App\Models\ChangelogRelease;
use App\Services\Changelog\ChangelogReleaseUpsertService;
use Illuminate\Database\Seeder;

class BetaChangelogSeeder extends Seeder
{
    public function run(ChangelogReleaseUpsertService $upsertService): void
    {
        $release = ChangelogRelease::query()
            ->where('version_label', '1.0.0-beta')
            ->first();

        $upsertService->upsert($release, [
            'version_label' => '1.0.0-beta',
            'channel' => 'beta',
            'is_published' => true,
            'is_pinned' => true,
            'published_at' => now(),
            'sort_order' => 1000,
            'translations' => [
                [
                    'locale' => 'it',
                    'title' => 'Beta iniziale disponibile',
                    'summary' => '<p>Questa è la prima beta pubblica di Soamco Budget: le funzioni principali sono disponibili per test reali online e da mobile, ma alcuni dettagli sono ancora in evoluzione.</p>',
                    'excerpt' => 'Prima beta pubblica disponibile per test reali.',
                ],
                [
                    'locale' => 'en',
                    'title' => 'Initial beta available',
                    'summary' => '<p>This is the first public beta of Soamco Budget: the main workflows are available for real online and mobile testing, but some details are still evolving.</p>',
                    'excerpt' => 'First public beta available for real-world testing.',
                ],
            ],
            'sections' => [
                [
                    'key' => 'beta-status',
                    'sort_order' => 1,
                    'translations' => [
                        ['locale' => 'it', 'label' => 'Stato beta'],
                        ['locale' => 'en', 'label' => 'Beta status'],
                    ],
                    'items' => [
                        [
                            'sort_order' => 1,
                            'item_type' => 'bullet',
                            'translations' => [
                                [
                                    'locale' => 'it',
                                    'title' => 'Base pronta per i test',
                                    'body' => 'Le funzioni principali dell’app sono disponibili e i contenuti iniziali bilingui sono stati preparati per il primo utilizzo.',
                                ],
                                [
                                    'locale' => 'en',
                                    'title' => 'Core beta ready for testing',
                                    'body' => 'The main app workflows are available and the initial bilingual content has been prepared for first use.',
                                ],
                            ],
                        ],
                        [
                            'sort_order' => 2,
                            'item_type' => 'bullet',
                            'translations' => [
                                [
                                    'locale' => 'it',
                                    'title' => 'Feedback utile',
                                    'body' => 'Durante questa beta potresti ancora trovare bug o comportamenti da rifinire. Il feedback contestualizzato è utile per stabilizzare il prodotto.',
                                ],
                                [
                                    'locale' => 'en',
                                    'title' => 'Feedback is useful',
                                    'body' => 'You may still encounter bugs or rough edges during this beta. Contextual feedback is useful to stabilize the product.',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'key' => 'known-scope',
                    'sort_order' => 2,
                    'translations' => [
                        ['locale' => 'it', 'label' => 'Perimetro attuale'],
                        ['locale' => 'en', 'label' => 'Current scope'],
                    ],
                    'items' => [
                        [
                            'sort_order' => 1,
                            'item_type' => 'bullet',
                            'translations' => [
                                [
                                    'locale' => 'it',
                                    'title' => 'Prime funzioni disponibili',
                                    'body' => 'Questa prima beta include i flussi principali per lavorare su transazioni, conti, conti condivisi, preventivazione e ricorrenze.',
                                ],
                                [
                                    'locale' => 'en',
                                    'title' => 'First features available',
                                    'body' => 'This first beta includes the main workflows for transactions, accounts, shared accounts, budget planning, and recurring entries.',
                                ],
                            ],
                        ],
                        [
                            'sort_order' => 2,
                            'item_type' => 'bullet',
                            'translations' => [
                                [
                                    'locale' => 'it',
                                    'title' => 'Alcune aree sono ancora in evoluzione',
                                    'body' => 'Alcune parti del prodotto sono volutamente ancora in affinamento, anche se il perimetro attuale è pronto per test realistici.',
                                ],
                                [
                                    'locale' => 'en',
                                    'title' => 'Some areas are still evolving',
                                    'body' => 'Some parts of the product are intentionally still being refined, even though the current scope is ready for realistic testing.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
