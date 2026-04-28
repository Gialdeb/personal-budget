export const reportsMessages = {
    it: {
        title: 'Report',
        areaLabel: 'Area report',
        description:
            'Hub intermedio per accedere alle sezioni report e preparare i futuri rilasci analytics.',
        navigationLabel: 'Navigazione area report',
        sidebarTitle: 'Sezioni report',
        hero: {
            description:
                'Accedi alle principali aree di report per analizzare andamento, risultati del periodo e distribuzione dei dati in modo chiaro e ordinato.',
        },
        launcher: {
            title: 'Launcher delle sezioni Report',
            description:
                'Ogni card apre una sezione dedicata, pronta a ricevere sviluppo incrementale senza trasformare subito l’index in una dashboard finale.',
        },
        filters: {
            title: 'Filtri report',
            description: 'Definisci perimetro temporale e chiave di lettura.',
            year: 'Anno',
            period: 'Periodo',
            referenceMonth: 'Mese di riferimento',
            resource: 'Risorsa / conto',
            allResources: 'Tutte le risorse',
            apply: 'Applica',
            reset: 'Reset',
            monthDisabledAnnual:
                'Nel report annuale il mese di riferimento non si applica, quindi viene rimosso dal filtro.',
            periods: {
                annual: 'Annuale',
                monthly: 'Mensile',
                lastThreeMonths: 'Ultimi 3 mesi',
                lastSixMonths: 'Ultimi 6 mesi',
                ytd: 'Da inizio anno (YTD)',
            },
            periodSummaries: {
                annual: 'Anno {year}',
                lastThreeMonths: 'Ultimi 3 mesi fino a {month} {year}',
                lastSixMonths: 'Ultimi 6 mesi fino a {month} {year}',
                ytd: 'Da inizio anno fino a {month} {year}',
            },
        },
        overview: {
            hero: {
                description:
                    'KPI principali, trend entrate/uscite e composizione del saldo dell’intero anno.',
            },
            kpis: {
                income: 'Entrate',
                expense: 'Uscite',
                net: 'Netto',
                transactions: 'Movimenti',
                transactionUnit: 'movimento',
                transactionsUnit: 'movimenti',
                averageNet: 'Media netta',
                averagePerDay: 'Media per giorno',
                averagePerMonth: 'Media per mese',
                bestPeriod: 'Periodo migliore',
                notAvailable: 'Non disponibile',
                periodTotal: 'Totale del perimetro selezionato',
                periodBalance: 'Saldo del perimetro selezionato',
                includedMovements: 'Movimenti manuali non trasferimento',
                previousPeriodHint: 'Confronto con {period}',
            },
            emptyState:
                'Non ci sono ancora dati sufficienti nel perimetro selezionato. La pagina resta disponibile, ma KPI e grafici verranno riempiti appena arriveranno movimenti coerenti con questi filtri.',
            distribution: {
                title: 'Equilibrio del periodo',
                description:
                    'Colpo d’occhio su entrate e uscite del periodo, con il saldo netto in evidenza al centro.',
                empty: 'Non ci sono ancora movimenti sufficienti per costruire la sintesi del periodo.',
                centerLabel: 'Netto',
                centerCaption: 'Saldo del periodo selezionato',
                legendShare: 'Quota sul totale movimentato',
            },
            comparison: {
                title: 'Confronto per intervallo',
                description:
                    'Bar chart comparativa per leggere rapidamente dove entrate e uscite si allargano o si comprimono nel periodo.',
                empty: 'Non ci sono ancora dati sufficienti per il confronto del periodo.',
            },
            snapshot: {
                title: 'Ultimi intervalli osservati',
                description:
                    'Riepilogo rapido degli ultimi bucket del report per isolare dove il saldo cambia davvero.',
                netLabel: 'Saldo netto',
                empty: 'Non ci sono ancora intervalli con movimento nel periodo selezionato.',
            },
            meta: {
                coverageNote:
                    '{count} movimenti sono esclusi dai totali monetari perche privi di conversione affidabile in valuta base.',
            },
            categoriesPage: {
                areaLabel: 'Ripartizione · {year}',
                title: 'Ripartizione per categoria',
                description:
                    'Leggi come si distribuiscono gli importi tra le categorie nel periodo selezionato.',
                newCategory: 'Nuova categoria',
                allCategories: 'Tutte le categorie',
                totalComposition: 'Composizione totale',
                compositionHint: 'Click sul grafico per navigare i livelli.',
                selectedTotal: 'Totale selezionato',
                topCategories: 'Categorie principali',
                topCategoriesHint:
                    'Categorie con il peso maggiore nel perimetro selezionato.',
                mainCategory: 'Categoria principale',
                mainCategoryShare: 'Quota sul totale',
                activeCategories: 'Categorie attive',
                categoriesTracked: 'categorie con importi nel perimetro',
                topSubcategory: 'Sottocategoria rilevante',
                notAvailable: 'Non disponibile',
                subcategories: '{count} categorie incluse nel gruppo',
                oneSubcategory: '1 categoria inclusa nel gruppo',
                noSubcategory: 'Nessuna categoria figlia con importi',
                trendTitle: 'Trend uscite per categoria',
                trendDescription:
                    'Stack mensile delle principali categorie di uscita.',
                recentTitle: 'Movimenti recenti',
                recentDescription:
                    'Ultimi 30 giorni coerenti con i filtri correnti.',
                seeAll: 'Vedi tutti',
                unresolvedNote:
                    '{count} movimenti non sono inclusi per assenza di conversione affidabile in valuta base.',
                emptyComposition:
                    'Non ci sono ancora categorie con importi sufficienti per costruire la ripartizione.',
                emptyTrend:
                    'Non ci sono ancora uscite categorizzate per costruire il trend.',
                emptyRecent:
                    'Nessun movimento disponibile nel perimetro selezionato.',
                emptySummary:
                    'La sintesi resta disponibile, ma servono movimenti categorizzati per renderla decisionale.',
                excludeInternal: 'Escludi giroconti/CC',
            },
            accountsPage: {
                areaLabel: 'Visione per conto · {year}',
                title: 'Visione per conto',
                description:
                    'Saldo, andamento, flusso cassa e confronto tra conti nello stesso perimetro temporale.',
                addAccount: 'Aggiungi conto',
                export: 'Esporta',
                account: 'Conto',
                allAccounts: 'Tutti i conti',
                noAccount: 'Nessun conto',
                currentBalance: 'Saldo corrente',
                openingBalance: 'Saldo iniziale',
                assetShare: 'del patrimonio',
                activeAccounts: 'Conti attivi',
                balanceTrend: 'Andamento saldo',
                balanceTrendDescription:
                    'Evoluzione del saldo per conto nel periodo selezionato, con confronto leggibile rispetto a {previous}.',
                multiAccountTrend: 'Confronto multi-conto',
                emptyBalanceTrend:
                    'Non ci sono ancora saldi sufficienti per costruire un andamento affidabile.',
                income: 'Entrata',
                expense: 'Uscita',
                net: 'Netto',
                bestPeriod: 'Periodo migliore',
                vsPreviousYear: 'vs anno prec.',
                vsPreviousPeriod: 'vs periodo prec.',
                comparisonUnavailable: 'confronto non disponibile',
                noBestPeriod: 'nessun periodo utile',
                worstPeriod: 'peggiore',
                cashFlow: 'Flusso cassa mensile',
                cashFlowDescription:
                    'Entrate e uscite a specchio per vedere mesi positivi e negativi.',
                emptyCashFlow:
                    'Non ci sono movimenti sufficienti per costruire il flusso cassa.',
                distribution: 'Distribuzione tra conti',
                distributionDescription:
                    'Quanto pesa ogni conto sul patrimonio monitorato.',
                emptyDistribution:
                    'Non ci sono saldi positivi sufficienti per costruire la distribuzione.',
                total: 'Totale',
                topCategories: 'Top categorie del conto',
                topCategoriesDescription:
                    'Dove sta andando il denaro sul conto selezionato.',
                emptyTopCategories:
                    'Non ci sono ancora uscite categorizzate per questo conto nel periodo.',
                comparisonTable: 'Tabella comparativa',
                comparisonDescription:
                    'Confronto rapido di saldo, andamento, entrate, uscite, netto e quota patrimonio.',
                assetShareShort: 'Quota patrimonio',
                emptyComparison:
                    'Non ci sono conti nel perimetro selezionato da confrontare.',
                recentMovements: 'Ultimi movimenti del conto',
            },
        },
        categories: {
            filters: {
                focuses: {
                    all: 'Tutte',
                    income: 'Entrate',
                    expense: 'Uscite',
                    saving: 'Risparmi',
                },
            },
        },
        categoryAnalysis: {
            areaLabel: 'Analisi categoria · {year}',
            title: 'Analisi per categoria',
            description:
                'Andamento, confronti temporali e peso delle sottocategorie per il perimetro selezionato.',
            fallbackCategory: 'Categoria selezionata',
            category: 'Categoria',
            subcategory: 'Sottocategoria',
            searchCategory: 'Cerca categoria o sottocategoria...',
            emptyCategories: 'Nessuna categoria trovata',
            back: 'Indietro',
            allSubcategories: 'Tutte le sottocategorie',
            budget: {
                label: 'Budget',
                variance: 'Scostamento budget',
                unavailable: 'Budget non confrontabile nel perimetro attivo',
            },
            scope: {
                summaryLabel: 'Base di lettura',
                analysisLabel: 'Perimetro',
                actualLabel: 'Spesa reale',
                budgetLabel: 'Lettura budget',
                comparisonLabel: 'Confronti',
                summary: '{scope}. {actual} {budget} {comparison}',
                selectedOnly: '{category} selezionata',
                selectedWithDescendants:
                    '{category} e sue eventuali categorie discendenti',
                categoryWithDescendants:
                    '{category} con tutte le sottocategorie coerenti',
                none: 'Nessuna categoria selezionabile',
                actualNone:
                    'La spesa reale resta vuota finché non è disponibile una categoria di spesa.',
                actualLedger:
                    'Include solo movimenti confermati del ledger assegnati a {category} o a categorie figlie incluse nel perimetro.',
                budgetDirect:
                    'Confronto sul budget mensile assegnato a {category}, senza risorse o riferimenti personali.',
                budgetAggregated:
                    'Budget aggregato: somma i budget mensili configurati su {category} e sulle sue sottocategorie incluse.',
                budgetMissing:
                    'Nessun budget confrontabile trovato per categoria, periodo e perimetro attivo.',
                budgetAccountUnsupported:
                    'Il budget non viene confrontato quando il filtro risorsa limita il perimetro.',
                comparisonPreviousYear:
                    'Il confronto usa lo stesso intervallo dell’anno precedente e la stessa selezione categoria.',
                comparisonUnavailable:
                    'Il confronto anno precedente resta nascosto quando non esiste una base storica coerente.',
            },
            comparisons: {
                unavailable:
                    'Confronto non disponibile: mancano dati nello stesso periodo di riferimento.',
                unavailableShort: 'Non disponibile',
            },
            emptyDataset: {
                title: 'Nessuna spesa nel perimetro selezionato',
                message:
                    'Nel periodo {period} non ci sono movimenti reali per {scope}. I grafici basati sulla spesa restano vuoti finché non esiste una base ledger confermata.',
                budgetNote:
                    'Il budget, quando presente, resta visibile come pianificazione assegnata e non come spesa effettuata.',
            },
            insight: {
                badgeStable: 'Stabile',
                badgeWarning: 'Attenzione',
                badgeInfo: 'Nota',
            },
            kpis: {
                totalSpent: 'Totale speso',
                averagePeriod: 'Media periodo',
                averagePerDay: 'Media giornaliera',
                averagePerMonth: 'Media mensile',
                bestMonth: 'Miglior periodo',
                worstMonth: 'Peggior periodo',
                previousPeriod: 'vs periodo prec.',
                previousYear: 'vs anno prec.',
                noData: 'Non disponibile',
            },
            charts: {
                trendTitle: 'Trend temporale della categoria',
                trendDescription:
                    'Spesa reale nel tempo, calcolata dal ledger nel perimetro attivo.',
                breakdownTitle: 'Breakdown sottocategorie',
                breakdownDescription:
                    'Peso delle sottocategorie incluse nella categoria selezionata.',
                yearComparisonTitle:
                    'Anno corrente vs anno precedente',
                yearComparisonDescription:
                    'Mese per mese, stesso perimetro categoria e stessa base ledger.',
                cumulativeTitle: 'Andamento cumulato vs budget',
                cumulativeDescription:
                    'Spesa cumulata, budget cumulato e anno precedente quando il budget è confrontabile.',
                subcategoryTimelineTitle: 'Sottocategorie nel tempo',
                subcategoryTimelineDescription:
                    'Distribuzione mensile delle principali sottocategorie incluse nel perimetro.',
                emptyTrend:
                    'Non ci sono movimenti sufficienti per costruire il trend.',
                emptyBreakdown:
                    'Non ci sono sottocategorie con importi nel perimetro selezionato.',
                emptyYearComparison:
                    'Non ci sono dati storici coerenti per il confronto anno su anno.',
                emptyCumulative:
                    'Il cumulato budget non è disponibile nel perimetro attivo.',
                emptySubcategoryTimeline:
                    'Non ci sono sottocategorie sufficienti per costruire la timeline.',
            },
            table: {
                title: 'Dettaglio periodo',
                description:
                    'Spesa reale, budget assegnato, scostamenti e sottocategoria dominante per ogni periodo.',
                period: 'Periodo',
                spent: 'Speso',
                previousYear: 'Anno prec.',
                deltaPreviousYear: 'Delta vs anno prec.',
                dominantSubcategory: 'Voce dominante',
                empty: 'Nessun dettaglio disponibile nel perimetro selezionato.',
            },
            export: {
                xlsx: 'Excel',
                pdf: 'PDF',
            },
            emptyState:
                'Seleziona una categoria di spesa con movimenti confermati per vedere l’analisi.',
        },
        modules: {
            kpi: {
                title: 'Panoramica del periodo',
                description:
                    'Prima sezione analytics reale con KPI del periodo e trend temporale integrati nella stessa vista.',
            },
            categories: {
                title: 'Ripartizione per categoria',
                description:
                    'Lettura dedicata della composizione per categoria, sottocategoria e peso sul periodo.',
            },
            categoryAnalysis: {
                title: 'Analisi per categoria',
                description:
                    'KPI, trend e confronti temporali per una categoria o sottocategoria specifica.',
            },
            accounts: {
                title: 'Visione per conto',
                description:
                    'Sezione pronta per consolidati, focus per conto e confronti futuri multi-account.',
            },
        },
        metrics: {
            net: 'Netto',
            activeAccounts: 'Conti attivi',
            transactionsOne: '{count} movimento',
            transactionsMany: '{count} movimenti',
        },
        planning: {
            title: 'Preventivo / Budget',
            description: 'Area distinta di pianificazione e preventivazione.',
            body: 'Preventivo/Budget resta separato da Transazioni: qui in Report e raggiungibile come area secondaria coerente, senza occupare la bottom nav mobile primaria.',
            distinction:
                'Pianificazione = confronto previsto/reale e allocazioni. Report = lettura analitica del periodo.',
            cardSummary:
                'Accesso diretto all’area di pianificazione, distinta dalle sezioni analitiche del report.',
            cardStatus: 'Area secondaria coerente',
        },
        charts: {
            trendTitle: 'Trend entrate / uscite',
            trendDescription:
                'Base ECharts gia pronta per estendere la lettura del netto, confronti storici e drill-down del periodo.',
            expenseTitle: 'Ripartizione spese per categoria',
            expenseDescription:
                'Lettura sintetica delle categorie di uscita piu rilevanti nel periodo corrente.',
            expenseEmpty:
                'Nessuna spesa disponibile per costruire la ripartizione del periodo.',
            incomeTitle: 'Ripartizione entrate per categoria',
            incomeDescription:
                'Base frontend pronta per future letture su ricavi, fonti e concentrazione delle entrate.',
            incomeEmpty:
                'Nessuna entrata disponibile per costruire la ripartizione del periodo.',
            accountsTitle: 'Visione per conto',
            accountsDescription:
                'Saldo corrente per conto e base grafica pronta per confronti futuri tra conti, carte e scope condivisi.',
            accountsEmpty:
                'Non ci sono ancora conti attivi da rappresentare nella vista report.',
        },
        roadmap: {
            title: 'Pronta per la fase successiva',
            description:
                'L’area Report esiste gia nella shell ma resta volutamente un hub: i dettagli analytics arrivano per sezioni dedicate.',
            nextStepsTitle: 'Step successivi naturali',
            nextStepsBody:
                'Pagine sezione, backend analytics dedicato, filtri avanzati, confronto periodi, esportazioni report e drill-down per categoria o conto.',
        },
        index: {
            placeholderTitle: 'Prima pagina di esempio',
            placeholderDescription:
                'Questa vista desktop e il primo contenitore reale dell’area Report: qui innesteremo i primi grafici senza perdere la struttura generale.',
            exampleTitle: 'Placeholder iniziale',
            exampleBody:
                'Manteniamo una pagina essenziale con gerarchia, titoli e spazi pronti per KPI, trend e breakdown futuri.',
            deliveryTitle: 'Sviluppo incrementale',
            deliveryBody:
                'Ogni sezione laterale resta separata e verra implementata in rilascio dedicato, senza trasformare subito tutto in una dashboard finale.',
        },
        quickLinks: {
            title: 'Collegamenti rapidi',
            description: 'Accessi coerenti tra lettura e pianificazione.',
        },
        section: {
            backToLauncher: 'Torna al launcher Report',
            phaseTitle: 'Fase iniziale',
            phaseBody:
                'Questa pagina e una shell di sezione: struttura, ingresso dedicato e gerarchia sono pronti prima della parte analytics completa.',
            placeholderTitle: 'Sezione predisposta, non ancora definitiva',
            placeholderDescription:
                'Qui sviluppiamo il dominio dedicato un rilascio alla volta, senza comprimere tutte le logiche dentro una singola dashboard.',
            nowTitle: 'Cosa c’e adesso',
            nowBody:
                'Ingresso reale nella shell, copy di contesto, gerarchia chiara e base pronta per introdurre dati, filtri e componenti dedicati.',
            nextTitle: 'Cosa arriva dopo',
            nextBody:
                'Visualizzazioni, ECharts, metriche e backend analytics saranno introdotti in modo mirato quando lavoreremo su questa sezione specifica.',
            otherSectionsTitle: 'Altre sezioni Report',
            otherSectionsDescription:
                'Le altre aree restano raggiungibili dal launcher e da qui, cosi la navigazione resta coerente anche su mobile.',
        },
    },
    en: {
        title: 'Reports',
        areaLabel: 'Reports area',
        description:
            'Intermediate hub for report sections and future analytics releases.',
        navigationLabel: 'Reports area navigation',
        sidebarTitle: 'Report sections',
        hero: {
            description:
                'Access the main report areas to analyze trends, period results, and data distribution in a clear, organized way.',
        },
        launcher: {
            title: 'Reports section launcher',
            description:
                'Each card opens a dedicated section, ready for incremental work without turning the index into the final analytics dashboard too early.',
        },
        filters: {
            title: 'Report filters',
            description: 'Define the time scope and reading key.',
            year: 'Year',
            period: 'Period',
            referenceMonth: 'Reference month',
            resource: 'Resource / account',
            allResources: 'All resources',
            apply: 'Apply',
            reset: 'Reset',
            monthDisabledAnnual:
                'The reference month does not apply to the annual view, so it is removed from the filter set.',
            periods: {
                annual: 'Annual',
                monthly: 'Monthly',
                lastThreeMonths: 'Last 3 months',
                lastSixMonths: 'Last 6 months',
                ytd: 'Year to date',
            },
            periodSummaries: {
                annual: 'Year {year}',
                lastThreeMonths: 'Last 3 months through {month} {year}',
                lastSixMonths: 'Last 6 months through {month} {year}',
                ytd: 'YTD through {month} {year}',
            },
        },
        overview: {
            hero: {
                description:
                    'Main KPIs, income/expense trends, and full-year balance composition.',
            },
            kpis: {
                income: 'Income',
                expense: 'Expenses',
                net: 'Net',
                transactions: 'Transactions',
                transactionUnit: 'transaction',
                transactionsUnit: 'transactions',
                averageNet: 'Average net',
                averagePerDay: 'Average per day',
                averagePerMonth: 'Average per month',
                bestPeriod: 'Best period',
                notAvailable: 'Not available',
                periodTotal: 'Total for the selected scope',
                periodBalance: 'Balance for the selected scope',
                includedMovements: 'Manual non-transfer movements',
                previousPeriodHint: 'Compared with {period}',
            },
            emptyState:
                'There is not enough data yet for the selected scope. The page stays readable, and KPIs and charts will populate as soon as matching movements exist.',
            distribution: {
                title: 'Period balance',
                description:
                    'At-a-glance view of income and expenses for the period, with net balance highlighted at the center.',
                empty: 'There are not enough transactions yet to build the period summary.',
                centerLabel: 'Net',
                centerCaption: 'Balance for the selected period',
                legendShare: 'Share of total movement',
            },
            comparison: {
                title: 'Interval comparison',
                description:
                    'Comparative bar chart to spot where income and expenses widen or compress across the selected period.',
                empty: 'There is not enough data yet for the period comparison.',
            },
            snapshot: {
                title: 'Latest observed intervals',
                description:
                    'Quick summary of the latest report buckets to isolate where balance actually changes.',
                netLabel: 'Net balance',
                empty: 'There are no observed intervals with movement in the selected period yet.',
            },
            meta: {
                coverageNote:
                    '{count} movements are excluded from money totals because they are missing a reliable base-currency conversion.',
            },
            categoriesPage: {
                areaLabel: 'Breakdown · {year}',
                title: 'Category breakdown',
                description:
                    'Read how amounts are distributed across categories in the selected period.',
                newCategory: 'New category',
                allCategories: 'All categories',
                totalComposition: 'Total composition',
                compositionHint:
                    'Use the chart to navigate across hierarchy levels.',
                selectedTotal: 'Selected total',
                topCategories: 'Top categories',
                topCategoriesHint:
                    'Categories with the highest weight in the selected scope.',
                mainCategory: 'Main category',
                mainCategoryShare: 'Share of total',
                activeCategories: 'Active categories',
                categoriesTracked: 'categories with amounts in scope',
                topSubcategory: 'Top subcategory',
                notAvailable: 'Not available',
                subcategories: '{count} categories included in this group',
                oneSubcategory: '1 category included in this group',
                noSubcategory: 'No child categories with amounts',
                trendTitle: 'Expense trend by category',
                trendDescription:
                    'Monthly stack for the main outflow categories.',
                recentTitle: 'Recent movements',
                recentDescription:
                    'Latest 30 days aligned with the current filters.',
                seeAll: 'See all',
                unresolvedNote:
                    '{count} movements are excluded because they are missing a reliable base-currency conversion.',
                emptyComposition:
                    'There are no categories with enough amount yet to build the breakdown.',
                emptyTrend:
                    'There are no categorized outflows yet to build the trend.',
                emptyRecent:
                    'No movements are available for the selected scope.',
                emptySummary:
                    'The summary stays available, but it needs categorized movements before it becomes decision-ready.',
                excludeInternal: 'Exclude transfers/CC',
            },
            accountsPage: {
                areaLabel: 'Account view · {year}',
                title: 'Account view',
                description:
                    'Balance, trend, cash flow, and account comparison in the same reporting scope.',
                addAccount: 'Add account',
                export: 'Export',
                account: 'Account',
                allAccounts: 'All accounts',
                noAccount: 'No account',
                currentBalance: 'Current balance',
                openingBalance: 'Opening balance',
                assetShare: 'of assets',
                activeAccounts: 'Active accounts',
                balanceTrend: 'Balance trend',
                balanceTrendDescription:
                    'Balance evolution for each account in the selected period, with a readable comparison against {previous}.',
                multiAccountTrend: 'Multi-account comparison',
                emptyBalanceTrend:
                    'There is not enough balance history yet to build a reliable trend.',
                income: 'Income',
                expense: 'Expense',
                net: 'Net',
                bestPeriod: 'Best period',
                vsPreviousYear: 'vs prev. year',
                vsPreviousPeriod: 'vs prev. period',
                comparisonUnavailable: 'comparison unavailable',
                noBestPeriod: 'no useful period',
                worstPeriod: 'worst',
                cashFlow: 'Monthly cash flow',
                cashFlowDescription:
                    'Income and expenses mirrored to spot positive and negative months.',
                emptyCashFlow:
                    'There are not enough movements yet to build the cash flow.',
                distribution: 'Account distribution',
                distributionDescription:
                    'How much each account weighs on monitored assets.',
                emptyDistribution:
                    'There are not enough positive balances yet to build the distribution.',
                total: 'Total',
                topCategories: 'Top account categories',
                topCategoriesDescription:
                    'Where money is going for the selected account.',
                emptyTopCategories:
                    'There are no categorized expenses for this account in the selected period yet.',
                comparisonTable: 'Comparison table',
                comparisonDescription:
                    'Quick comparison of balance, trend, income, expenses, net, and asset share.',
                assetShareShort: 'Asset share',
                emptyComparison:
                    'There are no accounts in the selected scope to compare.',
                recentMovements: 'Latest account movements',
            },
        },
        categories: {
            filters: {
                focuses: {
                    all: 'All',
                    income: 'Income',
                    expense: 'Expenses',
                    saving: 'Savings',
                },
            },
        },
        categoryAnalysis: {
            areaLabel: 'Category analysis · {year}',
            title: 'Category analysis',
            description:
                'Trend, time comparisons, and subcategory weight for the selected scope.',
            fallbackCategory: 'Selected category',
            category: 'Category',
            subcategory: 'Subcategory',
            searchCategory: 'Search category or subcategory...',
            emptyCategories: 'No categories found',
            back: 'Back',
            allSubcategories: 'All subcategories',
            budget: {
                label: 'Budget',
                variance: 'Budget variance',
                unavailable: 'Budget is not comparable in the active scope',
            },
            scope: {
                summaryLabel: 'Reading basis',
                analysisLabel: 'Scope',
                actualLabel: 'Actual spend',
                budgetLabel: 'Budget reading',
                comparisonLabel: 'Comparisons',
                summary: '{scope}. {actual} {budget} {comparison}',
                selectedOnly: '{category} selected',
                selectedWithDescendants:
                    '{category} and any descendant categories',
                categoryWithDescendants:
                    '{category} with all coherent subcategories',
                none: 'No selectable category',
                actualNone:
                    'Actual spend stays empty until an expense category is available.',
                actualLedger:
                    'Includes only confirmed ledger movements assigned to {category} or included child categories.',
                budgetDirect:
                    'Compares the monthly budget assigned to {category}, excluding resources and tracked items.',
                budgetAggregated:
                    'Aggregated budget: sums monthly budgets configured on {category} and included subcategories.',
                budgetMissing:
                    'No comparable budget was found for the active category, period, and scope.',
                budgetAccountUnsupported:
                    'Budget comparison is disabled when the resource filter limits the scope.',
                comparisonPreviousYear:
                    'The comparison uses the same interval in the previous year and the same category selection.',
                comparisonUnavailable:
                    'Previous-year comparison stays hidden when no coherent historical baseline exists.',
            },
            comparisons: {
                unavailable:
                    'Comparison unavailable: matching reference-period data is missing.',
                unavailableShort: 'Not available',
            },
            emptyDataset: {
                title: 'No spend in the selected scope',
                message:
                    'In {period} there are no actual movements for {scope}. Spend-based charts stay empty until there is a confirmed ledger baseline.',
                budgetNote:
                    'When present, budget remains visible as assigned planning, not as actual spend.',
            },
            insight: {
                badgeStable: 'Stable',
                badgeWarning: 'Attention',
                badgeInfo: 'Note',
            },
            kpis: {
                totalSpent: 'Total spent',
                averagePeriod: 'Period average',
                averagePerDay: 'Daily average',
                averagePerMonth: 'Monthly average',
                bestMonth: 'Best period',
                worstMonth: 'Worst period',
                previousPeriod: 'vs previous period',
                previousYear: 'vs previous year',
                noData: 'Not available',
            },
            charts: {
                trendTitle: 'Category time trend',
                trendDescription:
                    'Actual spend over time, calculated from the ledger in the active scope.',
                breakdownTitle: 'Subcategory breakdown',
                breakdownDescription:
                    'Weight of the subcategories included in the selected category.',
                yearComparisonTitle: 'Current year vs previous year',
                yearComparisonDescription:
                    'Month by month, same category scope and same ledger basis.',
                cumulativeTitle: 'Cumulative trend vs budget',
                cumulativeDescription:
                    'Cumulative spend, cumulative budget, and previous year when budget is comparable.',
                subcategoryTimelineTitle: 'Subcategories over time',
                subcategoryTimelineDescription:
                    'Monthly distribution of the main subcategories included in scope.',
                emptyTrend:
                    'There are not enough movements to build the trend.',
                emptyBreakdown:
                    'There are no subcategories with amounts in the selected scope.',
                emptyYearComparison:
                    'There is no coherent historical data for year-over-year comparison.',
                emptyCumulative:
                    'Budget cumulative trend is not available in the active scope.',
                emptySubcategoryTimeline:
                    'There are not enough subcategories to build the timeline.',
            },
            table: {
                title: 'Period detail',
                description:
                    'Actual spend, assigned budget, variances, and dominant subcategory for each period.',
                period: 'Period',
                spent: 'Spent',
                previousYear: 'Prev. year',
                deltaPreviousYear: 'Delta vs previous year',
                dominantSubcategory: 'Dominant item',
                empty: 'No detail is available in the selected scope.',
            },
            export: {
                xlsx: 'Excel',
                pdf: 'PDF',
            },
            emptyState:
                'Select an expense category with confirmed movements to see the analysis.',
        },
        modules: {
            kpi: {
                title: 'Period overview',
                description:
                    'First real analytics section with period KPIs and time trend combined in one view.',
            },
            categories: {
                title: 'Category split',
                description:
                    'Dedicated reading for category composition, subcategories, and period weight.',
            },
            categoryAnalysis: {
                title: 'Category analysis',
                description:
                    'KPIs, trends, and time comparisons for a specific category or subcategory.',
            },
            accounts: {
                title: 'Account view',
                description:
                    'Section ready for future consolidated, per-account, and multi-account comparisons.',
            },
        },
        metrics: {
            net: 'Net',
            activeAccounts: 'Active accounts',
            transactionsOne: '{count} transaction',
            transactionsMany: '{count} transactions',
        },
        planning: {
            title: 'Planning / Budget',
            description: 'A distinct planning and budgeting area.',
            body: 'Planning/Budget stays separate from Transactions: inside Reports it remains available as a coherent secondary area without taking a primary slot in the mobile bottom navigation.',
            distinction:
                'Planning = forecast vs actual and allocations. Reports = analytical reading of the period.',
            cardSummary:
                'Direct access to the planning area, kept distinct from the analytical report sections.',
            cardStatus: 'Coherent secondary area',
        },
        charts: {
            trendTitle: 'Income / expense trend',
            trendDescription:
                'ECharts is already in place here to extend net reading, historical comparisons, and period drill-downs.',
            expenseTitle: 'Expense split by category',
            expenseDescription:
                'Compact view of the most relevant expense categories in the current period.',
            expenseEmpty:
                'No expense data is available yet to build the period split.',
            incomeTitle: 'Income split by category',
            incomeDescription:
                'Frontend base ready for future reads across income sources, concentration, and composition.',
            incomeEmpty:
                'No income data is available yet to build the period split.',
            accountsTitle: 'Account view',
            accountsDescription:
                'Current balances by account with a chart foundation ready for future comparisons across accounts, cards, and shared scopes.',
            accountsEmpty:
                'There are no active accounts yet to render in the reports view.',
        },
        roadmap: {
            title: 'Ready for the next phase',
            description:
                'The Reports area already exists in the shell, but it intentionally stays a hub while the analytics depth is delivered section by section.',
            nextStepsTitle: 'Natural next steps',
            nextStepsBody:
                'Dedicated section pages, analytics backend, advanced filters, period comparisons, report exports, and drill-downs by category or account.',
        },
        index: {
            placeholderTitle: 'First example page',
            placeholderDescription:
                'This desktop view is the first real container for the Reports area, where the first charts will land without losing the overall structure.',
            exampleTitle: 'Initial placeholder',
            exampleBody:
                'The page stays intentionally light, with hierarchy and room ready for future KPIs, trends, and breakdowns.',
            deliveryTitle: 'Incremental delivery',
            deliveryBody:
                'Each sidebar section remains separate and will be implemented in dedicated releases instead of forcing everything into one dashboard immediately.',
        },
        quickLinks: {
            title: 'Quick links',
            description: 'Coherent access between reading and planning.',
        },
        section: {
            backToLauncher: 'Back to reports launcher',
            phaseTitle: 'Initial phase',
            phaseBody:
                'This page is a section shell first: structure, dedicated entry, and hierarchy are in place before the full analytics layer arrives.',
            placeholderTitle: 'Section prepared, not final yet',
            placeholderDescription:
                'This domain will be developed one release at a time instead of compressing every reporting concern into a single page too early.',
            nowTitle: 'What exists now',
            nowBody:
                'A real shell entry point, clear hierarchy, focused copy, and a base ready for dedicated data, filters, and future components.',
            nextTitle: 'What comes next',
            nextBody:
                'Charts, ECharts, metrics, and analytics backend will be introduced deliberately once we work on this specific section.',
            otherSectionsTitle: 'Other report sections',
            otherSectionsDescription:
                'The other areas remain reachable from the launcher and from here, so navigation stays coherent on mobile too.',
        },
    },
} as const;
