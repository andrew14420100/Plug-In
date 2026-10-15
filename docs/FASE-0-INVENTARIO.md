# Fase 0 — Inventario sito e ricognizione Club Manager

**Stato:** procedure e moduli di raccolta **da compilare**. Nessun dato del sito o di Club Manager è stato rilevato in questa sessione: il sito di produzione non è stato contattato e Club Manager non è accessibile da qui.

> I risultati compilati possono contenere informazioni tecniche sensibili (percorsi server, versioni vulnerabili, nomi utente). Prima di inserirli nel repository pubblico rimuovere: percorsi assoluti, nomi utente, email, chiavi, URL di backup, qualsiasi dato di soci. In caso di dubbio conservarli **fuori** dal repository e riportare qui solo il riepilogo.

---

## 1. Rilievo versioni WordPress/PHP/hosting (sola lettura)

**Metodo consigliato (nessuna modifica al sito):** l'amministratore apre in produzione *Strumenti → Salute del sito → Informazioni* e usa "Copia negli appunti". È una lettura che non cambia configurazione. In alternativa, lo stesso rilievo su una copia LocalWP (§3).

| Voce | Valore rilevato | Fonte/data |
| --- | --- | --- |
| Versione WordPress | ⏳ | |
| Lingua / fuso orario | ⏳ | |
| Multisite (sì/no) | ⏳ | |
| Tema attivo + versione (+ tema genitore) | ⏳ | |
| Versione PHP | ⏳ | |
| Estensioni PHP: `sodium`, `intl`, `mbstring`, `zip`, `curl`, `openssl`, `gd`/`imagick` | ⏳ | |
| `memory_limit` / `WP_MEMORY_LIMIT` / `WP_MAX_MEMORY_LIMIT` | ⏳ | |
| `max_execution_time` / `upload_max_filesize` / `post_max_size` | ⏳ | |
| Server web (Apache/LiteSpeed/nginx) | ⏳ | determina `.htaccess` per documenti privati |
| DB: MySQL o MariaDB + versione | ⏳ | |
| Charset/collation DB | ⏳ | |
| `DISABLE_WP_CRON` / cron di sistema disponibile nel pannello TopHost | ⏳ | Q307 |
| Invio email (PHP mail / SMTP hosting) | ⏳ | Q304 |
| Spazio disco disponibile/occupato | ⏳ | Q308 |
| Directory fuori webroot scrivibile da PHP | ⏳ | ADR §8.3 |
| HTTPS e certificato | ⏳ | |
| Plugin di cache/CDN lato hosting | ⏳ | |

**Uso dei risultati:** confermare o rivedere i minimi proposti (ADR D2: PHP 8.2, WP 6.9) e la matrice CI (ADR §11).

---

## 2. Inventario plugin

### 2.1 Elenco da verificare

Plugin citati in un inventario precedente **non verificato** (SPECIFICA §6). Vanno confermati e completati con quelli effettivamente installati. **Nessun plugin va disattivato o rimosso** (CLAUDE.md §8).

| Plugin | Funzione presunta | Possibile modulo Bracelli | Fase | Rischio sostituzione | Note iniziali |
| --- | --- | --- | --- | --- | --- |
| Elementor | Page builder | `elementor` (integrazione, non sostituzione) | — | Alto | Mantenere (Q168, Q225) |
| Elementor Pro | Theme builder, form, popup | parziale `forms` | 6+ | Alto | Decisione rinviata a inventario (Q168): verificare template header/footer, form Pro, popup |
| Amelia | Prenotazioni/appuntamenti | `bookings` | 4 | Alto | Contiene dati clienti/prenotazioni: inventariare tabelle, servizi, pagamenti, notifiche |
| Contact Form 7 | Form contatti | `forms` | 6 | Medio | Contare form e shortcode `[contact-form-7]`; plugin di archiviazione messaggi (es. Flamingo)? |
| Revolution Slider | Slider | `sliders` | 6 | Medio | Slider usati via shortcode/widget Elementor; spesso incluso dal tema |
| Yoast SEO | SEO, sitemap, redirect (Premium?) | `seo`, `redirects` | 6 | Alto | Migrazione con diff (Q227); free o Premium? redirect esistenti |
| TRX Addons | Componenti tema ThemeREX | — | — | Alto | Probabile dipendenza del tema: non sostituibile senza cambiare tema |
| TRX Updater | Aggiornamenti tema/plugin ThemeREX | `updater` (solo per il nostro plugin) | — | Medio | Verificare cosa aggiorna |
| WPCode | Snippet PHP/JS/CSS | — (Q224: nessun editor snippet) | 6 | Alto | Inventariare **ogni snippet attivo** e la sua funzione; alcuni potrebbero diventare codice del plugin |
| Vibe AI | Funzioni AI | — (AI rinviata) | — | Da valutare | Verificare dati inviati a servizi esterni |
| Download Plugins From Dashboard Pro | Download ZIP plugin | — | — | Basso | Strumento di servizio |

### 2.2 Scheda per ogni plugin (da compilare)

```text
Plugin / slug / versione / attivo:
Fornitore e licenza (free/pro, scadenza):
Funzioni realmente usate:
Dove sono usate (pagine, template Elementor, shortcode, widget, cron):
Dati salvati (tabelle proprie, options, postmeta, file in uploads):
Dati personali trattati (sì/no, quali categorie):
Dipendenze (tema, altri plugin):
Sostituibile da modulo Bracelli? (sì/no/parziale) + fase:
Piano migrazione (import, diff, test, rollback):
Decisione utente:
```

### 2.3 Comandi in sola lettura (su copia LocalWP, mai in produzione)

Da eseguire nella shell del sito LocalWP ("Open site shell"); producono solo output a schermo.

```bash
wp core version
wp --info
wp plugin list --fields=name,status,version,update --format=table
wp theme list --fields=name,status,version,parent --format=table
wp option get template && wp option get stylesheet
wp cron event list --fields=hook,next_run_relative,recurrence --format=table
wp db tables --all-tables-with-prefix
wp db size --tables --human-readable

# Contenuti con shortcode noti (conteggio)
wp post list --post_type=page,post --s='[contact-form-7' --format=count
wp post list --post_type=page,post --s='[rev_slider' --format=count

# Widget Elementor usati (conta pagine per tipo di widget)
wp db query "SELECT COUNT(DISTINCT post_id) FROM $(wp db prefix)postmeta WHERE meta_key='_elementor_data' AND meta_value LIKE '%\"widgetType\":\"form\"%'"
wp db query "SELECT COUNT(DISTINCT post_id) FROM $(wp db prefix)postmeta WHERE meta_key='_elementor_data' AND meta_value LIKE '%slider_revolution%'"

# Template Elementor Pro (header/footer/popup)
wp post list --post_type=elementor_library --fields=ID,post_title,post_status --format=table
```

I conteggi non contengono dati personali; gli elenchi di tabelle e post possono essere riportati nel repository dopo revisione.

---

## 3. Come ottenere una copia del sito senza modificarlo

| Opzione | Impatto sulla produzione | Note |
| --- | --- | --- |
| A. Backup già esistente scaricato dal pannello TopHost | Nessuna modifica; solo download | Preferita se disponibile |
| B. Export da plugin di backup già installato | Crea un file di backup sul server | Richiede approvazione esplicita dell'utente (azione sul sito) |
| C. Solo rilievo Site Health + elenco plugin dalla schermata Plugin | Nessuna | Sufficiente per versioni e inventario superficiale, non per analisi shortcode/tabelle |

La copia contiene **dati reali** (utenti, form, prenotazioni Amelia): resta solo sul PC dell'utente, in LocalWP isolato (vedi `FASE-0-LOCALWP.md` §2), mai nel repository, con invio email disattivato. Si cancella al termine dell'inventario.

---

## 4. Ricognizione Club Manager (da eseguire sul PC, solo lettura)

Riferimento: `docs/CLUB_MANAGER.md` "Verifiche P0". Nessuno di questi passi è stato eseguito. Ogni passo richiede **autorizzazione** e, per i passi 4.3–4.4, un **backup** preventivo dell'archivio fatto con lo strumento ufficiale del programma.

### 4.1 Identificazione (senza aprire file di dati)

| Domanda | Risposta | Come trovarla |
| --- | --- | --- |
| Nome esatto del prodotto e produttore | ⏳ | Menu "Informazioni su" / "?" del programma |
| Versione e build | ⏳ (Q291) | Idem |
| Tipo di licenza e numero postazioni | ⏳ | Contratto/attivazione |
| Su quali PC è installato (quanti, Windows versione) | ⏳ (Q311) | |
| Dove risiede l'archivio condiviso (PC server, NAS, cartella condivisa) | ⏳ (Q292) | Impostazioni del programma o documentazione |
| Il produttore offre export, API, ODBC, report schedulabili? | ⏳ (Q252) | Manuale, sito del produttore, assistenza |
| Esistono funzioni di backup ufficiali e dove salvano | ⏳ | |
| Termini di licenza su accesso ai dati da parte di terzi | ⏳ | Contratto/EULA |

### 4.2 Dati necessari al progetto (mappa da compilare, nessun valore reale)

| Entità | Campi da cercare | Note |
| --- | --- | --- |
| Socio/cliente | ID interno, nome, cognome, data nascita, CF, contatti, indirizzo, tipo, stato, data creazione/modifica | Q319: chiave stabile? |
| Tessera | codice, formato/lunghezza, zeri iniziali, data emissione, sostituzioni | Q265 simbologia |
| Iscrizione/tesseramento | stagione, tipo, validità, stato | |
| Corso/iscrizione corso | corso, periodo, stato | |
| Famiglia | relazione tutore/minore | Q329 |
| Documenti/certificati | tipo, scadenza, dove sono salvati | dati particolari: minimizzare |
| Pagamenti | importi, date, metodi | Q328: solo mappare, nessuna decisione |
| Timestamp di modifica | esistono per record? | determina sync incrementale |
| Cancellazioni/archiviazioni | come sono rappresentate | Q321 |

### 4.3 Canale di lettura (in ordine di preferenza)

1. API o export ufficiale (CSV/XML/Excel) — anche manuale, per valutare il formato su un export di prova con **dati fittizi o anonimizzati** se il programma lo consente.
2. Export/backup schedulabile dal programma.
3. Lettura di una **copia** del database/backup su un PC isolato.
4. Lettura diretta in sola lettura del database vivo — solo se supportata dal produttore e dopo misura dell'impatto sugli altri PC.

Da verificare per il canale scelto: accessibilità con programma chiuso e PC acceso (Q337), lock sui file, impatto sulle prestazioni, presenza di identificatori stabili.

### 4.4 Divieti

Nessuna installazione di driver/servizi/connettori sul PC senza permesso; nessuna scrittura sui file dell'archivio; nessuna decompilazione o elusione di protezioni; nessun file del gestionale o export reale nel repository o nei log.

### 4.5 Esito atteso della ricognizione

Un breve rapporto (anonimo, senza dati reali) con: prodotto/versione, canale di lettura possibile, mappa campi, chiave di correlazione candidata (Q319), formato barcode (Q265), vincoli di licenza. Su questa base si scrive un ADR dedicato al connettore e si decide se la Fase 3 è avviabile o se si ripiega su import periodico.
