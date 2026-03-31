# Reverb Foundation

Questa base realtime resta volutamente limitata.

- Broadcaster: `reverb`
- Primo caso d'uso attivo: aggiornamenti realtime dello stato automazioni admin
- Scope escluso: budget live, transazioni live, sync aggressiva dell'app
- Setup principale: Reverb dentro Docker, non piu' come processo host manuale

## Installazione applicata al progetto

1. Broadcasting foundation Laravel abilitata con `php artisan install:broadcasting --reverb --no-interaction`.
2. Pacchetto server installato con Composer: `laravel/reverb`.
3. Client frontend installato con npm: `laravel-echo` e `pusher-js`.
4. Dockerizzazione operativa aggiunta con:
    - servizio `reverb` in `docker-compose.yml`
    - reverse proxy websocket in `docker/caddy/Caddyfile`
    - configurazione distinta tra endpoint Docker interni e endpoint browser pubblici

## Variabili ambiente

Variabili richieste:

- `REVERB_APP_ID`
- `REVERB_APP_KEY`
- `REVERB_APP_SECRET`
- `REVERB_SERVER_HOST`
- `REVERB_SERVER_PORT`
- `REVERB_HOST`
- `REVERB_PORT`
- `REVERB_SCHEME`
- `REVERB_ALLOWED_ORIGINS`
- `VITE_REVERB_APP_KEY`
- `VITE_REVERB_HOST`
- `VITE_REVERB_PORT`
- `VITE_REVERB_SCHEME`

Configurazione Docker locale prevista in questo progetto:

- app pubblica su `https://soamco.lo`
- container Reverb in ascolto su `0.0.0.0:8080`
- Laravel broadcasta verso `http://reverb:8080`
- il browser si connette a `wss://soamco.lo` tramite Caddy su `443`
- origin consentiti: `soamco.lo`, `localhost`, `127.0.0.1`

## Distinzione Host E Port

Questa distinzione e' intenzionale e va mantenuta:

- `REVERB_SERVER_HOST` e `REVERB_SERVER_PORT`
  definiscono dove il processo Reverb ascolta dentro il container
- `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`
  definiscono come Laravel raggiunge Reverb dal network Docker
- `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`
  definiscono come il browser raggiunge Reverb dal lato pubblico

Nel setup locale corrente:

- `REVERB_SERVER_HOST=0.0.0.0`
- `REVERB_SERVER_PORT=8080`
- `REVERB_HOST=reverb`
- `REVERB_PORT=8080`
- `REVERB_SCHEME=http`
- `VITE_REVERB_HOST=soamco.lo`
- `VITE_REVERB_PORT=443`
- `VITE_REVERB_SCHEME=https`

Questo evita un errore comune: usare lo stesso host per container interni e browser esterno.

## Avvio E Riavvio

Avvio del solo servizio Reverb:

```bash
docker compose up -d reverb
```

Avvio del set minimo coerente:

```bash
docker compose up -d app web proxy db redis reverb
```

Riavvio del solo Reverb:

```bash
docker compose restart reverb
```

Riavvio logico del server via Artisan nel container:

```bash
docker compose exec reverb php artisan reverb:restart
```

## Logs E Debug

Seguire i log del container:

```bash
docker compose logs -f reverb
```

Verificare che il servizio esista e sia in esecuzione:

```bash
docker compose ps reverb
```

Verificare la configurazione Compose risolta:

```bash
docker compose config --services
docker compose config reverb
```

## Networking Locale

- Il browser non deve raggiungere `reverb:8080`: quel nome host esiste solo nel network Docker.
- Il browser usa `https://soamco.lo` e Caddy inoltra i path websocket `/app*` e `/apps*` al servizio `reverb`.
- Laravel non deve usare `soamco.lo` per pubblicare eventi verso Reverb nel network Docker: usa `reverb:8080`.
- `allowed_origins` resta chiuso e non usa `*`.

## HTTPS E Mixed Content

- Con `APP_URL=https://soamco.lo`, il browser deve usare `VITE_REVERB_SCHEME=https`.
- Il traffico browser arriva come `wss` verso Caddy su `443`.
- Dietro Caddy, il traffico verso il container Reverb puo' restare `http` su `8080` senza mixed content, perche' il downgrade avviene solo all'interno del network Docker.
- Se il browser prova a connettersi a `ws://` mentre la pagina e' `https://`, il problema e' quasi sempre una variabile `VITE_REVERB_*` incoerente.
- Eventuali file in `docker/certs` sono solo materiale locale di sviluppo: non fanno parte del deploy, non vanno pushati e la configurazione committata non dipende dalla loro presenza.

## Verifica Locale HTTPS E PWA

Due controlli locali sono bloccanti prima di validare PWA o Reverb:

- `https://soamco.lo/service-worker.js` deve rispondere `200`, non `404`
- `https://soamco.lo` deve risultare trusted nel browser, altrimenti PWA e `wss` non sono verificabili davvero

Cause corrette in questo setup:

- il `404` del service worker era causato da Nginx: il matcher statico `location ~* \.(css|js|...)$` intercettava `/service-worker.js` e lo trattava come asset statico invece di inoltrarlo a Laravel
- il certificato non trusted era causato da Caddy: stava servendo il certificato interno di Caddy invece del leaf mkcert per `soamco.lo`

Verifiche pratiche:

```bash
curl -I https://soamco.lo/service-worker.js
openssl s_client -connect soamco.lo:443 -servername soamco.lo -showcerts </dev/null
curl -i -N --http1.1 \
  -H 'Origin: https://soamco.lo' \
  -H 'Connection: Upgrade' \
  -H 'Upgrade: websocket' \
  -H 'Sec-WebSocket-Version: 13' \
  -H 'Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==' \
  "https://soamco.lo/app/${REVERB_APP_KEY}?protocol=7&client=js&version=8.4.0&flash=false"
```

Esito atteso:

- `service-worker.js` con `HTTP/2 200` e `service-worker-allowed: /`
- certificato con `Verify return code: 0 (ok)` e SAN che include `soamco.lo`
- websocket con `101 Switching Protocols` e `X-Powered-By: Laravel Reverb`

Per una verifica browser reale:

- apri `https://soamco.lo` in Chrome
- controlla in DevTools > Security che il certificato sia valido per `soamco.lo`
- in Application > Service Workers verifica che il worker registrato punti a `https://soamco.lo/service-worker.js`
- se Chrome continua a segnare il sito come non trusted, il problema non e' Reverb: va controllata l'installazione della root mkcert nel sistema/browser locale

## Problemi Noti E Diagnosi

Se la connessione websocket fallisce:

- controlla `docker compose logs -f reverb`
- controlla `docker compose logs -f proxy`
- controlla la console browser
- controlla `POST /broadcasting/auth`
- controlla che `REVERB_ALLOWED_ORIGINS` includa l'host reale della pagina

Se Reverb non parte:

- verifica che `db` e `redis` siano attivi
- questo setup usa `CACHE_STORE=database`, quindi il container Reverb deve stare nel network Docker con accesso a `db`
- il workaround `CACHE_STORE=file` era solo per l'avvio fuori Docker; non e' il setup operativo principale e non serve nel flusso Docker corretto

## Test Locale Rapido

1. Avvia `docker compose up -d app web proxy db redis reverb`.
2. Apri `/admin/automation` come admin in due tab browser.
3. Esegui un `Run now` o un `Retry`.
4. Verifica che l'altra tab aggiorni `runs` e `statuses` senza refresh completo.
5. Se non aggiorna:
    - controlla i log `reverb`
    - controlla i log `proxy`
    - controlla la console browser per errori di auth o origin

## Integrazione Nel Progetto

- Backend: `App\Events\Admin\AutomationRunUpdated` viene broadcastato quando `AutomationRunRecorder` cambia stato a una run.
- Channel: private channel `admin.automation.runs`, autorizzato solo agli admin.
- Frontend: client Echo centralizzato in `resources/js/lib/realtime/echo.ts`.
- Listener isolato: `resources/js/composables/useAdminAutomationRealtime.ts`, usato solo in `resources/js/pages/admin/Automation/Index.vue`.
- Il caso d'uso admin automation realtime resta invariato: questa modifica riguarda solo il runtime Docker di Reverb.
