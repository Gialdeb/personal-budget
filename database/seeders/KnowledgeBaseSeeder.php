<?php

namespace Database\Seeders;

use App\Models\KnowledgeArticle;
use App\Models\KnowledgeArticleTranslation;
use App\Models\KnowledgeSection;
use App\Models\KnowledgeSectionTranslation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KnowledgeBaseSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            [
                'slug' => 'getting-started',
                'sort_order' => 1,
                'translations' => [
                    'it' => [
                        'title' => 'Per iniziare',
                        'description' => 'Le basi per orientarti nel prodotto, capire la struttura e partire con ordine.',
                    ],
                    'en' => [
                        'title' => 'Getting started',
                        'description' => 'The essentials to understand the product structure and start from a clean foundation.',
                    ],
                ],
                'articles' => [
                    [
                        'slug' => 'how-the-workspace-is-organized',
                        'sort_order' => 1,
                        'translations' => [
                            'it' => [
                                'title' => 'Come è organizzato lo spazio di lavoro',
                                'excerpt' => 'Una panoramica rapida su dashboard, registri e logica generale del prodotto.',
                                'body' => '<p>Soamco Budget nasce per tenere insieme struttura e leggibilità. Dashboard, conti, budget e movimenti convivono in un flusso unico invece di essere sparsi in schermate isolate.</p><p>Il punto di partenza è capire tre aree: visione generale del periodo, dettaglio operativo dei movimenti e impostazioni che definiscono conti, categorie e riferimenti.</p><ul><li>La dashboard dà contesto.</li><li>I registri mensili mostrano il lavoro operativo.</li><li>Le impostazioni consolidano la struttura di base.</li></ul>',
                            ],
                            'en' => [
                                'title' => 'How the workspace is organized',
                                'excerpt' => 'A quick overview of dashboard, ledgers, and the product’s overall structure.',
                                'body' => '<p>Soamco Budget is designed to keep structure and readability together. Dashboard, accounts, budgets, and transactions live in one coherent flow instead of being scattered across unrelated screens.</p><p>The best starting point is to understand three areas: high-level monthly visibility, operational transaction work, and settings that define accounts, categories, and references.</p><ul><li>The dashboard gives context.</li><li>Monthly ledgers support day-to-day work.</li><li>Settings stabilize the underlying structure.</li></ul>',
                            ],
                        ],
                    ],
                    [
                        'slug' => 'what-you-should-configure-first',
                        'sort_order' => 2,
                        'translations' => [
                            'it' => [
                                'title' => 'Cosa configurare per primo',
                                'excerpt' => 'I primi elementi da impostare per evitare disordine appena inizi a usarlo.',
                                'body' => '<p>Prima di inserire molti dati conviene sistemare i pilastri: conti, anno attivo, categorie essenziali e alcune ricorrenze già prevedibili.</p><p>Questo evita di correggere in seguito una struttura nata in modo frettoloso.</p><ol><li>Definisci i conti principali.</li><li>Controlla l’anno attivo.</li><li>Crea solo le categorie davvero utili all’inizio.</li></ol>',
                            ],
                            'en' => [
                                'title' => 'What to configure first',
                                'excerpt' => 'The first settings worth defining so the workspace stays clean from the beginning.',
                                'body' => '<p>Before adding a large amount of data, it is worth stabilizing the foundations: main accounts, active year, essential categories, and the recurring items you already know you will need.</p><p>That makes later work much cleaner and avoids correcting a rushed structure.</p><ol><li>Set up your primary accounts.</li><li>Check the active year.</li><li>Create only the categories that are genuinely useful at first.</li></ol>',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'accounts-and-workflows',
                'sort_order' => 2,
                'translations' => [
                    'it' => [
                        'title' => 'Conti e flussi di lavoro',
                        'description' => 'Come impostare conti, movimenti e routine operative senza perdere leggibilità nel tempo.',
                    ],
                    'en' => [
                        'title' => 'Accounts and workflows',
                        'description' => 'How to structure accounts, transactions, and recurring routines without losing clarity over time.',
                    ],
                ],
                'articles' => [
                    [
                        'slug' => 'how-to-keep-accounts-clean-over-time',
                        'sort_order' => 1,
                        'translations' => [
                            'it' => [
                                'title' => 'Come mantenere i conti ordinati nel tempo',
                                'excerpt' => 'Piccole regole operative per evitare duplicazioni e strutture poco leggibili.',
                                'body' => '<p>Un conto ben impostato dovrebbe avere uno scopo chiaro. Se due conti finiscono per rappresentare la stessa cosa, la lettura del totale e dei movimenti si indebolisce.</p><p>Meglio una struttura essenziale, aggiornata quando serve, che una tassonomia sovraccarica di eccezioni.</p>',
                            ],
                            'en' => [
                                'title' => 'How to keep accounts clean over time',
                                'excerpt' => 'Operational habits that help avoid duplication and unclear account structures.',
                                'body' => '<p>A well-defined account should have a clear purpose. If two accounts end up representing the same thing, balances and transaction review become harder to trust.</p><p>A smaller, clearer structure is usually better than an overloaded taxonomy full of exceptions.</p>',
                            ],
                        ],
                    ],
                    [
                        'slug' => 'when-recurring-workflows-make-sense',
                        'sort_order' => 2,
                        'translations' => [
                            'it' => [
                                'title' => 'Quando ha senso usare le ricorrenze',
                                'excerpt' => 'Capire quali flussi ripetitivi conviene automatizzare e quali no.',
                                'body' => '<p>Le ricorrenze aiutano quando un movimento o una pianificazione si ripete davvero nel tempo. Non servono per ogni operazione possibile.</p><p>Usale quando migliorano continuità e controllo, non solo perché una funzione esiste.</p>',
                            ],
                            'en' => [
                                'title' => 'When recurring workflows make sense',
                                'excerpt' => 'A simple way to decide which routines deserve automation and which do not.',
                                'body' => '<p>Recurring flows help when a transaction or planning routine is genuinely repetitive. They do not need to be used for every possible action.</p><p>Use them where they improve continuity and control, not simply because the feature exists.</p>',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'slug' => 'billing-and-access',
                'sort_order' => 3,
                'translations' => [
                    'it' => [
                        'title' => 'Accesso e supporto al progetto',
                        'description' => 'Chiarimenti pubblici su accesso, stato del prodotto e come ottenere supporto quando serve.',
                    ],
                    'en' => [
                        'title' => 'Access and project support',
                        'description' => 'Public guidance about access, product status, and how support works when you need it.',
                    ],
                ],
                'articles' => [
                    [
                        'slug' => 'how-public-access-and-account-access-differ',
                        'sort_order' => 1,
                        'translations' => [
                            'it' => [
                                'title' => 'Differenza tra contenuti pubblici e accesso all’account',
                                'excerpt' => 'Cosa puoi leggere senza account e cosa richiede accesso autenticato.',
                                'body' => '<p>L’Help Center pubblico è pensato per spiegare il prodotto, chiarire il funzionamento e offrire una base di orientamento anche prima della registrazione.</p><p>Le attività operative e il supporto personalizzato restano invece dentro l’account autenticato.</p>',
                            ],
                            'en' => [
                                'title' => 'How public content and account access differ',
                                'excerpt' => 'What remains public and what is intentionally reserved for authenticated users.',
                                'body' => '<p>The public Help Center exists to explain the product, clarify how it works, and provide useful orientation before registration.</p><p>Operational actions and personalized support remain inside the authenticated account area.</p>',
                            ],
                        ],
                    ],
                    [
                        'slug' => 'where-to-look-before-contacting-support',
                        'sort_order' => 2,
                        'translations' => [
                            'it' => [
                                'title' => 'Dove guardare prima di contattare il supporto',
                                'excerpt' => 'Il percorso consigliato per capire se una risposta è già disponibile nella guida pubblica.',
                                'body' => '<p>Prima di contattare il supporto conviene partire dalle sezioni della guida, dal changelog pubblico e dalle pagine che spiegano accesso e funzionamento del prodotto.</p><p>Questo aiuta a distinguere meglio tra una domanda già chiarita, un comportamento atteso e un problema reale da segnalare.</p>',
                            ],
                            'en' => [
                                'title' => 'Where to look before contacting support',
                                'excerpt' => 'The recommended path to follow before escalating a question to support.',
                                'body' => '<p>Before contacting support, it is worth checking the Help Center sections, the public changelog, and the pages that explain product access and expected behavior.</p><p>That makes it easier to separate an already documented answer from a real issue worth reporting.</p>',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($sections as $sectionData) {
            $section = KnowledgeSection::query()->updateOrCreate(
                ['slug' => $sectionData['slug']],
                [
                    'uuid' => (string) Str::uuid(),
                    'sort_order' => $sectionData['sort_order'],
                    'is_published' => true,
                ],
            );

            foreach ($sectionData['translations'] as $locale => $translation) {
                KnowledgeSectionTranslation::query()->updateOrCreate(
                    [
                        'section_id' => $section->id,
                        'locale' => $locale,
                    ],
                    $translation,
                );
            }

            foreach ($sectionData['articles'] as $articleData) {
                $article = KnowledgeArticle::query()->updateOrCreate(
                    ['slug' => $articleData['slug']],
                    [
                        'uuid' => (string) Str::uuid(),
                        'section_id' => $section->id,
                        'sort_order' => $articleData['sort_order'],
                        'is_published' => true,
                        'published_at' => now(),
                    ],
                );

                foreach ($articleData['translations'] as $locale => $translation) {
                    KnowledgeArticleTranslation::query()->updateOrCreate(
                        [
                            'article_id' => $article->id,
                            'locale' => $locale,
                        ],
                        $translation,
                    );
                }
            }
        }
    }
}
