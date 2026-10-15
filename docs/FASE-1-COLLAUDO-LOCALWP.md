# Collaudo Fase 1 in LocalWP (Windows)

Procedura per verificare personalmente il plugin **senza toccare il sito pubblico**. Tempo stimato: 20–30 minuti. Usa un sito LocalWP nuovo e vuoto: nessun dato reale.

## 1. Preparazione

1. In LocalWP: **+ Create a new site** → nome `bracelli-dev` → *Preferred* (oppure *Custom* con PHP 8.2 o 8.3) → utente amministratore a scelta.
2. **Site shell** (clic destro sul sito → *Open site shell*) e annota le versioni:
   ```bash
   wp core version
   wp --info
   ```
3. Facoltativo, consigliato: in `app/public/wp-config.php` aggiungi prima di `/* That's all */`:
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   define( 'WP_DEBUG_DISPLAY', false );
   define( 'WP_ENVIRONMENT_TYPE', 'local' );
   ```

## 2. Installazione dallo ZIP

1. Bacheca → **Plugin → Aggiungi nuovo → Carica plugin** → `bracelli-club-0.1.0.zip` → **Installa ora** → **Attiva**.
2. Atteso: nessun errore; compare il menu **Bracelli Club**.
3. Nella shell: `wp bracelli status` → 6 moduli `active`, 0 migrazioni in sospeso.
4. Facoltativo: `wp bracelli dev seed-users` crea un utente di prova per ogni ruolo Bracelli e mostra le password **una sola volta**.

## 3. Checklist

| # | Azione | Risultato atteso | OK |
| --- | --- | --- | --- |
| 1 | Esci e rientra come amministratore | Si apre la dashboard Bracelli | ☐ |
| 2 | Guarda i widget | Benvenuto, Preferiti, Aree gestionali ("Non ancora disponibile"), Stato moduli, Salute del sistema, Attività recenti; nessun numero inventato | ☐ |
| 3 | Tema: Scuro, poi tavolozza "Aurora"; ricarica la pagina | Le scelte restano; anche Moduli e Impostazioni sono scuri | ☐ |
| 4 | **Personalizza** → aggiungi "Il mio profilo" ai preferiti, nascondi un widget, sposta un widget | Dopo il ricaricamento le scelte restano | ☐ |
| 5 | Menu laterale → **Nascondi menu WordPress**, poi apri Articoli | Menu WordPress nascosto, pulsante "Mostra menu WordPress" in basso a sinistra; un clic lo riapre | ☐ |
| 6 | **Moduli** → Disattiva "Diagnostica" | Messaggio di conferma; il widget "Salute del sistema" sparisce; i dati restano | ☐ |
| 7 | Riattiva "Diagnostica" | Il widget ritorna | ☐ |
| 8 | **Registro attività** → filtra per `module.deactivated` → Dettagli | Evento visibile con autore e data; nessuna email in chiaro | ☐ |
| 9 | **Impostazioni** → Scarica esportazione; modifica il file (`"ui.default_theme": "dark"`); Importa → Analizza | Anteprima con una sola differenza; nulla cambia finché non premi "Applica" | ☐ |
| 10 | **Permessi** → spunta "Registro attività" per la Segreteria → Salva; entra come utente Segreteria | La segreteria vede la dashboard in contesto dipendente e il Registro; Moduli/Impostazioni/Permessi danno "accesso negato" | ☐ |
| 11 | **Ripristina predefiniti** per la Segreteria | Il Registro torna inaccessibile alla segreteria | ☐ |
| 12 | Disattiva JavaScript nel browser e apri Bracelli Club → Ripristino | La pagina funziona; i pulsanti eseguono le azioni | ☐ |
| 13 | Aggiungi `define( 'BRACELLI_CLUB_SAFE_MODE', true );` in wp-config.php | Resta solo "Ripristino Bracelli"; avviso di modalità sicura. Togli la riga per tornare alla normalità | ☐ |
| 14 | Riduci la finestra (o modalità smartphone del browser) | Layout leggibile, nessuno scorrimento orizzontale | ☐ |
| 15 | Plugin → Disattiva, poi Attiva | Preferenze, registro e permessi ancora presenti | ☐ |
| 16 | Plugin → Disattiva → **Elimina**, poi reinstalla lo stesso ZIP | I dati ci sono ancora (opzione "Elimina tutti i dati" non attivata) | ☐ |
| 17 | `wp-content/debug.log` | Nessun messaggio proveniente da `bracelli-club` | ☐ |

## 4. Prova facoltativa con i plugin del sito

Solo se hai preparato una **copia** del sito in LocalWP come da `docs/FASE-0-INVENTARIO.md` §3 (dati reali: non condividerli e non caricarli su GitHub): installa lo ZIP, ripeti i punti 1, 2, 5 e 17 e annota eventuali conflitti con Elementor, il tema o altri plugin.

## 5. Cosa riportare

Versioni rilevate al punto 1.2, esito della checklist (numeri non superati e cosa vedi) ed eventuali righe di `debug.log` (senza percorsi personali o dati). Niente screenshot che mostrino dati reali.
