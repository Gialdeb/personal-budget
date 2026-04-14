# SOAMCO Budget

Applicazione web per la gestione finanziaria personale e condivisa, costruita con Laravel, Inertia.js e Vue.

## Panoramica

SOAMCO Budget centralizza la gestione di conti, transazioni, budget e pianificazioni ricorrenti in un'interfaccia operativa unica. L'applicazione e progettata per dare una vista mensile chiara dei movimenti, consentire interventi rapidi sul registro e mantenere coerenza tra dati manuali, movimenti generati e flussi condivisi.

L'obiettivo del progetto e offrire un ambiente affidabile per:

- registrare e modificare movimenti finanziari
- monitorare entrate, uscite, bollette e risparmi
- confrontare spese reali e budget
- gestire transazioni ricorrenti e relative occorrenze
- supportare conti condivisi con permessi differenziati
- mantenere tracciabilita operativa e storico delle azioni

## Ambiti Coperti Dall'Applicazione

### 1. Registro Transazioni

Il modulo transazioni e il cuore dell'applicazione. Permette di:

- visualizzare il registro mensile con filtri e riepiloghi
- creare, modificare, eliminare, ripristinare e forzare eliminazioni quando consentito
- registrare rimborsi e annullare rimborsi esistenti
- gestire trasferimenti tra conti
- lavorare con rettifiche di saldo e saldi iniziali
- evidenziare movimenti ricorrenti, pianificati, eliminati o rimborsati

### 2. Budget Planning

L'applicazione integra una pianificazione budget orientata al confronto tra previsione e consuntivo:

- definizione del budget per categorie e periodi
- confronto tra valori effettivi e valori pianificati
- evidenza di scostamenti e saldo netto
- supporto alla copia del budget dall'anno precedente

### 3. Movimenti Ricorrenti

SOAMCO Budget include un dominio dedicato alle ricorrenze:

- creazione e modifica di piani ricorrenti
- generazione e conversione delle occorrenze
- gestione di skip, pause, riprese e annullamenti
- rimborso di movimenti provenienti da ricorrenze quando previsto

### 4. Conti e Risorse Finanziarie

L'applicazione gestisce diversi tipi di conto e contesti operativi:

- conti manuali
- conti di pagamento
- carte di credito
- saldo iniziale e saldo corrente
- supporto multi-account e contesti condivisi

### 5. Conti Condivisi e Collaborazione

Una parte importante del progetto riguarda la collaborazione tra utenti:

- condivisione dei conti
- ruoli e permessi sui membri
- inviti, revoche e ripristini
- visibilita coerente delle operazioni in ambienti condivisi

### 6. Importazioni

Il sistema include un modulo import strutturato per portare dati finanziari dall'esterno:

- caricamento file di import
- validazione e revisione righe
- gestione duplicati e righe da ignorare
- rollback e finalizzazione del processo

### 7. Notifiche e Inbox

L'applicazione dispone di una inbox interna per la consultazione di notifiche operative:

- elenco notifiche
- stato letto/non letto
- azioni massive di lettura
- anteprima contenuti

### 8. Supporto e Area Operativa Estesa

Il progetto include anche moduli accessori orientati alla gestione applicativa:

- support requests
- changelog e contenuti editoriali
- knowledge/support content
- session monitoring e warning di sessione

## Caratteristiche Tecniche Principali

- Backend Laravel 13
- PHP 8.5
- Frontend Inertia.js v2 + Vue 3
- Tailwind CSS v4
- Wayfinder per route typing lato frontend
- Fortify per autenticazione
- Horizon per code e monitoraggio job
- Reverb + Echo per realtime/broadcasting
- Pest per test automatici
- Pint, ESLint e Prettier per la qualita del codice

## Architettura

Il progetto segue una struttura Laravel moderna con frontend SPA server-driven:

- `app/` contiene controller, servizi, request, modelli e logica di dominio
- `resources/js/` contiene pagine Inertia, componenti Vue, i18n e librerie client
- `routes/` definisce i flussi HTTP dell'applicazione
- `tests/` copre feature backend e verifiche frontend mirate

L'interfaccia non e una SPA separata: le pagine vengono renderizzate tramite Inertia, mantenendo routing e validazione lato Laravel e un'esperienza utente reattiva lato Vue.

## Requisiti

- PHP 8.5+
- Composer
- Node.js
- npm
- database PostgreSQL configurato
- Redis consigliato per queue, Horizon e componenti realtime

## Installazione

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

In alternativa e disponibile anche lo script Composer di bootstrap:

```bash
composer run setup
```

## Avvio in Sviluppo

Per l'ambiente locale standard:

```bash
composer run dev
```

Per un flusso con SSR e processi dedicati:

```bash
composer run dev:ssr
```

## Qualita e Test

Formattazione PHP:

```bash
vendor/bin/pint --dirty --format agent
```

Test applicativi:

```bash
php artisan test
```

Controlli frontend e tipizzazione:

```bash
npm run format:check
npm run lint:check
npm run types:check
```

Pipeline completa locale:

```bash
composer run ci:check
```

## Casi d'Uso Tipici

- gestione del registro mensile delle spese personali
- riconciliazione movimenti e saldi tra conti diversi
- controllo di budget per categoria
- pianificazione di spese o entrate ricorrenti
- gestione collaborativa di conti condivisi
- import massivo di movimenti da fonti esterne

## Deployment

Per ambienti di produzione l'applicazione richiede attenzione a:

- configurazione corretta di database, cache e queue
- riavvio worker queue in fase di deploy
- build asset frontend
- eventuale gestione del processo SSR, se abilitato
- servizi Redis/Reverb/Horizon ove previsti dall'ambiente

## Note

Questo repository rappresenta una piattaforma gestionale finanziaria completa, con focus su operativita quotidiana, affidabilita dei dati, controllo del budget e gestione collaborativa dei conti.
