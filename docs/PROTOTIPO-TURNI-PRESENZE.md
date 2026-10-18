# Prototipo "Turni e presenze" — plugin 0.2.0 (solo LocalWP)

Branch: `prototype/presenze-locale-v0.1.0` · Modulo `turni` versione 0.1.0 · Plugin 0.2.0 · **Non è una release** e non va installato sul sito pubblico.

## 1. Perché esiste e cosa è autorizzato

Su richiesta dell'utente, l'intervista dei requisiti (gruppi A–F completati, G in corso) è sospesa per provare subito **una piccola funzione reale** in LocalWP su Windows e correggerla in base a ciò che si vede. L'autorizzazione riguarda **solo** questo prototipo:

- la segreteria gestisce gli orari (turni) dei dipendenti;
- il dipendente vede solo i propri turni;
- il dipendente registra **Presente** oppure **Assente** per il turno previsto.

Restano fuori: timbrature di entrata e uscita, ritardi, giustificazioni, certificati, pagamenti, creazione degli account dal portale, tutte le altre fasi.

## 2. Decisioni prese per il prototipo

| # | Decisione | Motivo |
| --- | --- | --- |
| P1 | Il prototipo è un **modulo** (`turni`) del plugin esistente, costruito sopra il nucleo della Fase 1 (PR #2, non ancora unita) | Riusa migrazioni, registro attività, permessi, modalità sicura e moduli disattivabili invece di duplicarli |
| P2 | Area riservata **frontend** servita dal plugin all'indirizzo `/area-bracelli/` (con permalink semplici: `/?bracelli_portal=1`) | I dipendenti non usano la bacheca WordPress; la pagina non dipende dal tema né da Elementor |
| P3 | Nuovo ruolo **"Dipendente Bracelli"** creato dal plugin; la Segreteria riceve la capability "Gestione turni" | Serve per provare i due ruoli. È un'**eccezione dichiarata** alla regola F2 (nessun ruolo creato automaticamente): da rivedere nella fase dedicata ai permessi. Nessun ruolo viene assegnato ad account esistenti |
| P4 | I turni **non si eliminano**: si annullano e restano nello storico | Requisito "annullare conservando lo storico"; nessuna cancellazione definitiva per la segreteria |
| P5 | Un turno con presenza già registrata, o annullato, non si modifica più | Evita che la registrazione del dipendente si riferisca a un orario diverso da quello mostrato |
| P6 | Il dipendente registra Presente/Assente **dal giorno del turno in poi** e può cambiare scelta **solo nel giorno del turno** | Regola semplice da verificare in prova; può essere cambiata dopo il feedback |
| P7 | Turni a cavallo della mezzanotte non supportati; due turni dello stesso dipendente non possono sovrapporsi | Validazione minima dei dati |
| P8 | Lo storico usa il registro attività del nucleo (`bc_audit_events`), senza tabella aggiuntiva | Una sola fonte dello storico, già protetta dai permessi |
| P9 | Le presenze di questo prototipo sono **presenze del personale**, distinte dalle presenze degli iscritti ai corsi (Fase 4 della roadmap) | Richiesta F7: non confondere le due funzioni |

## 3. Dati e permessi

| Tabella | Contenuto |
| --- | --- |
| `{prefix}bc_turni_shifts` | dipendente (id utente), data, inizio, fine (ora locale del sito), stato `scheduled`/`cancelled`, chi e quando ha creato/modificato/annullato (UTC) |
| `{prefix}bc_turni_attendance` | una registrazione per turno: `present`/`absent`, data di registrazione e di modifica (UTC) |

| Capability | Chi la riceve | Cosa consente |
| --- | --- | --- |
| `bracelli_turni_manage` "Gestione turni" | Segreteria Bracelli, Amministratore | Vedere tutti i turni e le presenze, creare, modificare data e orario, annullare |
| `bracelli_turni_self` "Dipendente con turni" | Dipendente Bracelli (non l'amministratore) | Ricevere turni, vedere solo i propri, registrare la propria presenza o assenza |

Controlli sul server (non solo nell'interfaccia): ogni operazione passa da `ShiftService`, che verifica la capability e, per il dipendente, che il turno sia suo (un turno altrui risulta "non trovato"). Ogni modulo ha un nonce legato all'azione e al turno. Query con segnaposto, output con escape, intestazioni `noindex`, `no-cache` e anti-iframe. La Segreteria non riceve permessi su Moduli, Impostazioni, Permessi, Utenti o altri dati.

## 4. Installazione in LocalWP (Windows)

> **Attenzione:** usa un **sito LocalWP nuovo** con soli dati fittizi. Se carichi questo ZIP su un sito dove c'è la tua versione 0.1.5, la sostituisce: le modifiche grafiche della 0.1.5 non sono ancora nel repository.

1. LocalWP → **+ Create a new site** → nome `bracelli-turni` → *Preferred* → crea un amministratore.
2. Annota le versioni: clic destro sul sito → *Open site shell* → `wp core version` e `php -v`. Il plugin richiede (provvisoriamente) PHP 8.2 e WordPress 6.9: se sono inferiori, si rifiuta di attivarsi con un messaggio.
3. Bacheca → **Impostazioni → Generali → Fuso orario: Roma** (la data "di oggi" dei turni dipende da questa impostazione).
4. **Plugin → Aggiungi nuovo → Carica plugin** → `bracelli-club-0.2.0.zip` → Installa → Attiva.
5. **Bracelli Club → Turni e presenze**: la pagina mostra l'indirizzo dell'area turni (di solito `http://bracelli-turni.local/area-bracelli/`).

## 5. Account fittizi

Da **Utenti → Aggiungi nuovo utente** (email inventate, per esempio `@example.org`; non serve inviare l'email):

| Nome utente | Ruolo | Nome visualizzato |
| --- | --- | --- |
| `segreteria1` | Segreteria Bracelli | Segreteria Prova |
| `dipendente1` | Dipendente Bracelli | Dipendente Prova Uno |
| `dipendente2` | Dipendente Bracelli | Dipendente Prova Due |

In alternativa, dalla *site shell*: `wp bracelli dev seed-users` crea un utente fittizio per ogni ruolo Bracelli (compreso `demo_dipendente`) e mostra le password una sola volta; il secondo dipendente va creato a mano.

## 6. Prova dei due ruoli

Usa due browser diversi (o una finestra anonima) per avere segreteria e dipendente collegati insieme.

| # | Chi | Azione | Risultato atteso |
| --- | --- | --- | --- |
| 1 | Segreteria | Apri l'indirizzo dell'area turni e accedi | Pagina "Turni e presenze" con filtri e riepilogo |
| 2 | Segreteria | **Nuovo turno**: Dipendente Prova Uno, oggi, 18:00–09:00 | Errore sul campo "Fine"; nulla viene salvato |
| 3 | Segreteria | Correggi in 08:00–12:00 → Crea turno | "Turno creato"; il turno è in elenco |
| 4 | Segreteria | Crea un turno di oggi per Dipendente Prova Due e uno di domani per Dipendente Prova Uno | Tre turni in elenco |
| 5 | Segreteria | Su un turno: **Modifica** l'orario, poi **Annulla il turno** → Conferma | Storico con 3 eventi; turno "Annullato", ancora visibile, non più modificabile |
| 6 | Dipendente Uno | Accedi all'area turni | Vede solo i propri turni; nessun turno di Dipendente Due |
| 7 | Dipendente Uno | Sul turno di oggi premi **Presente** | "Registrazione salvata", stato "Presente" con data e ora; può cambiare in "Assente" solo oggi |
| 8 | Dipendente Uno | Sul turno di domani | Nessun pulsante: "Potrai registrare presenza o assenza dal giorno del turno" |
| 9 | Dipendente Uno | Prova ad aprire `/wp-admin/` | Torna all'area turni; nessuna barra di amministrazione sul sito |
| 10 | Segreteria | Ricarica l'elenco; filtro Presenza = Presente | Il turno risulta "Presente"; non è più modificabile né annullabile |
| 11 | Segreteria | Apri Bacheca → Bracelli Club → Moduli / Impostazioni / Permessi, oppure Utenti | Accesso negato |
| 12 | Amministratore | Bracelli Club → **Registro attività**, cerca `turni` | Eventi di creazione, modifica, annullamento e presenza, con autore e data |
| 13 | Tutti | Riduci la finestra o usa il telefono sulla stessa rete | Pagine leggibili, senza scorrimento orizzontale |
| 14 | Amministratore | Plugin → Disattiva, poi Attiva | Turni e presenze ancora presenti |

Da riportare: versioni del punto 4.2, numeri della tabella non superati e cosa vedi, eventuali righe di `wp-content/debug.log`. Niente screenshot con dati reali.

## 7. Verifiche eseguite (container Linux, non sul PC dell'utente)

Ambiente: PHP 8.3.6, MariaDB 10.11.14, Apache 2.4.58 + mod_php, WordPress 7.1.3 e 6.9.10, Chromium (Playwright 1.56.1).

| Verifica | Esito |
| --- | --- |
| Sintassi PHP (`php -l`) dei file nuovi e modificati | OK |
| PHPCS (WordPress-Extra + PHPCompatibilityWP 8.1+) | 0 errori, 0 avvisi |
| PHPStan livello 6 + estensione WordPress | Nessun errore |
| Test unitari PHP | 38 test OK |
| Test di integrazione su WP 7.1.3 (19 nuovi per i turni) | 64 test OK, anche in ordine casuale con 5 semi diversi |
| Test di integrazione su WP 6.9.10 | 64 test OK |
| ESLint, Stylelint, TypeScript, Jest | 0 problemi; 14 test Jest OK |
| End-to-end in Chromium con plugin installato **dallo ZIP** su Apache (8 nuovi per i turni) | 21 test OK in 3 esecuzioni consecutive su siti ricreati da zero; `debug.log` vuoto |
| Ciclo di vita (`bin/lifecycle-check.sh`) | OK: disattivazione/riattivazione senza perdita di turni; disinstallazione senza flag conserva turni e ruoli; con flag elimina tabelle e ruolo Dipendente; **aggiornamento dalla 0.1.0** caricando lo ZIP 0.2.0: l'area turni avvisa "non ancora pronta" finché un amministratore non apre la bacheca, poi tabelle, ruolo e permessi sono creati senza perdere i dati esistenti |
| Plugin Check 2.1.0 | Solo le segnalazioni già note della Fase 1 (licenza non decisa, readme in italiano, `load_plugin_textdomain`) |
| Scansione dati personali/segreti (`bin/check-public-safety.sh`) | Nessun riscontro |

I test di sicurezza coprono: dipendente che prova a creare, modificare o annullare turni (rifiutato anche con nonce valido); dipendente che registra la presenza sul turno di un altro (rifiutato, nessun dato scritto); nonce falso o di un altro turno (rifiutato); visitatore e abbonato senza permessi; nome utente con codice HTML (mostrato come testo); segreteria senza accesso a Moduli, Impostazioni, Permessi e Utenti.

**Non eseguito:** installazione in LocalWP su Windows (non disponibile in questa sessione), prove con lettori di schermo reali, prove su TopHost.

## 8. Limiti e rischi noti

1. Nessuna protezione propria contro i tentativi ripetuti di accesso: il modulo di accesso usa il login di WordPress, quindi valgono le protezioni eventualmente già installate (richiesta F6, da realizzare in una fase successiva).
2. Il dipendente non ha una pagina per cambiare la password: usa "Password dimenticata?" (serve l'invio di email funzionante; in LocalWP le email si leggono nello strumento email di LocalWP, Mailpit o MailHog secondo la versione).
3. L'indirizzo `/area-bracelli/` potrebbe coincidere con una pagina esistente del sito reale: va verificato prima di qualsiasi uso fuori da LocalWP (è modificabile con il filtro `bracelli_club_portal_slug`).
4. Il logo è quello impostato nel sito (Aspetto → Personalizza → Logo); se assente compare la scritta "BRACELLI CLUB". Nessun file del logo è stato aggiunto al repository.
5. L'elenco della segreteria mostra al massimo 500 turni e periodi di al massimo 93 giorni.
6. Le regole P5–P7 sono scelte iniziali da confermare dopo la prova.
7. Il ruolo "Dipendente Bracelli" è creato automaticamente (P3), in deroga temporanea a F2.
