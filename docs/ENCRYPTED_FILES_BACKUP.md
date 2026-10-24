# Backup cifrato giornaliero dei file di produzione

`bin/backup-production-files` crea una copia a caldo di file persistenti e configurazioni. **Non garantisce coerenza atomica tra PostgreSQL e file**, né un unico istante comune fra tutti i file. Non ferma applicazione, Horizon o scheduler. Se un file cambia durante la sua lettura, annulla il backup anziché pubblicare una copia incompleta.

## Contenuto

- Volumi `soamco_budget_public_storage` e `soamco_budget_private_storage`, individuati dai mount dell'app e dalle etichette Compose. Il prefisso effettivo dei volumi dipende dal progetto Docker.
- File d'ambiente Laravel e, se usato, file di interpolazione Compose.
- Caddyfile effettivamente montato dal proxy e i due file Compose del checkout.
- Ambiente effettivo del container applicativo, revisione del checkout, riferimenti delle immagini e manifest con SHA-256 dei singoli file.

Sono esclusi `backups/`, `pending-reference-logos/`, `tmp/`, `temp/`, `cache/`, `.cache/` e le directory personalizzate dei backup applicativi dichiarate nell'ambiente dell'app. Non sono acquisiti PostgreSQL, Redis, log, certificati Caddy, cache del framework o filesystem dei container. Symlink e file speciali nello storage incluso causano un errore: non vengono seguiti.

Il ripristino necessita anche del dump PostgreSQL e del codice della versione corretta. I file del database PostgreSQL non vengono copiati direttamente.

## Chiavi: nessuna identità privata sul VPS

Usare `age` con **un solo destinatario pubblico X25519 nativo**, nel formato `age1…`.

1. Predisporre e custodire la coppia di chiavi su una macchina fidata fuori dal VPS. Questa implementazione non genera chiavi.
2. Conservare la chiave privata sul dispositivo di recupero/NAS e in una copia offline protetta. Non inserirla nel repository, nei file d'ambiente, nel VPS o nei comandi di backup.
3. Sul VPS collocare soltanto il destinatario pubblico in `/etc/soamco-backup/files.recipient`: una riga, senza commenti o altre chiavi, proprietario root e non scrivibile da gruppo/altri.
4. Verificare il destinatario con un canale fidato prima di usarlo. Anche se pubblica, la chiave deve essere protetta dalla sostituzione.
5. In caso di rotazione, conservare le vecchie identità private finché esistono archivi cifrati per esse, compresa l'ultima copia protetta che può superare i dieci giorni.

Non usare in questo file l'output completo di un generatore di identità: le identità private vengono rifiutate. Non vengono impiegati plugin, password o chiavi SSH come destinatari.

## Configurazione sul VPS

Richiede Python 3, `age`, Git, Docker locale e il gruppo `soamco-backup`. Non installa dipendenze automaticamente. Nessuna dipendenza dell'applicazione viene modificata.

Preparare `/etc/soamco-backup/files.json` a partire da `docker/soamco-files-backup.json.example`, adattando i percorsi reali:

- `checkout`: checkout di produzione contenente i due file Compose.
- `runtime_env_file`: file scelto per `SOAMCO_ENV_FILE` durante il deployment.
- `compose_env_file`: file scelto per `SOAMCO_COMPOSE_ENV_FILE`, oppure `null` se non usato.
- `recipient_file`: percorso assoluto del solo destinatario pubblico.
- `minimum_free_mib`: riserva minima, predefinita nell'esempio a 1024 MiB.

La configurazione del backup e il destinatario devono appartenere a root, senza scrittura per gruppo/altri e senza symlink. I file di configurazione sorgente possono appartenere a root o al proprietario del checkout, sempre senza scrittura per gruppo/altri. I volumi devono usare il driver locale, senza opzioni di mount aggiuntive; il Docker remoto viene escluso usando esplicitamente il socket Unix locale.

Il controllo preventivo richiede tre volte la stima dei dati e dell'overhead TAR, più la riserva. È una stima prudenziale, non una prenotazione del disco: altri processi possono consumare spazio durante la copia. Qualunque errore di scrittura annulla la procedura e pulisce l'area temporanea della corrente esecuzione. Non cancella archivi esistenti per liberare spazio.

## Verifiche e pubblicazione

Prima di cifrare viene riletto integralmente il gzip, verificato il TAR e confrontato ogni file con il manifest interno. `age --encrypt` deve terminare senza errori. Vengono poi controllati header age v1, presenza della stanza X25519 e dimensione attesa del payload, oltre ai checksum SHA-256 finali.

Il risultato pubblicato atomicamente è:

```text
/var/backups/soamco/files-encrypted-AAAAMMGGTHHMMSSZ-xxxxxxxxxx/
    files.tar.gz.age
    manifest.json
    SHA256SUMS
```

Il manifest dettagliato e l'ambiente con i segreti sono **dentro il TAR cifrato**. Il manifest esterno contiene solo versione del formato, esiti delle verifiche, dimensione/hash del TAR e impronta del destinatario pubblico. Le directory finali sono `root:soamco-backup` con modo `0750`; i tre file hanno modo `0640`.

L'area `.files-incomplete-*` è privata durante la produzione del TAR in chiaro. Il TAR viene eliminato prima che gli artefatti finali siano accessibili al gruppo. La rimozione non equivale a cancellazione fisica sicura dei settori. Dopo un arresto non intercettabile possono restare aree temporanee private: non sono backup pubblicati e vanno esaminate da root, senza cancellazioni ricorsive generiche.

**Il VPS non può verificare la decifratura né autenticare il MAC age senza un'identità privata.** Il manifest lo dichiara (`decryption_verified_on_vps: false`). Le verifiche locali non sostituiscono una prova di recupero fuori dal VPS; SHA-256 da solo non autentica la provenienza.

## Conservazione e pianificazione

Il timer predisposto esegue il backup alle **03:30 UTC** ogni giorno. Riutilizza il lock del deployment e dei backup PostgreSQL. Se occupato, termina con codice 75 senza eseguire copie o retention. Non rimuove né cambia i permessi del lock esistente.

La retention elimina soltanto directory di questo formato più vecchie di dieci giorni UTC, con manifest, struttura e hash riconosciuti. Non segue symlink, non usa cancellazioni ricorsive per gli archivi pubblicati e lascia intatti file inattesi. Si avvia solo dopo una nuova copia verificata e protegge quella copia: l'ultima valida viene conservata anche oltre dieci giorni in caso di guasti prolungati. Non è quindi un limite temporale assoluto.

Quando autorizzato, installare lo script root-owned come `/usr/local/sbin/soamco-backup-files` e le unità `soamco-files-backup.service` e `.timer`; prima verificarle con systemd sul VPS. Configurare i file sopra e solo successivamente abilitare il timer. Questa implementazione non installa né attiva unità e non esegue backup reali. I log sono nel journal della nuova unità.

Il trasferimento NAS di questi archivi **non è implementato**: il pull PostgreSQL attuale continua a riconoscere esclusivamente `postgres-*`.

## Prova di recupero fuori dal VPS

Su una macchina fidata, con l'identità privata custodita separatamente, verificare prima i due SHA-256. Decifrare poi `files.tar.gz.age` con `age --decrypt --identity <identità-privata>` in una directory privata, confrontare hash e dimensione del TAR con il manifest esterno e verificare il manifest interno e i file. Ispezionare il TAR prima dell'estrazione; non estrarlo direttamente sul filesystem di produzione. L'errore di autenticazione age deve interrompere il recupero.

## Test

```bash
php artisan test --compact tests/Unit/EncryptedPersistentFilesBackupTest.php
```

I test usano solo file sintetici, Docker/Git/age simulati e percorsi temporanei. Verificano il TAR reale e il protocollo di pubblicazione, non la crittografia dell'eseguibile `age`. Non generano né conservano chiavi, non contattano Docker e non accedono alla produzione.
