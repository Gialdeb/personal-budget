# Ripristino PostgreSQL guidato

## Verifica e prova locale

Avvia `bash bin/restore-postgres-guided` dal checkout. Non serve Laravel.
Su macOS servono Python 3 e age: con Homebrew già installato, `brew install python age`.
Per la sola verifica non serve Docker. Per la prova serve Docker locale attivo e
l'immagine `postgres:17-alpine` già disponibile: lo script non la scarica.

Copia manualmente dal NAS l'intera cartella `postgres-...` in un percorso locale
esterno al repository. Non selezionare il singolo `.age`: servono anche
`database.list`, `manifest.json` e `SHA256SUMS`. Recupera separatamente la chiave
privata age e mantienila fuori dal repository e dal VPS, con permessi `600` e
proprietà dell'utente che esegue il recupero. Non usare la chiave SSH del NAS.
Incolla percorsi assoluti senza virgolette; gli spazi sono ammessi.

La voce 1 verifica SHA-256, manifest, autenticazione age, hash del dump decifrato,
formato custom e compatibilità PostgreSQL 17 della TOC registrata. I temporanei
sono privati e vengono rimossi. Non equivale a una prova effettiva di `pg_restore`.
Sono accettati anche i vecchi backup PostgreSQL in chiaro, senza chiave, con
`database.dump`, `database.list` e `SHA256SUMS`; viene verificato quanto disponibile.

La voce 2 richiede la frase `PROVA ISOLATA`. Riusa `restore-postgres-isolated`:
nuovo container PostgreSQL 17, nuovo volume anonimo dedicato, nessun bind mount,
rete `none`, nessuna porta esposta. Non legge configurazioni Soamco o credenziali
applicative e ignora le variabili Docker/PostgreSQL del chiamante. Verifica
l'isolamento prima degli accessi, confronta la TOC effettiva, esegue `pg_restore`
in transazione, `ANALYZE` e `pg_amcheck`. Container e volume vengono eliminati
anche in caso di errore, salvo problemi di Docker o mancata verifica di proprietà:
in tal caso il log segnala gli elementi da esaminare manualmente.

Il motore esistente accetta solo l'endpoint Unix locale del contesto Docker
`default`. Non cambia contesti o socket; se questo endpoint non funziona,
fermarsi senza aggirare le protezioni. Il database locale di Soamco e quello di
produzione non sono destinazioni selezionabili.

La procedura non contatta VPS/NAS e non esporta dump decifrati. INT/TERM attivano
la pulizia; SIGKILL, spegnimento o Docker indisponibile possono lasciare risorse
residue. Non condividere i log con nomi di file sensibili senza controllarli.

## Preparazione di un futuro intervento in produzione — NON eseguire ora

La voce 3 mostra soltanto questo documento. Non esiste un comando di ripristino
reale nel menu, né una conferma che possa sbloccarlo. La frase della prova locale
NON autorizza un ripristino reale.

Prima di ogni futuro intervento, preparare e far approvare un piano riferito alla
specifica destinazione. Raccogliere:

- identificativo/data UTC del backup, SHA-256 di `SHA256SUMS`, esito della prova
  isolata e controlli funzionali sui dati richiesti dal responsabile;
- identificativi verificati di VPS, progetto Docker, container, database e volume
  attuali e di quelli di recupero, evitando ogni volume esistente;
- versione applicativa, ruoli/estensioni PostgreSQL e impostazioni necessarie,
  che il solo `pg_dump` di un database non ricrea integralmente;
- configurazioni/APP_KEY e backup file eventualmente necessari: database e file
  a caldo non costituiscono uno snapshot atomicamente coerente;
- spazio disponibile, punto di ritorno e modalità per preservare integralmente
  il vecchio database e i vecchi volumi.

Predisporre un nuovo PostgreSQL 17 e un nuovo volume di recupero, inizialmente
senza collegamenti all'applicazione. Eseguire verifica e decifratura sulla macchina
di recupero fidata: la chiave privata non va copiata sul VPS. Definire, senza
avviarli, trasferimento protetto dei dati verso la nuova istanza, ripristino,
verifica ruoli/ownership/estensioni e collaudo applicativo.

**STOP obbligatorio prima di qualsiasi ripristino reale**, anche su una nuova
istanza destinata alla produzione: richiedere al responsabile una conferma
esplicita che identifichi backup, VPS, database e nuovo volume. Esempio di richiesta:

> Confermi «AUTORIZZO IL RIPRISTINO REALE», backup [ID + SHA-256], sul VPS [ID],
> database [NOME], nuovo volume [ID], mantenendo intatti database e volume attuali?

Una risposta generica, il silenzio o una conferma della prova locale non valgono.
La conferma deve essere acquisita al momento dell'intervento; questo documento
non la concede. Nessun comando operativo di produzione viene fornito dal menu.

Pianificare anche una conferma separata prima dello switch applicativo: usare il
lock deployment/backup esistente, una finestra con tutte le scritture applicative
fermate e richieste/job completati, poi validare il nuovo database e cambiare la
sola destinazione approvata. Se non si può verificare la quiescenza, fermarsi.
Tenere il vecchio database integro e definire quando lo switch può essere annullato:
dopo nuove scritture sulla nuova istanza, il ritorno alla vecchia richiede un piano
di riconciliazione. Non eliminare vecchi volumi né eseguire migrazioni automaticamente.

Questi passaggi sono un piano da completare con gli identificativi reali e una
nuova autorizzazione, non una procedura automatizzata né già autorizzata.
