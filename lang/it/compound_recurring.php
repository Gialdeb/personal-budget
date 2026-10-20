<?php

return [
    'name_invalid' => 'Inserisci un nome valido di massimo 100 caratteri.',
    'edit_protected' => 'Questa componente ha pagamenti o collegamenti economici protetti: nome, data e riferimento non possono essere modificati.',
    'archived' => 'Componente rimossa dal piano. Lo storico è conservato.',
    'read_only' => 'La componente è archiviata: lo storico è consultabile in sola lettura.',
    'account_unavailable' => 'Seleziona un conto modificabile con una valuta configurata.',
    'category_unavailable' => 'Seleziona una categoria valida.',
    'duplicate_component' => 'Il nome del pagamento è già presente nel piano.',
    'protected_component' => 'Questa componente non può essere eliminata o trasferita ad altro conto, valuta o tipo di piano. Gestisci le singole scadenze dal dettaglio.',
    'date_required' => 'Inserisci la data della prima scadenza.',
    'date_invalid' => 'Inserisci una data valida nel formato giorno/mese/anno.',
    'name_required' => 'Inserisci il nome del pagamento.',
    'amount_required' => 'Inserisci l’importo previsto del pagamento.',
    'amount_invalid' => 'Inserisci un importo maggiore di zero, con al massimo due decimali.',
    'fields' => [
        'name' => 'nome del piano', 'account' => 'conto', 'category' => 'categoria',
        'direction' => 'tipo di movimento', 'components' => 'pagamenti', 'component' => 'pagamento',
        'component_name' => 'nome del pagamento', 'date' => 'data della scadenza',
        'amount' => 'importo previsto', 'reminders' => 'anticipi del promemoria', 'reminder' => 'anticipo del promemoria',
    ],
];
