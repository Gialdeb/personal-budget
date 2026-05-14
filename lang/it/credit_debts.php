<?php

return [
    'validation' => [
        'type_required' => 'Seleziona credito o debito.',
        'type_invalid' => 'Il tipo selezionato non è valido.',
        'description_required' => 'Inserisci una descrizione.',
        'amount_required' => 'Inserisci un importo.',
        'amount_numeric' => 'Inserisci un importo valido.',
        'amount_gt_zero' => 'Inserisci un importo maggiore di zero.',
        'currency_required' => 'La valuta è obbligatoria.',
        'currency_invalid' => 'La valuta selezionata non è disponibile.',
        'account_required' => 'Seleziona un conto.',
        'account_unavailable' => 'Il conto selezionato non è disponibile.',
        'account_currency_mismatch' => 'La valuta del conto deve coincidere con la valuta del credito/debito.',
        'category_required' => 'Seleziona una categoria.',
        'category_unavailable' => 'La categoria selezionata non è disponibile per questo conto.',
        'reference_unavailable' => 'Il riferimento selezionato non è disponibile per questo conto e categoria.',
        'due_date_required' => 'Inserisci una scadenza.',
        'paid_at_required' => 'Inserisci la data del pagamento.',
        'date_invalid' => 'Inserisci una data valida.',
        'payment_exceeds_remaining' => "L'importo inserito supera il residuo disponibile.",
        'locked_with_payments' => 'Non puoi modificare questo campo perché sono presenti pagamenti collegati.',
        'total_locked_with_payments' => "Non puoi modificare l'importo totale perché sono presenti pagamenti collegati. Elimina prima i singoli pagamenti.",
        'delete_item_with_payments' => "Non è possibile eliminare questo credito/debito perché sono presenti :count pagamenti collegati. Elimina prima i pagamenti, partendo dall'ultimo registrato.",
        'delete_latest_payment_required' => "Non puoi eliminare questo pagamento perché esistono pagamenti successivi. Elimina prima l'ultimo pagamento registrato.",
        'payment_transaction_mismatch' => 'La transazione collegata a questo pagamento non è valida.',
    ],
];
