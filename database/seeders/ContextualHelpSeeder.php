<?php

namespace Database\Seeders;

use App\Models\ContextualHelpEntry;
use App\Models\ContextualHelpEntryTranslation;
use App\Models\KnowledgeArticle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContextualHelpSeeder extends Seeder
{
    public function run(): void
    {
        $supportKnowledgeArticleId = KnowledgeArticle::query()
            ->where('slug', 'where-to-look-before-contacting-support')
            ->value('id');

        $entries = [
            [
                'page_key' => 'dashboard',
                'sort_order' => 1,
                'knowledge_article_id' => null,
                'translations' => [
                    'it' => [
                        'title' => 'Come leggere la dashboard',
                        'body' => '<p>Questa pagina ti dà una vista rapida del periodo attivo: saldi, trend, categorie da controllare e azioni in sospeso.</p><ul><li>Usa i filtri in alto per cambiare anno, mese o account.</li><li>Parti dai riepiloghi e poi scendi nei dettagli che richiedono attenzione.</li><li>Se qualcosa non torna, apri i movimenti del periodo per verificare che il dato di origine sia stato inserito correttamente.</li></ul>',
                    ],
                    'en' => [
                        'title' => 'How to read the dashboard',
                        'body' => '<p>This page gives you a quick view of the active period: balances, trends, categories to review, and pending actions.</p><ul><li>Use the top filters to switch year, month, or account.</li><li>Start with the summaries, then move into the details that require attention.</li><li>If something looks off, open the period transactions to verify the source data.</li></ul>',
                    ],
                ],
            ],
            [
                'page_key' => 'transactions',
                'sort_order' => 2,
                'knowledge_article_id' => null,
                'translations' => [
                    'it' => [
                        'title' => 'Come lavorare sui movimenti',
                        'body' => '<p>Qui gestisci i movimenti del periodo selezionato. La pagina serve per correggere, classificare e controllare i dettagli operativi.</p><ul><li>Verifica sempre anno e mese attivi prima di inserire o modificare un movimento.</li><li>Usa categorie e riferimenti coerenti per mantenere leggibili report e dashboard.</li><li>Se lavori su flussi ricorrenti, controlla anche l’area dedicata alle ricorrenze.</li></ul>',
                    ],
                    'en' => [
                        'title' => 'How to work on transactions',
                        'body' => '<p>This page is where you manage the transactions for the selected period. Use it to correct, classify, and review operational details.</p><ul><li>Always check the active year and month before editing or creating a transaction.</li><li>Use consistent categories and references so reports and dashboard totals stay readable.</li><li>If a movement belongs to a recurring flow, also review the recurring entries area.</li></ul>',
                    ],
                ],
            ],
            [
                'page_key' => 'recurring-entries',
                'sort_order' => 3,
                'knowledge_article_id' => null,
                'translations' => [
                    'it' => [
                        'title' => 'Come gestire le ricorrenze',
                        'body' => '<p>Qui prepari entrate e uscite che si ripetono nel tempo. Tieni le regole semplici e aggiornate per evitare correzioni continue nei mesi successivi.</p><ul><li>Controlla frequenza, importo e conto prima di attivare una ricorrenza.</li><li>Metti in pausa le ricorrenze non più valide invece di lasciarle continuare a operare se non più utili.</li><li>Quando un caso diventa eccezionale, valuta la conversione in movimento manuale.</li></ul>',
                    ],
                    'en' => [
                        'title' => 'How to manage recurring entries',
                        'body' => '<p>This page is where you prepare income and expense flows that repeat over time. Keep the rules simple and current so future months need fewer corrections.</p><ul><li>Review frequency, amount, and target account before activating a recurring entry.</li><li>Pause entries that are no longer valid instead of letting them create noise.</li><li>When a case becomes exceptional, consider converting it into a manual transaction.</li></ul>',
                    ],
                ],
            ],
            [
                'page_key' => 'budget-planning',
                'sort_order' => 14,
                'knowledge_article_id' => null,
                'translations' => [
                    'it' => [
                        'title' => 'Come usare la preventivazione',
                        'body' => '<p>La preventivazione ti aiuta a distribuire obiettivi e limiti mese per mese, così puoi confrontare il piano con i movimenti reali durante l’anno.</p><ul><li>Lavora sull’anno corretto prima di modificare gli importi.</li><li>Inserisci valori solo sulle categorie operative, lasciando che i totali riepiloghino la struttura.</li><li>Usa la copia dall’anno precedente quando vuoi partire da una base già coerente e poi rifinire mese per mese.</li></ul>',
                    ],
                    'en' => [
                        'title' => 'How to use budget planning',
                        'body' => '<p>Budget planning helps you spread targets and limits month by month, so you can compare the plan with real transactions throughout the year.</p><ul><li>Work on the correct year before changing amounts.</li><li>Enter values only on operational categories, letting totals summarize the structure.</li><li>Use the previous-year copy when you want to start from an already consistent baseline and then refine month by month.</li></ul>',
                    ],
                ],
            ],
            [
                'page_key' => 'categories',
                'sort_order' => 4,
                'knowledge_article_id' => null,
                'translations' => [
                    'it' => [
                        'title' => 'Come usare le categorie',
                        'body' => '<p>Questa pagina serve a mantenere ordinata la tassonomia usata nei movimenti. Una struttura troppo ampia o incoerente rende meno leggibili report e analisi.</p><ul><li>Crea solo le categorie che ti servono davvero.</li><li>Usa la gerarchia quando aggiunge chiarezza, non come formalità.</li><li>Prima di disattivare o cancellare una categoria, verifica se è già in uso.</li></ul>',
                    ],
                    'en' => [
                        'title' => 'How to use categories',
                        'body' => '<p>This page helps you maintain the taxonomy used across your transactions. An oversized or inconsistent structure makes reports harder to trust.</p><ul><li>Create only the categories you really need.</li><li>Use hierarchy only when it improves clarity.</li><li>Before deleting or disabling a category, check whether it is already in use.</li></ul>',
                    ],
                ],
            ],
            [
                'page_key' => 'banks',
                'sort_order' => 7,
                'knowledge_article_id' => null,
                'translations' => [
                    'it' => [
                        'title' => 'Come organizzare le banche',
                        'body' => '<p>Questa pagina raccoglie le banche che usi come origine dei conti. Mantenerla ordinata evita duplicati e semplifica la creazione dei conti collegati.</p><ul><li>Aggiungi una banca dal catalogo quando esiste gia.</li><li>Crea una banca personalizzata solo se serve davvero.</li><li>Prima di disattivare o eliminare una banca, verifica se e gia collegata a dei conti.</li></ul>',
                    ],
                    'en' => [
                        'title' => 'How to organize banks',
                        'body' => '<p>This page collects the banks you use as account sources. Keeping it tidy helps avoid duplicates and makes linked account creation easier.</p><ul><li>Add a bank from the catalog when it already exists.</li><li>Create a custom bank only when you really need one.</li><li>Before disabling or deleting a bank, check whether it is already linked to accounts.</li></ul>',
                    ],
                ],
            ],
            [
                'page_key' => 'tracked-items',
                'sort_order' => 6,
                'knowledge_article_id' => null,
                'translations' => [
                    'it' => [
                        'title' => 'Come usare i riferimenti tracciati',
                        'body' => '<p>I riferimenti servono a collegare movimenti, ricorrenze e categorie a entita che vuoi monitorare nel tempo. Qui conviene privilegiare chiarezza e riuso.</p><ul><li>Usa nomi stabili e facili da riconoscere.</li><li>Collega categorie compatibili solo quando servono davvero.</li><li>Disattiva gli elementi non piu utili invece di duplicarli.</li></ul>',
                    ],
                    'en' => [
                        'title' => 'How to use tracked items',
                        'body' => '<p>Tracked items connect transactions, recurring flows, and categories to entities you want to monitor over time. Favor clarity and reuse here.</p><ul><li>Use stable, recognizable names.</li><li>Attach compatible categories only when they are actually useful.</li><li>Disable items that are no longer needed instead of duplicating them.</li></ul>',
                    ],
                ],
            ],
            [
                'page_key' => 'accounts',
                'sort_order' => 8,
                'knowledge_article_id' => null,
                'translations' => [
                    'it' => [
                        'title' => 'Come configurare i conti',
                        'body' => '<p>Questa pagina governa i conti che alimentano saldi, movimenti e ricorrenze. Mantieni ogni conto con nome, stato e banca corretti per evitare dati incoerenti.</p><ul><li>Imposta un conto predefinito solo se e davvero quello usato piu spesso.</li><li>Controlla il saldo iniziale e la data di apertura quando crei un conto.</li><li>Prima di disattivare o eliminare un conto, verifica se ha dati collegati.</li></ul>',
                    ],
                    'en' => [
                        'title' => 'How to configure accounts',
                        'body' => '<p>This page governs the accounts that feed balances, transactions, and recurring flows. Keep each account name, state, and bank assignment clean so the data stays reliable.</p><ul><li>Set a default account only when it is truly your most common one.</li><li>Review opening balance and opening date when creating an account.</li><li>Before disabling or deleting an account, check whether related data already exists.</li></ul>',
                    ],
                ],
            ],
            [
                'page_key' => 'years',
                'sort_order' => 9,
                'knowledge_article_id' => null,
                'translations' => [
                    'it' => [
                        'title' => 'Come gestire gli anni',
                        'body' => '<p>Qui controlli quali anni sono disponibili nel budget e quale anno e attivo nell\'app. Una gestione ordinata aiuta a separare i periodi storici da quello operativo.</p><ul><li>Attiva l\'anno corretto prima di lavorare su dashboard e movimenti.</li><li>Chiudi un anno solo quando i dati sono consolidati.</li><li>Evita di cancellare anni che hanno gia utilizzo o storico rilevante.</li></ul>',
                    ],
                    'en' => [
                        'title' => 'How to manage years',
                        'body' => '<p>This page controls which years are available in the budget and which year is active in the app. Keeping years organized helps separate historical periods from the current operating one.</p><ul><li>Activate the correct year before working on dashboard and transactions.</li><li>Close a year only when its data is settled.</li><li>Avoid deleting years that already contain meaningful usage or history.</li></ul>',
                    ],
                ],
            ],
            [
                'page_key' => 'shared-categories',
                'sort_order' => 10,
                'knowledge_article_id' => null,
                'translations' => [
                    'it' => [
                        'title' => 'Categorie condivise: quando usarle',
                        'body' => '<p>Le categorie condivise servono quando più persone o più contesti lavorano sulla stessa struttura. Qui conviene essere ancora più rigorosi su naming e attivazione.</p><ul><li>Controlla sempre l’account condiviso selezionato.</li><li>Evita duplicati che rappresentano la stessa voce.</li><li>Materializza nel personale solo ciò che deve davvero diventare indipendente.</li></ul>',
                    ],
                    'en' => [
                        'title' => 'When to use shared categories',
                        'body' => '<p>Shared categories are useful when multiple people or contexts work on the same taxonomy. This is where naming and activation rules need to stay especially clean.</p><ul><li>Always verify which shared account is currently selected.</li><li>Avoid duplicates that represent the same concept.</li><li>Materialize into personal categories only when they really need to diverge.</li></ul>',
                    ],
                ],
            ],
            [
                'page_key' => 'exports',
                'sort_order' => 11,
                'knowledge_article_id' => null,
                'translations' => [
                    'it' => [
                        'title' => 'Come scegliere l’export giusto',
                        'body' => '<p>La pagina export ti permette di scaricare dataset specifici o un export più completo. Prima di esportare, chiarisci cosa ti serve davvero.</p><ul><li>Scegli dataset e formato in base all’uso finale.</li><li>Seleziona un intervallo temporale solo quando il dataset lo supporta.</li><li>Per controlli rapidi basta spesso un export mirato, non quello completo.</li></ul>',
                    ],
                    'en' => [
                        'title' => 'How to choose the right export',
                        'body' => '<p>The export page lets you download specific datasets or a broader export. Before exporting, decide what you actually need.</p><ul><li>Pick dataset and format based on the final use case.</li><li>Use a time range only when the selected dataset supports it.</li><li>For quick checks, a focused export is often better than a full one.</li></ul>',
                    ],
                ],
            ],
            [
                'page_key' => 'support',
                'sort_order' => 12,
                'knowledge_article_id' => $supportKnowledgeArticleId,
                'translations' => [
                    'it' => [
                        'title' => 'Prima di contattare il supporto',
                        'body' => '<p>Usa questa pagina quando la guida pubblica non basta. Il form salva la richiesta nel sistema e la collega al tuo account per evitare messaggi fuori contesto.</p><ul><li>Scegli la categoria più vicina al problema reale.</li><li>Scrivi un oggetto chiaro e un messaggio con i passaggi utili.</li><li>Se arrivi da una pagina specifica, il contesto viene salvato automaticamente.</li></ul>',
                    ],
                    'en' => [
                        'title' => 'Before contacting support',
                        'body' => '<p>Use this page when the public guide is not enough. The form stores the request in the system and links it to your account so the context stays clear.</p><ul><li>Choose the category that best matches the actual issue.</li><li>Write a clear subject and message with useful steps or context.</li><li>If you arrived from a specific page, that context is saved automatically.</li></ul>',
                    ],
                ],
            ],
            [
                'page_key' => 'profile',
                'sort_order' => 5,
                'knowledge_article_id' => null,
                'translations' => [
                    'it' => [
                        'title' => 'Come usare il profilo',
                        'body' => '<p>Il profilo raccoglie le preferenze personali che influenzano la tua esperienza quotidiana. Aggiorna solo ciò che incide davvero sul modo in cui lavori.</p><ul><li>Controlla lingua, formato dati e valuta base prima di iniziare a usare l’app con continuità.</li><li>Rivedi le preferenze notifiche se vuoi ridurre rumore o perdere meno eventi utili.</li><li>Usa questa pagina anche per verificare lo stato generale del tuo account.</li></ul>',
                    ],
                    'en' => [
                        'title' => 'How to use profile settings',
                        'body' => '<p>Your profile gathers the personal preferences that shape daily usage. Update only the settings that materially affect the way you work.</p><ul><li>Review language, data format, and base currency before using the app consistently.</li><li>Adjust notification preferences if you want less noise or clearer follow-up.</li><li>Use this page to verify your overall account setup as well.</li></ul>',
                    ],
                ],
            ],
            [
                'page_key' => 'exchange-rates',
                'sort_order' => 13,
                'knowledge_article_id' => null,
                'translations' => [
                    'it' => [
                        'title' => 'Come leggere i tassi di cambio',
                        'body' => '<p>Questa pagina serve a controllare i tassi disponibili e capire quali conversioni stanno alimentando i dati multi-valuta. Usala come verifica, non come archivio da consultare senza obiettivo.</p><ul><li>Filtra per data e coppia valuta quando stai controllando un caso specifico.</li><li>Confronta il tasso con il periodo del movimento o della ricorrenza interessata.</li><li>Se manca un risultato, verifica prima i filtri applicati e la data selezionata.</li></ul>',
                    ],
                    'en' => [
                        'title' => 'How to review exchange rates',
                        'body' => '<p>This page helps you inspect the rates available to the multi-currency flow. Use it as a verification tool rather than a long-form archive.</p><ul><li>Filter by date and currency pair when reviewing a specific case.</li><li>Compare the rate with the period used by the related transaction or recurring entry.</li><li>If no result appears, first review the active filters and selected date.</li></ul>',
                    ],
                ],
            ],
        ];

        foreach ($entries as $entryData) {
            $entry = ContextualHelpEntry::query()->updateOrCreate(
                ['page_key' => $entryData['page_key']],
                [
                    'uuid' => (string) Str::uuid(),
                    'knowledge_article_id' => $entryData['knowledge_article_id'],
                    'sort_order' => $entryData['sort_order'],
                    'is_published' => true,
                ],
            );

            foreach ($entryData['translations'] as $locale => $translation) {
                ContextualHelpEntryTranslation::query()->updateOrCreate(
                    [
                        'contextual_help_entry_id' => $entry->id,
                        'locale' => $locale,
                    ],
                    $translation,
                );
            }
        }
    }
}
