FROM app_assets AS assets
FROM nginx:1.27-alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=assets /var/www/html/public /var/www/html/public
RUN test -f /var/www/html/public/index.php && test -s /var/www/html/public/build/manifest.json
HEALTHCHECK --interval=10s --timeout=5s --start-period=30s --retries=12 CMD wget -q -O /dev/null http://127.0.0.1/up || exit 1
