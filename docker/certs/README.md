# Local Certificates Only

Questa cartella e' riservata a certificati locali di sviluppo.

## Regole

- I file reali `.crt`, `.key`, `.pem` non devono essere pushati.
- Il repository traccia solo questo `README.md` e `.gitkeep`.
- I certificati veri di produzione non vanno messi qui.

## Stato Del Setup Committato

Il setup Docker committato non richiede certificati in questa cartella.

- In locale il proxy Caddy usa `tls internal` come fallback sicuro.
- Se sul tuo computer vuoi usare certificati custom locali, puoi copiarli qui.
- Questi file restano solo sul tuo filesystem locale e sono ignorati da git.

## Esempi Di File Locali Ammessi Solo In Sviluppo

- `docker/certs/soamco.lo.pem`
- `docker/certs/soamco.lo-key.pem`
- `docker/certs/dev.crt`
- `docker/certs/dev.key`

## Produzione

In produzione questi file non devono essere usati.

- Il certificato verra' fornito dal reverse proxy, dal server o dal provider del deploy.
- Nessun path locale dentro `docker/certs` deve essere considerato parte del deploy.

## Se Ti Servono Certificati Custom Locali

1. Generali o copiali in questa cartella solo sul tuo ambiente locale.
2. Non fare `git add` dei file reali: `.gitignore` li blocca.
3. Se vuoi usarli davvero nel proxy locale, fallo con una configurazione locale non committata o con un override esplicito del tuo ambiente.

Se la cartella e' vuota, il bootstrap del progetto non si rompe: il fallback documentato resta `tls internal`.
