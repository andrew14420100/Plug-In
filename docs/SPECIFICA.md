# Specifica funzionale — Bracelli Club Plug-In

**Stato:** baseline di progetto, non software già implementato.  
**Ambito:** sito Bracelli Club su TopHost, WordPress, Elementor; repository pubblico `andrew14420100/Plug-In`.  
**Requisito economico:** zero costi ricorrenti obbligatori imposti dal plugin (le commissioni di servizi terzi possono esistere; essi restano facoltativi).

## 1. Prodotto, UI, amministrazione

- Un solo plugin distribuibile via ZIP, basato su un **core** e moduli indipendenti installabili anche separatamente da ZIP/GitHub autorizzato previa verifica sicurezza/versione/compatibilità e approvazione.
- Dashboard futuristica moderna, veloce, responsive, grafici e indicatori su sito/soci/corsi/prenotazioni/pagamenti/sicurezza; temi chiaro/scuro, tavolozza configurabile, personalizzazione icone e posizione widget.
- Dashboard iniziale Bracelli all'accesso amministrazione WP; menu laterale personalizzabile con possibilità di nascondere la UI WordPress standard, pulsante sempre disponibile per riaprirla e recovery mode/ingresso admin standard se il plugin fallisce.
- Dashboard differenti per amministratori e dipendenti; preferenze grafiche e preferiti per account; ruoli predefiniti, sottoruoli e permessi per modulo/azione e, se necessario, categoria di dato. Circa cinque operatori contemporanei previsti.
- Catalogo moduli, stato e dipendenze, scheda con versioni, diagnostica, performance, log, errori; disattivazione di dipendenti a cascata SOLO su conferma consapevole; dati preservati su disinstallazione, fatte salve esigenze legali/privacy.
- Log approfonditi utente/data/azione/cambiamento, filtri e ricerca; statistiche modulo e avvisi per quelli pesanti. Diagnostica approfondita solo su richiesta.
- Audit delle modifiche e workflow di approvazione ove richiesto; notifiche, ricerca globale, form avanzati e tabelle ordinate/filtrate.

## 2. Archivio soci, famiglie, tessere (priorità alta)

- Gestire ~1.001–5.000 soci/anno con anagrafica completa, contatti, codice fiscale, indirizzo; registro sociale indipendente dagli account WordPress, nessuna registrazione WP automatica per i soci.
- Stato separato per **tessera**, **iscrizione associativa**, **singolo corso** e **attivazione del socio nell'elenco operativo del sito**; archiviazione solo manuale dopo stato scaduto, con regole di conservazione conformi.
- Gestione completa nuclei familiari, genitori/tutori/minori; relazioni verificabili e permessi di accesso per categoria documento/dato.
- Le tessere fisiche **già esistono**, hanno codice a barre con identificativo persistente nel corso degli anni. La lettura deve supportare dispositivo USB, ricerca e dispositivi compatibili; formato tecnico da verificare su una tessera reale. Sostituzione smarrimento/danneggiamento con storico codice.
- **Cerca per codice tessera:** utilizzare dati veri dell'archivio Club Manager importato/sincronizzato, NON creare una finta scheda vuota con il solo ID come comportamento finale. Mostrare anteprima completa, controlli duplicati, campi modificabili secondo permessi, conferma prima di associare.
- Se la tessera non viene trovata: ricerca alternativa, controllo salute sincronizzazione, verifica duplicati, eventuale creazione manuale SOLO autorizzata. Se la scheda è incompleta: evidenziare campi mancanti, validare, completare manualmente secondo permessi, stato Completa/Incompleta.
- Se scaduta: controllare separatamente tessera/iscrizione/corsi, mostrarne scadenze, rinnovo guidato e regole azione; non riassegnare codice in un semplice rinnovo.
- Scheda socio futuristica: dati essenziali prominenti, sezioni configurabili, ricerca, cronologia, note, azioni rapide, documenti e permessi. Dati sensibili soltanto per finalità/ruolo autorizzati.
- **Due viste distinte:** archivio generale di tutti i soci sincronizzati e elenco di soci *attivati sul sito*. Inserimento singolo tramite codice tessera, conferma, deduplica; attivazioni multiple autorizzate da selezione o elenco codici (UX specifica ancora da definire). Attivazione operativa NON equivale a validità dell'iscrizione.
- Dati importati in WordPress disponibili anche con Club Manager chiuso/PC spenti mediante ultima copia sincronizzata, con timestamp di freschezza visibile; nuove modifiche arrivano solo quando la sorgente è raggiungibile.

## 3. Corsi, presenze, istruttori

- Categorie multilivello e campi completamente configurabili per disciplina/età/livello; istruttori su più corsi con disponibilità, sostituti e storico.
- Calendario lezioni: ricorrenze, festività, eccezioni, recuperi e singole sessioni; cronologia annullamenti/spostamenti con data/autore/motivo.
- Presenze **registrate manualmente** tramite Presente/Assente/Giustificato, salvataggio immediato; correzioni pregresse entro finestra configurabile, con audit.
- Statistiche giornaliere/mensili/annuali, grafici e esportazioni; diritti degli istruttori da configurare.
- Aggiornamento delle pagine pubbliche dei corsi in base al gestionale con controllo degli aggiornamenti pubblicati.

## 4. Prenotazioni pubbliche e strutture

- Prenotazioni per campi, sale, corsi, lezioni private e tipi personalizzabili; disponibilità, pause, festivi, blocchi, regole anti-sovrapposizione per risorsa.
- Socio e cliente esterno possono prenotare dal sito secondo modalità per servizio, con moduli condizionali/validazione, conferma su sito ed email tramite sistema hosting; possibilità di modifica/annullamento secondo regole del servizio.
- Prezzi/tariffe prenotazioni completamente configurabili (non significa pagamenti online obbligatori); cancellazione libera immediatamente la disponibilità.
- Calendario interattivo tutte le viste, filtri, trascinamenti soltanto autorizzati; regole di approvazione dei non soci.
- **Liste d'attesa: NON ANCORA DECISO (domanda 208).**

## 5. Quote, incassi, report, documenti economici

- Listini e storico completo; calcolo quote tramite regole configurabili per corso/socio/periodo; sconti SOLO manuali per ora, tramite permessi espliciti.
- Acconti, rate, saldo residuo e storico. **Per ora registrazione degli incassi SENZA gateway di pagamento** o servizi obbligatori.
- Solo amministratori accedono ai dati economici in generale; capacità specifiche sulle singole operazioni (es. proposta/applicazione sconto) da definire mediante permessi granulari, senza accesso indiscriminato a report.
- Modelli documentali e numerazioni separate, ricevute/riepiloghi; verificare obblighi fiscali prima di descrivere documenti come fatture ufficiali. Report filtrati categoria/corso/metodo/periodo.
- **Sincronizzazione pagamenti da Club Manager: da decidere dopo analisi dei dati esposti (domanda 328).**

## 6. Sito WordPress e sostituzione altri plugin

- Obiettivo sostituire il maggior numero possibile di plugin esistenti senza interrompere il sito; migrazione con inventario, mappatura funzioni, anteprima, backup, test, approvazione e possibilità di ritirata.
- Elementor/Elementor Pro usati oggi; mantenere fino ad analisi di compatibilità. Supporto widget Elementor, shortcode e pagine pubbliche gestite, NON imporre editor Gutenberg addizionali richiesti come non prioritari.
- Form builder visuale con modelli, campi condizionali, validazione, upload protetto, storage risposte, notifiche/workflow.
- Slider, banner, caroselli con editor e programmazione; sistema multimediale con organizzazione, rilevazione duplicati, ottimizzazione e ricerca.
- **Nessun editor CSS/JS/snippet nel pannello** (richiesta esplicita). Header/footer/layout attuali conservati fino a verifica; non presumere che siano sostituibili subito.
- Compatibilità con WooCommerce prevista come *compatibilità*, non installazione richiesta.
- Plugin menzionati nel precedente inventario non verificato in questa sessione: Amelia, Contact Form 7, Revolution Slider, Yoast SEO, TRX Addons / TRX Updater, WPCode, Elementor/Pro, Vibe AI, Download Plugins From Dashboard Pro. Rilevare l'inventario attuale prima delle decisioni.

## 7. SEO, analytics, marketing

- Migrazione completa da Yoast con diff ante/post, controllo dei metadati, redirect e diagnostica senza perdita di indicizzazione.
- Analisi SEO senza AI a pagamento: punteggi e suggerimenti, sitemap, robots, canonical, schema, redirect, 404/link rotti, statistiche, notifiche.
- Analytics sito con dashboard avanzata, obiettivi e storico indipendente da Google Analytics; progetto privacy e carico del database da validare.
- Newsletter con modelli, segmentazioni, iscrizioni/consensi, revoche, disiscrizione, report errori e limiti di invio hosting.
- Pubblicazioni automatiche corso/avvisi configurabili; report esportabili schedulabili con storico; niente invii di massa senza consenso tracciabile.

## 8. Sicurezza, backup e automazioni

- 2FA obbligatoria amministratori, opzionale dipendenti, metodi configurabili; protezione tentativi di accesso, alert, sblocco amministrativo; seconde conferme con finestra per azioni sensibili, senza sostituire permessi server.
- Documenti soci protetti con versioni, autorizzazioni granulari, scadenze, tracciamento accessi, minimizzazione dati e strumenti richieste privacy.
- Backup completi/selettivi, manuali e programmati, destinazioni multiple, limiti personalizzabili numero/spazio/età, controlli preventivi sullo spazio, verifica d'integrità e test di recupero in ambiente isolato.
- Automazioni con editor visuale leggero per TopHost, libreria flussi modificabili, code persistenti, retry, limiti rate, errori/audit; scelta cron secondo compatibilità hosting verificata.
- Diagnostica profonda *su richiesta*, logging essenziale continuo e indicatori per gli errori; evitare scanner pesanti su ogni pagina.

## 9. Integrazioni e funzionalità rinviate

- **AI interna** rinviata alle release future, Claude Code è lo sviluppatore assistito, non un servizio AI obbligatorio nel runtime.
- API/servizi esterni più complessi e relativi collegamenti (Telegram, WhatsApp, ecc.) rinviati alla fase successiva, pur predisponendo adattatori. Nessuna garanzia di gratuità delle API terze.
- **Nuovo gestionale Windows proprietario** verrà creato in futuro; Club Manager deve rimanere intatto e operativo oggi.
- Compatibilità futura per mobile, Multisite e integrazioni da valutare in rapporto a hosting/budget/esigenze.

## 10. Soglie e criteri non funzionali

- Priorità prestazioni e accessibilità; WordPress e PHP supportati da verificare in locale; installazione su hosting condiviso TopHost (no richiesta di Node/Python lato produzione).
- ~5 operatori contemporanei e almeno migliaia di soci simulati per test dataset; indici selettivi, query paginabili, batching, carico misurato.
- Recovery amministrativo anche in caso di JS/dashboard rotta; le funzioni critiche non devono dipendere dalla sola UI React.
- Aggiornamenti GitHub automatici SOLO per release stable approvate dal flusso test e integrità; backup prima di upgrade, rollback applicativo, ripristino dati sotto conferma.
- Costi zero obbligatori. Repo pubblico **senza dati personali**, niente licenza decisa senza autorizzazione.
