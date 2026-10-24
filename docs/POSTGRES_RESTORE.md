# Ripristino PostgreSQL 17 in isolamento

Questa procedura recupera il **solo database** prodotto da `bin/backup-production-db`. Non recupera file pubblici/privati, `.env`, `APP_KEY`, Redis o gli altri servizi. Non usa Laravel e funziona anche se l'applicazione non si avvia.

Il comando crea sempre un container nuovo. Non accetta host, porta, database o container di destinazione. Non esiste una modalità di ripristino sulla produzione.

## 1. Recuperare una copia dal NAS

Come Alex, aprire File Manager di UGOS e raggiungere:

```text
/volume1/Web Backups/Soamco Budget/postgresql
```

Scegliere una cartella `postgres-AAAAMMGGTHHMMSSZ-xxxxxxxxxx`. Il timestamp è UTC. Scaricare la cartella completa sulla macchina di recupero, senza spostarla o cancellarla sul NAS. Estrarre l'eventuale ZIP di trasporto generato da File Manager.

I tre file necessari devono trovarsi direttamente nella cartella selezionata:

```text
database.dump
database.list
SHA256SUMS
```

L'eventuale `.soamco-nas-receipt.json` è ammesso ma non è necessario al ripristino. Non usare le cartelle `.pull-incomplete-*` né gli ZIP dei backup applicativi.

`SHA256SUMS` è il manifest effettivamente prodotto dal backup: contiene esattamente i due hash di `database.dump` e `database.list`. Non esiste un ulteriore manifest JSON del backup PostgreSQL. I checksum verificano l'integrità, non l'autenticità: usare copie provenienti dal proprio NAS/VPS.

## 2. Preparare la macchina di recupero

Usare preferibilmente una macchina separata dal VPS, con Python 3 e Docker Engine locale per container Linux. Il contesto Docker `default` deve puntare a un socket Unix locale. Il comando rifiuta contesti remoti, ignora le variabili `DOCKER_*` e `PG*` ereditate e non legge `.env`.

Occorre spazio per una copia privata del dump e per il database ripristinato. Il container ha limiti di 1 GiB di RAM e 1 CPU; la sua nuova area dati usa un volume Docker anonimo, senza collegamenti ai volumi esistenti.

L'immagine ufficiale deve essere già disponibile localmente. Solo quando si decide di effettuare il recupero, se necessario:

```bash
docker --context default pull postgres:17-alpine
```

Lo script non scarica immagini automaticamente. Se il backup richiede estensioni non disponibili nell'immagine, il ripristino fallisce e la sandbox viene rimossa: non tentare un ripristino parziale.

## 3. Ripristinare e verificare

Dalla copia del repository sul branch `release/3.5.4-beta`, indicare la directory scaricata, sostituendo il nome d'esempio con quello reale:

```bash
python3 bin/restore-postgres-isolated \
  "$HOME/Downloads/postgres-20261024T030000Z-abcdefghij"
```

Se i file scaricati sono scrivibili da gruppo/altri, togliere quei permessi **alla copia locale** prima di procedere. Il comando non cambia i permessi del NAS.

La procedura:

1. Copia i tre file in una directory temporanea privata e verifica struttura, manifest e SHA-256.
2. Accetta solo archivi custom provenienti da PostgreSQL 17 e `pg_dump` 17.
3. Crea una sandbox con rete `none`, nessuna porta pubblicata, filesystem principale in sola lettura e un volume dati nuovo. PostgreSQL ascolta solo sul socket Unix interno.
4. Verifica l'identità e l'isolamento del container prima di utilizzarlo. Confronta l'indice letto da `pg_restore --list` con quello verificato del backup.
5. Esegue `pg_restore` in una singola transazione, fermandosi al primo errore. Proprietari, privilegi e tablespace originali non vengono applicati; gli oggetti appartengono all'utente isolato `soamco_restore`.
6. Esegue `ANALYZE`, `pg_amcheck` e un riepilogo delle relazioni. Questi controlli non sostituiscono le verifiche funzionali applicative.
7. Rimuove il container e il suo volume dati, anche dopo errori o interruzioni gestibili. I backup originali restano intatti. Se identità/isolamento non sono più verificabili, rifiuta anche la cancellazione e segnala il nome da esaminare manualmente.

Un messaggio `SUCCESS restore, ANALYZE and pg_amcheck completed` conferma il ripristino di verifica. Il processo restituisce un codice diverso da zero se un controllo o la pulizia fallisce. I messaggi non stampano i dati SQL o le credenziali.

### Conservare la sandbox per esaminare i dati

Ripetere il comando con `--keep` soltanto quando si desidera mantenere il database isolato dopo tutti i controlli:

```bash
python3 bin/restore-postgres-isolated \
  "$HOME/Downloads/postgres-20261024T030000Z-abcdefghij" --keep
```

Il messaggio `KEPT` indica l'ID del container. Per esaminare **quel container**, sostituire `ID_RESTITUITO`:

```bash
docker --context default exec -it --user postgres ID_RESTITUITO \
  psql -X --host=/var/run/postgresql --username=soamco_restore --dbname=soamco_restore
```

La sandbox resta senza accesso alla rete e non viene collegata a Laravel. Il trasferimento dei dati in produzione è intenzionalmente fuori da questa procedura.

Per eliminarla, prendere dal nome `soamco-pg-restore-TOKEN` il token indicato nei log, quindi usare entrambi i filtri. Sostituire `TOKEN` in tutte le occorrenze:

```bash
docker --context default rm --force --volumes \
  "$(docker --context default ps -aq --no-trunc \
    --filter 'name=^/soamco-pg-restore-TOKEN$' \
    --filter 'label=io.soamco.isolated-restore=TOKEN')"
```

Il filtro seleziona solo la sandbox marcata; non usare `prune`, nomi dei servizi Compose o volumi della produzione. Dopo un arresto non intercettabile, la stessa selezione permette di ritrovare la sandbox residua.

## 4. Prova automatizzata con dati sintetici

È disponibile una prova completa, che non legge backup o dati applicativi:

```bash
python3 bin/restore-postgres-isolated --self-test
```

Genera nella sandbox un database sorgente con righe sintetiche, chiave esterna, sequenza, vista e funzione; produce un archivio `-Fc`, elimina il database sorgente e ripristina il dump nel database distinto `soamco_restore`. Controlla righe, vista, avanzamento della sequenza, funzione, chiave esterna validata e integrità fisica, poi elimina la sandbox.

I test rapidi usano Docker simulato e non effettuano ripristini:

```bash
php artisan test --compact tests/Unit/IsolatedPostgresRestoreTest.php \
  --exclude-group=postgres-restore-integration
```

Il test d'integrazione reale è disattivato per impostazione predefinita. Solo su autorizzazione, con l'immagine già disponibile, eseguirlo così:

```bash
RUN_POSTGRES_RESTORE_INTEGRATION=1 php artisan test --compact \
  tests/Unit/IsolatedPostgresRestoreTest.php --group=postgres-restore-integration
```

Richiede Docker locale e usa esclusivamente i dati sintetici appena descritti. Non avvia Laravel, non legge configurazioni di produzione e non effettua deployment.
