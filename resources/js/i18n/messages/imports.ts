export const importsMessages = {
    it: {
        title: 'Importazioni',
        badge: 'Sezione operativa',
        description:
            "Usa il template XLSX ufficiale generato dall'app, controlla le righe classificate e lavora sempre sull'anno gestionale attivo del tuo profilo.",
        year: {
            label: 'Anno',
            current: "Stai lavorando sull'anno corrente.",
            other: "Stai consultando il {selectedYear}, diverso dall'anno corrente {currentYear}.",
            managementLabel: 'Anno gestionale {year}',
            managementNotice:
                "Ogni importazione viene controllata sull'anno gestionale {year}. Le righe di altri anni vengono bloccate e segnalate chiaramente.",
        },
        summary: {
            total: 'Import totali',
            review: 'Da verificare',
            completed: 'Completati',
            failed: 'Falliti',
        },
        index: {
            historyTitle: 'Storico import',
            historyDescription:
                'Carica un file, controlla le righe classificate e individua subito quelle da rivedere, bloccate o già importate.',
            downloadTemplate: 'Scarica template XLSX',
            newImportTitle: 'Nuovo import',
            newImportDescription:
                'Scarica il template XLSX ufficiale, compilalo e reimportalo sullo stesso anno gestionale attivo: {year}. Il conto viene letto dalla colonna del file, riga per riga.',
            fields: {
                importFormat: 'Formato import',
                csvFile: 'File XLSX',
            },
            placeholders: {
                genericFormat: 'Seleziona il formato guidato',
                chooseFile: 'Scegli file',
                noFileSelected: 'Nessun file selezionato',
            },
            helpers: {
                singleFormat:
                    'È l’unico formato attivo disponibile e viene selezionato automaticamente.',
                supportedHeaders:
                    'Intestazioni supportate: Conto, Data, Tipo, Importo, Dettaglio, Categoria, Conto destinazione, Riferimento, Esercente, Riferimento esterno.',
                templateFirst:
                    'Percorso consigliato: scarica il template XLSX ufficiale, compila le righe e ricarica lo stesso file senza cambiarne la struttura.',
                singleAccountColumn:
                    'Usa una sola colonna Conto. Se necessario, il valore può includere anche l’identificatore nel formato "Nome conto (uuid)".',
                destinationAccountOnlyTransfer:
                    'Conto di destinazione serve solo per i giroconti. Per entrate e uscite normali lascialo vuoto: il sistema lo ignora.',
                yearOnly: 'Inserisci solo righe riferite a {year}.',
                noActiveFormat: 'Nessun formato import attivo disponibile.',
                genericFormatLabel: 'Template XLSX guidato',
                genericFormatNotes:
                    'Formato ufficiale basato sul template XLSX generato dall’app.',
            },
            actions: {
                upload: 'Carica importazione',
                uploading: 'Caricamento in corso...',
                openDetail: 'Apri dettaglio',
                deleteImport: 'Elimina import',
                cancel: 'Annulla',
                previous: 'Precedente',
                next: 'Successiva',
            },
            alerts: {
                updated: 'Importazione aggiornata',
            },
            listSection: {
                title: 'Storico importazioni',
                description:
                    'Le importazioni più recenti con stato, parser e contatori riga.',
                paginationLabel: 'Paginazione importazioni',
                statusFilter: 'Stato import',
                empty: 'Nessuna importazione disponibile. Scarica il template XLSX ufficiale e carica il primo file per iniziare.',
                rows: 'Righe',
                ready: 'Pronte',
                review: 'Review',
                invalid: 'Non valide',
                duplicates: 'Duplicate',
                importsRange: 'Importazioni {from}-{to} su {total}',
                accountUnavailable: 'Conti letti dal file',
                statusLabels: {
                    all: 'Tutti',
                    review_required: 'Richiede revisione',
                    completed: 'Completati',
                    failed: 'Falliti',
                    rolled_back: 'Annullati',
                },
            },
            deleteDialog: {
                title: 'Eliminare questo import?',
                description:
                    "L'import verrà rimosso dallo storico solo se è già rollbackato e non ha più effetti sulle transazioni.",
            },
        },
        list: {
            statusBadge: {
                pending: 'In attesa',
                review_required: 'Richiede revisione',
                completed: 'Completato',
                failed: 'Fallito',
                rolled_back: 'Annullato',
            },
            filters: {
                all: 'Tutte',
                review: 'Da rivedere',
                invalid: 'Non valide',
                duplicate: 'Duplicate',
                ready: 'Pronte',
                imported: 'Importate',
                skipped: 'Saltate',
            },
        },
        show: {
            metaTitle: 'Importazione · {filename}',
            accountUnavailable: 'Conti letti dal file',
            uploadedOn: 'caricata il {date}',
            backToList: 'Torna alla lista',
            actions: {
                rollbackImport: 'Annulla import',
                deleteImport: 'Elimina import',
                importReady: 'Importa righe pronte',
                importingReady: 'Importazione righe in corso...',
                forceImport: 'Forza import',
                sending: 'Invio in corso...',
                edit: 'Modifica',
                skipping: 'Salto in corso...',
                skip: 'Salta',
                skipRow: 'Salta riga',
                details: 'Dettagli',
                close: 'Chiudi',
                confirmRollback: 'Conferma rollback',
                rollbackInProgress: 'Rollback in corso...',
                deleteInProgress: 'Eliminazione in corso...',
                cancel: 'Annulla',
            },
            metrics: {
                totalRows: 'Righe totali',
                ready: 'Pronte',
                review: 'Da rivedere',
                invalid: 'Non valide',
                duplicate: 'Duplicate',
                imported: 'Già importate',
            },
            infoCard: {
                title: 'Scheda import',
                format: 'Formato',
                completedAt: 'Completata il',
                failedAt: 'Fallita il',
                rolledBackAt: 'Rollback il',
            },
            alerts: {
                yearValidated:
                    'Questa importazione è stata validata sull’anno gestionale {year}.',
                actionCompleted: 'Azione completata',
                notCompleted: 'Importazione non completata',
                importError: 'Errore importazione',
                rowsOutsideYear: 'Righe fuori anno gestionale',
                rowsOutsideYearDescription:
                    '{count} {rowLabel} nell’anno gestionale {year} e vanno corrette nel file CSV.',
                importRolledBack: 'Import annullato',
                importRolledBackDescription:
                    'Questa importazione è stata annullata. Le righe importate sono state riportate allo stato di rollback.',
            },
            rowsSection: {
                title: 'Righe importate',
                description:
                    'Vista operativa compatta delle righe, con dettagli apribili solo quando servono.',
                readyToPromoteOne:
                    '1 riga pronta da promuovere nelle transazioni.',
                readyToPromoteMany:
                    '{count} righe pronte da promuovere nelle transazioni.',
                filterRows: 'Filtra righe',
                empty: 'Nessuna riga disponibile per questa importazione.',
                emptyFiltered:
                    'Nessuna riga corrisponde al filtro selezionato.',
                columns: {
                    row: 'Riga',
                    date: 'Data',
                    type: 'Tipo',
                    amount: 'Importo',
                    detail: 'Dettaglio',
                    category: 'Categoria',
                },
                unavailable: 'Non disponibile',
                detailUnavailable: 'Dettaglio non disponibile',
                categoryToReview: 'Da verificare',
                readyBadge: "Pronta per l'import",
                importedBadge: 'Già promossa in transazione',
                blockedBadge: 'Bloccata',
                parsing: 'Parsing: {status}',
                errorsTitle: 'Errori da gestire',
                feedbackTitle: 'Feedback riga',
                warningsTitle: 'Warning operativi',
                rawData: 'Dati letti dal file',
                normalizedData: 'Dati normalizzati',
                rawEmpty: 'La riga non espone dati raw.',
                normalizedEmpty: 'La riga non espone dati normalizzati.',
            },
            statusMessages: {
                ready: "La riga è pronta per l'importazione nelle transazioni.",
                imported:
                    'La riga è già stata importata correttamente nelle transazioni.',
                needsReview:
                    'La riga richiede una revisione prima di poter essere importata.',
                invalid:
                    'La riga contiene dati non validi e deve essere corretta.',
                blockedYear:
                    "La riga è bloccata perché non appartiene all'anno gestionale dell'import.",
                duplicateCandidate:
                    'La riga sembra un duplicato e richiede conferma manuale.',
                alreadyImported: 'La riga risulta già importata in precedenza.',
                skipped: 'La riga è stata saltata e non verrà importata.',
                rolledBack:
                    "La riga era stata importata, ma l'import è stato annullato.",
            },
            feedbackMessages: {
                categoryMissingReview:
                    'La categoria non è valorizzata e richiede revisione.',
                categoryUnknownReview:
                    'La categoria indicata non esiste nel gestionale e la riga richiede revisione.',
                alreadyImported:
                    'Questa riga risulta già importata in precedenza.',
                duplicateCurrentImport:
                    'Questa riga sembra duplicata nello stesso import.',
                skippedManually: 'Riga saltata manualmente dall’utente.',
            },
            skipDialog: {
                title: 'Saltare questa riga?',
                description:
                    'La riga verrà esclusa dal flusso corrente di import e non sarà importata nelle transazioni finché non verrà eventualmente rivalutata in seguito.',
            },
            duplicateDialog: {
                title: 'Confermare il duplicato candidato?',
                description:
                    "La riga verrà approvata manualmente e tornerà tra quelle pronte per l'import.",
            },
            rollbackDialog: {
                title: 'Annullare questa importazione?',
                description:
                    "L'azione elimina le transazioni create da questo import e porta le righe importate allo stato “Annullata”. Usala solo se l'import è stato già promosso e vuoi tornare indietro.",
            },
            deleteDialog: {
                title: 'Eliminare questo import?',
                description:
                    "L'azione rimuove definitivamente dallo storico un import già rollbackato e senza effetti contabili residui.",
            },
            singularRowOutsideYear: 'riga non rientra',
            pluralRowsOutsideYear: 'righe non rientrano',
        },
        reviewDialog: {
            title: 'Modifica riga',
            description:
                'Correggi i dati letti dal file e salva per rivalidare subito la riga.',
            fields: {
                account: 'Conto',
                date: 'Data',
                type: 'Tipo',
                amount: 'Importo',
                category: 'Categoria',
                destinationAccount: 'Conto destinazione',
                detail: 'Dettaglio',
                reference: 'Riferimento',
                merchant: 'Esercente',
                externalReference: 'Riferimento esterno',
            },
            placeholders: {
                account: 'Seleziona il conto corretto',
                date: 'GG/MM/AAAA',
                type: 'Seleziona un tipo',
                amount: '12,50',
                category: 'Seleziona categoria',
                categorySearch: 'Cerca categoria',
                categoryInvalid: 'Scegli una categoria valida',
                destinationAccount: 'Seleziona un conto destinazione',
                detail: 'Descrizione movimento',
                reference: 'Riferimento',
                referenceSearch: 'Cerca riferimento',
                referenceCreate: 'Aggiungi nuovo riferimento',
                merchant: 'Esercente',
                externalReference: 'Riferimento esterno',
            },
            emptyCategories:
                'Non ci sono ancora categorie disponibili per questo utente.',
            categoryHelper:
                'Seleziona una categoria del gestionale con lo stesso selettore gerarchico usato nelle impostazioni.',
            referenceHelper:
                'Il riferimento usa i tracking item del tuo profilo. Puoi cercarne uno esistente o crearne uno nuovo nel contesto corrente.',
            errors: {
                invalidTypeForTrackedItem:
                    'Il riferimento non è disponibile per i giroconti.',
                trackedItemContextRequired:
                    'Per creare un riferimento scegli prima conto, tipo e categoria corretti.',
                createTrackedItemFailed:
                    'Non è stato possibile creare il nuovo riferimento.',
            },
            importedCategory: 'Categoria letta dal file',
            accountHelper:
                'Il conto della riga arriva dal file. Correggilo qui solo quando il matching non è sicuro.',
            destinationSource: 'conto sorgente',
            destinationHelper:
                'Serve per completare il giroconto verso il conto corretto.',
            close: 'Chiudi',
            save: 'Salva e rivalida',
            saving: 'Salvataggio...',
        },
    },
    en: {
        title: 'Imports',
        badge: 'Operational area',
        description:
            'Use the official XLSX template generated by the app, review classified rows, and always work on the active management year from your profile.',
        year: {
            label: 'Year',
            current: 'You are working on the current year.',
            other: 'You are viewing {selectedYear}, different from the current year {currentYear}.',
            managementLabel: 'Management year {year}',
            managementNotice:
                'Every import is checked against management year {year}. Rows from other years are blocked and clearly flagged.',
        },
        summary: {
            total: 'Total imports',
            review: 'To review',
            completed: 'Completed',
            failed: 'Failed',
        },
        index: {
            historyTitle: 'Import history',
            historyDescription:
                'Upload a file, review classified rows, and immediately spot rows that need review, are blocked, or already imported.',
            downloadTemplate: 'Download XLSX template',
            newImportTitle: 'New import',
            newImportDescription:
                'Download the official XLSX template, fill it in, and re-upload it for the same active management year: {year}. The account is read from the file column, row by row.',
            fields: {
                importFormat: 'Import format',
                csvFile: 'XLSX file',
            },
            placeholders: {
                genericFormat: 'Select the guided format',
                chooseFile: 'Choose file',
                noFileSelected: 'No file selected',
            },
            helpers: {
                singleFormat:
                    'This is the only active format available and it is selected automatically.',
                supportedHeaders:
                    'Supported headers: Account, Date, Type, Amount, Detail, Category, Destination account, Reference, Merchant, External reference.',
                templateFirst:
                    'Recommended path: download the official XLSX template, fill in the rows, and upload the same file back without changing its structure.',
                singleAccountColumn:
                    'Use a single Account column. When needed, the value may also include the identifier in the "Account name (uuid)" format.',
                destinationAccountOnlyTransfer:
                    'Destination account is only for transfer rows. For regular income and expense rows leave it empty: the system ignores it.',
                yearOnly: 'Only include rows related to {year}.',
                noActiveFormat: 'No active import format available.',
                genericFormatLabel: 'Guided XLSX template',
                genericFormatNotes:
                    'Official format based on the XLSX template generated by the app.',
            },
            actions: {
                upload: 'Upload import',
                uploading: 'Uploading...',
                openDetail: 'Open detail',
                deleteImport: 'Delete import',
                cancel: 'Cancel',
                previous: 'Previous',
                next: 'Next',
            },
            alerts: {
                updated: 'Import updated',
            },
            listSection: {
                title: 'Imports history',
                description:
                    'Most recent imports with status, parser, and row counters.',
                paginationLabel: 'Imports pagination',
                statusFilter: 'Import status',
                empty: 'No imports available. Download the official XLSX template and upload your first file to get started.',
                rows: 'Rows',
                ready: 'Ready',
                review: 'Review',
                invalid: 'Invalid',
                duplicates: 'Duplicates',
                importsRange: 'Imports {from}-{to} of {total}',
                accountUnavailable: 'Accounts read from file',
                statusLabels: {
                    all: 'All',
                    review_required: 'Requires review',
                    completed: 'Completed',
                    failed: 'Failed',
                    rolled_back: 'Rolled back',
                },
            },
            deleteDialog: {
                title: 'Delete this import?',
                description:
                    'The import will be removed from history only if it has already been rolled back and no longer affects transactions.',
            },
        },
        list: {
            statusBadge: {
                pending: 'Pending',
                review_required: 'Requires review',
                completed: 'Completed',
                failed: 'Failed',
                rolled_back: 'Rolled back',
            },
            filters: {
                all: 'All',
                review: 'Needs review',
                invalid: 'Invalid',
                duplicate: 'Duplicates',
                ready: 'Ready',
                imported: 'Imported',
                skipped: 'Skipped',
            },
        },
        show: {
            metaTitle: 'Import · {filename}',
            accountUnavailable: 'Accounts read from file',
            uploadedOn: 'uploaded on {date}',
            backToList: 'Back to list',
            actions: {
                rollbackImport: 'Rollback import',
                deleteImport: 'Delete import',
                importReady: 'Import ready rows',
                importingReady: 'Importing rows...',
                forceImport: 'Force import',
                sending: 'Sending...',
                edit: 'Edit',
                skipping: 'Skipping...',
                skip: 'Skip',
                skipRow: 'Skip row',
                details: 'Details',
                close: 'Close',
                confirmRollback: 'Confirm rollback',
                rollbackInProgress: 'Rollback in progress...',
                deleteInProgress: 'Deleting...',
                cancel: 'Cancel',
            },
            metrics: {
                totalRows: 'Total rows',
                ready: 'Ready',
                review: 'Needs review',
                invalid: 'Invalid',
                duplicate: 'Duplicates',
                imported: 'Already imported',
            },
            infoCard: {
                title: 'Import card',
                format: 'Format',
                completedAt: 'Completed on',
                failedAt: 'Failed on',
                rolledBackAt: 'Rolled back on',
            },
            alerts: {
                yearValidated:
                    'This import was validated on management year {year}.',
                actionCompleted: 'Action completed',
                notCompleted: 'Import not completed',
                importError: 'Import error',
                rowsOutsideYear: 'Rows outside management year',
                rowsOutsideYearDescription:
                    '{count} {rowLabel} within management year {year} and must be fixed in the CSV file.',
                importRolledBack: 'Import rolled back',
                importRolledBackDescription:
                    'This import has been rolled back. Imported rows were returned to rollback status.',
            },
            rowsSection: {
                title: 'Imported rows',
                description:
                    'Compact operational view of rows, with expandable details only when needed.',
                readyToPromoteOne:
                    '1 row ready to be promoted into transactions.',
                readyToPromoteMany:
                    '{count} rows ready to be promoted into transactions.',
                filterRows: 'Filter rows',
                empty: 'No rows available for this import.',
                emptyFiltered: 'No rows match the selected filter.',
                columns: {
                    row: 'Row',
                    date: 'Date',
                    type: 'Type',
                    amount: 'Amount',
                    detail: 'Detail',
                    category: 'Category',
                },
                unavailable: 'Unavailable',
                detailUnavailable: 'Detail unavailable',
                categoryToReview: 'To review',
                readyBadge: 'Ready for import',
                importedBadge: 'Already promoted to transaction',
                blockedBadge: 'Blocked',
                parsing: 'Parsing: {status}',
                errorsTitle: 'Errors to address',
                feedbackTitle: 'Row feedback',
                warningsTitle: 'Operational warnings',
                rawData: 'Data read from file',
                normalizedData: 'Normalized data',
                rawEmpty: 'This row does not expose raw data.',
                normalizedEmpty: 'This row does not expose normalized data.',
            },
            statusMessages: {
                ready: 'This row is ready to be imported into transactions.',
                imported:
                    'This row has already been imported into transactions successfully.',
                needsReview:
                    'This row requires review before it can be imported.',
                invalid:
                    'This row contains invalid data and must be corrected.',
                blockedYear:
                    'This row is blocked because it does not belong to the import management year.',
                duplicateCandidate:
                    'This row looks like a duplicate and requires manual confirmation.',
                alreadyImported:
                    'This row appears to have already been imported.',
                skipped: 'This row was skipped and will not be imported.',
                rolledBack:
                    'This row had been imported, but the import was rolled back.',
            },
            feedbackMessages: {
                categoryMissingReview:
                    'The category is missing and requires review.',
                categoryUnknownReview:
                    'The specified category does not exist in the app and the row requires review.',
                alreadyImported:
                    'This row appears to have already been imported.',
                duplicateCurrentImport:
                    'This row appears duplicated within the same import.',
                skippedManually: 'Row skipped manually by the user.',
            },
            skipDialog: {
                title: 'Skip this row?',
                description:
                    'The row will be excluded from the current import flow and will not be imported into transactions unless reevaluated later.',
            },
            duplicateDialog: {
                title: 'Confirm duplicate candidate?',
                description:
                    'The row will be manually approved and returned to the ready-for-import list.',
            },
            rollbackDialog: {
                title: 'Rollback this import?',
                description:
                    'This action removes the transactions created by this import and moves imported rows to the “Rolled back” state. Use it only if the import has already been promoted and you want to revert it.',
            },
            deleteDialog: {
                title: 'Delete this import?',
                description:
                    'This action permanently removes from history an import that has already been rolled back and has no residual accounting effects.',
            },
            singularRowOutsideYear: 'row does not fall',
            pluralRowsOutsideYear: 'rows do not fall',
        },
        reviewDialog: {
            title: 'Edit row',
            description:
                'Correct the data read from the file and save to revalidate the row immediately.',
            fields: {
                account: 'Account',
                date: 'Date',
                type: 'Type',
                amount: 'Amount',
                category: 'Category',
                destinationAccount: 'Destination account',
                detail: 'Detail',
                reference: 'Reference',
                merchant: 'Merchant',
                externalReference: 'External reference',
            },
            placeholders: {
                account: 'Select the correct account',
                date: 'DD/MM/YYYY',
                type: 'Select a type',
                amount: '12.50',
                category: 'Select category',
                categorySearch: 'Search category',
                categoryInvalid: 'Choose a valid category',
                destinationAccount: 'Select a destination account',
                detail: 'Transaction description',
                reference: 'Reference',
                referenceSearch: 'Search reference',
                referenceCreate: 'Add new reference',
                merchant: 'Merchant',
                externalReference: 'External reference',
            },
            emptyCategories:
                'There are no categories available for this user yet.',
            categoryHelper:
                'Select a category using the same hierarchical picker used in settings.',
            referenceHelper:
                'Reference uses your tracked items. Search an existing one or create a new one for the current context.',
            errors: {
                invalidTypeForTrackedItem:
                    'Reference is not available for transfers.',
                trackedItemContextRequired:
                    'Choose account, type, and category before creating a reference.',
                createTrackedItemFailed:
                    'The new reference could not be created.',
            },
            importedCategory: 'Category read from file',
            accountHelper:
                'The row account comes from the file. Correct it here only when matching is not safe.',
            destinationSource: 'source account',
            destinationHelper:
                'Needed to complete the transfer toward the correct account.',
            close: 'Close',
            save: 'Save and revalidate',
            saving: 'Saving...',
        },
    },
} as const;
