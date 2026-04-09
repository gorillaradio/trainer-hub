# Test da mobile su rete locale

Per visualizzare il sito dal telefono durante lo sviluppo.

## Requisiti

- Telefono e computer sulla stessa rete Wi-Fi

## Procedura

1. Trova l'IP del Mac:

```bash
ipconfig getifaddr en0
```

2. Builda gli asset:

```bash
npm run build
```

3. Avvia il server Laravel esposto sulla rete:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

4. Dal telefono apri `http://<IP-DEL-MAC>:8000`

## Note

- Dopo ogni modifica al frontend, rilancia `npm run build` (~6 secondi) e ricarica la pagina sul telefono.
- Non c'è hot reload — è una build di produzione servita localmente.
- Per tornare a sviluppare su desktop con hot reload, rilancia `npm run dev`.
- Il dev server Vite (`npm run dev`) non funziona da mobile perché il laravel-vite-plugin scrive un indirizzo non raggiungibile nel file `public/hot`.
