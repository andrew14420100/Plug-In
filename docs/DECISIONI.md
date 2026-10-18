# Registro delle decisioni — Questionario Bracelli Club

**Baseline al 15/10/2026.** Le risposte sono state fornite in più blocchi nel dialogo. Questo file conserva le decisioni operative essenziali e i numeri delle domande per rintracciarle, NON inventa risposte mancanti. Le 331–339 appartengono al filone aggiornato sulle tessere: un vecchio questionario 331–350 fu sospeso e non va interpretato come approvato.

**Legenda:** ✅ confermato · ⏳ da verificare / decidere · ➜ futuro / differito.

## Risposte 1–160 — scelte fondamentali (raggruppate)

- **1–10**: plugin *tutto-in-uno* **Bracelli Club Plug-In** (solo sito proprio), moltissime funzioni, obiettivo sostituire plugin preesistenti, 100% gratuito senza costi obbligatori, moduli ampliabili; priorità di dettaglio da definire gradualmente. Nessuna AI runtime per ora.
- **11–20**: uso da proprietario/amministratori/dipendenti, registro operazioni e possibilità di approvazione; no registrazione pubblica automatica/account social in questa fase, privilegi configurabili; altre scelte di controllo utenti da sviluppare gradualmente.
- **21–30**: un unico pacchetto con core/moduli indipendenti e catalogo moduli; stato, configurazione, migrazioni, import/export e compatibilità estesa; predisposizione a evoluzione senza ricostruire il core.
- **31–40**: sito `centrosportivobracelliclub.it`, TopHost, WordPress e PHP dichiarati “ultimi”, Elementor; compatibilità WooCommerce, altri temi/hosting, toolchain moderna e sito staging/locale da verificare. Non assumere numeri versione senza check.
- **41–60**: dashboard futuristica, temi chiaro/scuro, colori selezionabili, menu laterale, widget drag-and-drop, grafici, notifiche, mobile, frontend/pagine/shortcode e widget Elementor. Nessuna imposizione di Gutenberg addizionale; alcune personalizzazioni utente ancora in definizione, poi precisate Q171–177.
- **61–80**: dati e tabelle, cronologia/audit, recupero, import/export, documenti, ricerca, automazioni, promemoria, notifiche e code/ritenti; il modello dati esatto non è stato scelto dall'utente, deve progettarlo Claude.
- **81–90**: funzionalità di AI integrata rinviate a fasi future, non inserire servizi/chiavi AI in Fase 1.
- **91–110**: SEO, Search Console/analytics, newsletter, vendita e contabilità, quote, sconti e pagamenti; scelte per la prima versione ristrette in Q211–218 (nessun gateway per ora).
- **111–120**: soci, corsi, doc, calendari, presenze, aree riservate; Q112 tessere fisiche già esistenti da associare con codice, Q117 presenze manuali.
- **121–130**: integrazioni esterne complesse posticipate.
- **131–160**: sicurezza e privacy, backup, verifiche, velocità, diagnostica, test, CI, ZIP; priorità prestazioni (Q141), ~5 utenti simultanei (Q142), scala esatta DB ignota (Q143); GitHub repository PUBBLICO (Q152), NO pubblicazione nella directory WordPress.org (Q158). Q137 negava l'aggiunta di un autonomo sistema personalizzato di tempi retention; questo non esenta dagli obblighi privacy/legali e dalle richieste Q138/242.

## Risposte 161–170 — impianto tecnico

- **161** dashboard Bracelli come schermata iniziale admin.
- **162** menu personalizzabile, possibilità di nascondere menu WP.
- **163** catalogo con moduli aggiuntivi installabili.
- **164** struttura database ottimizzata da scegliere dopo analisi.
- **165** aggiornamenti GitHub automatici solo dopo superamento test.
- **166** backup manuali, giornalieri, settimanali e prima aggiornamenti, configurabili.
- **167** ruoli predefiniti + permessi personalizzati.
- **168** decisione Elementor Pro rinviata a inventario sito.
- **169** migrazione altri plugin da decidere dopo inventario.
- **170** sviluppo/test sul PC locale Windows.

## Risposte 171–190 — dashboard, moduli, dati iniziali

- **171** dashboard distinte per amministratori/dipendenti.
- **172** pulsante sempre accessibile per menu WordPress.
- **173** recovery con WP standard + recupero/disattivazione moduli.
- **174** dashboard aggregata sito/soci/corsi/prenotazioni/pagamenti/sicurezza.
- **175** editor tema/colore/icone/posizione widget, non un design editor illimitato.
- **176** preferenze grafiche per utente.
- **177** preferiti ordinabili per utente.
- **178** log utente/data/azione/diff con ricerca.
- **179** nuovi moduli ZIP validati su compatibilità/permessi/sicurezza.
- **180** installazione da GitHub con verifica versione.
- **181** scheda modulo diagnostica/statistiche/errori.
- **182** disattivazione con conferma e cascade dei dipendenti.
- **183** prestazioni per modulo e avvisi su moduli pesanti.
- **184** moduli sperimentali ammessi anche sul sito pubblico previa conferma (non bypassare verifiche o backup).
- **185** import/export configurazioni con anteprima differenze.
- **186** preservare dati moduli disinstallati finché cancellazione esplicita, nel rispetto norme.
- **187** elenco soci oggi su gestionale esterno.
- **188** circa 1.001–5.000 soci/anno.
- **189** anagrafica/contatti/codice fiscale/indirizzo richiesti.
- **190** tessere già esistenti con codice a barre.

## Risposte 191–210 — socio/corso/presenza/prenotazione

- **191** identificativo barcode della tessera stabile nei rinnovi.
- **192** nuclei familiari, minori e responsabili.
- **193** scheda storico completo, ricerca, export e audit.
- **194** scaduto, poi archiviazione manuale.
- **195** tassonomia corsi personalizzabile multicriterio.
- **196** ricorrenze, eccezioni/festivi, lezioni manuali.
- **197** storico lezioni spostate/annullate con data/autore/motivo.
- **198** istruttori multicorso, disponibilità/sostituti/storico.
- **199** Presente / Assente / Giustificato, manuale.
- **200** salvataggio immediato presenze.
- **201** rettifiche entro intervallo configurabile.
- **202** statistiche e report giornalieri/mensili/annuali.
- **203** ogni tipologia di prenotazione configurabile.
- **204** prenotazioni anche da soci/clienti sul sito pubblico.
- **205** calendari con pause, festivi, blocchi.
- **206** regole conflitti per struttura.
- **207** tariffario personalizzabile prenotazioni.
- **208 ⏳** liste d'attesa DA DECIDERE.
- **209** annullamento libera immediatamente disponibilità.
- **210** calendario con viste, filtri, drag autorizzato.

## Risposte 211–230 — economia, moduli, SEO

- **211–213** listini storicizzati, calcolo quote per regole, acconti/rate/saldi e storico.
- **214** sconti SOLO manuali.
- **215** PER ORA solo registrazione incassi, NO gateway.
- **216** modelli documenti e numerazioni separate.
- **217** report per categoria/corso/metodo/periodo.
- **218** visibilità/modifica dati economici generale solo amministratori; specifica operazione di sconto da precisare con Q286.
- **219–220** editor moduli completo con risposte, notifiche, workflow, validazione/condizioni.
- **221** slider/caroselli/banner con programmazione.
- **222** aggiornamento automatico pagine corso con controllo pubblicazione.
- **223** libreria media avanzata, ottimizzazione/duplicati.
- **224** NON creare editor codice CSS/JS/snippet dal pannello.
- **225** mantenere layout/header/footer esistenti finché compatibilità verificata.
- **226** sostituire altri plugin solo dopo test/approvazione.
- **227** migrazione Yoast completa con diff e verifica.
- **228** SEO avanzato senza AI a pagamento.
- **229** SEO tecnico con anteprima/diagnostica.
- **230** gestione 404, redirect, link rotti, statistiche/alert.

## Risposte 231–250 — statistiche e gestione operativa

- **231** analytics interno con storico, obiettivi e dashboard.
- **232** newsletter con consenso/disiscrizione/report.
- **233** pubblicazioni corsi configurabili per tipologia.
- **234** report completi programmabili/storicizzati.
- **235** 2FA obbligatoria amministratori, opzionale dipendenti.
- **236** blocco accessi sospetti con regole, alert e sblocco.
- **237** operazioni sensibili richiedono finestra conferma.
- **238–240** backup completi/selettivi, limiti per numero/spazio/anzianità, test ripristino isolato.
- **241** documenti protetti con permessi, scadenze, traccia.
- **242** privacy integrata con registro/permessi.
- **243** workflow visuali ma leggeri per hosting condiviso.
- **244** libreria automazioni modificabile.
- **245** code, retry, log e diagnostica.
- **246** diagnostica approfondita solo quando richiesta.
- **247** CI completa con test/ZIP, blocco release non valide.
- **248** release stable generate automaticamente dopo test richiesti.
- **249** changelog, anteprima, storico, rollback.
- **250** manutenzione completa con gestione operazioni/log.

## Risposte 251–270 — migrazione e ambiente test

- **251** gestionale installato sul PC.
- **252 ⏳** metodo export sconosciuto.
- **253–254** import tutti dati disponibili/compatibili (compresi doc e pagamenti), tutto lo storico possibile.
- **255** sync automatica solo se supportata.
- **256** deduplica multicampo.
- **257** anteprima intera, possibilità annullare import.
- **258–259** relazioni familiari correggibili e documenti collegati protetti.
- **260** correzione record invalidi dall'interfaccia.
- **261** lettura barcode da metodi compatibili senza nuovi dispositivi obbligatori.
- **262** schermata rapida per socio/operazioni autorizzate.
- **263** collisioni barcode gestite con eccezioni autorizzate e storico.
- **264** sostituzione card e storico ID.
- **265 ⏳** simbologia barcode ignota.
- **266** LocalWP su Windows.
- **267** migliaia di soci fittizi per test.
- **268** script setup/diagnostica/restore ambiente.
- **269** pipeline con compatibilità/backup/rollback.
- **270** aggiornamento automatico con alternativa manuale.

## Risposte 271–290 — Club Manager e permessi

- **271** software commerciale “**Club Manager**”.
- **272** esplorare alternative export senza modificare programma originale.
- **273** origine dati e riconciliazione tracciata.
- **274 ⏳** uso parallelo del vecchio gestionale da decidere dopo analisi; successivamente chiarito: Club Manager resta operativo per ora.
- **275–276** conflitti/regole per campo, validazione anagrafica configurabile.
- **277** stati tessera/iscrizione/corso separati.
- **278** permessi per categorie di dati personali.
- **279–280** archivio documentale con versioni/scadenze/permessi, promemoria regolabili.
- **281–285** prenotazioni pubbliche configurabili per servizio; form condizionali, conferme web/email, annullamento/modifica regolati, non soci con possibile approvazione.
- **286** permessi granulari sulle operazioni economiche (non aprire tutti i report ai dipendenti).
- **287** modelli documenti, integrazioni fiscali specialistiche in futuro.
- **288** backup multi destinazione e controllo spazio.
- **289** sistema recupero update con conferma prima di ripristini dati.
- **290** prima release con architettura globale, moduli implementati secondo dipendenze.

## Risposte 291–310 — conservazione Club Manager e sito

- **291 ⏳** versione Club Manager da controllare sul PC.
- **292** dati presumibilmente in file/database locale del PC (da verificare).
- **293** verifiche esclusivamente sicure e non invasive.
- **294** prima dati simulati, poi eventualmente anonimizzati.
- **295** ogni import conserva la sorgente.
- **296** confronto import e blocco completamento se errori critici.
- **297** migrazione verificata, personale preparato, recupero pronto prima di qualsiasi dismissione.
- **298** checkpoint/report/ripresa e ripristino.
- **299** soci separati da account WP.
- **300** ruoli/sottoruoli e permessi per modulo.
- **301** documenti sanitari per ruolo/finalità/necessità, log accessi.
- **302** conflitti barcode senza selezione automatica ambigua.
- **303** **requisito rivisto:** NON sostituire ora Club Manager, mantenere il software desktop operativo; importare soci/clienti/utenti e ricevere aggiornamenti automatici dal programma al sito; nuovo software desktop proprio solo in futuro.
- **304** utilizzare mail servizio hosting.
- **305–306** newsletter con limitazioni invio, errore/revoca e prova di consenso, double opt-in opzionale.
- **307** cron adattato a TopHost dopo verifica.
- **308** avvisi/manutenzione spazio backup.
- **309** controllo completo sicurezza e test moduli.
- **310** Fase 1 accettata solo con test, review e ZIP verificato.

## Risposte 311–330 — sincronizzazione Club Manager → WordPress

- **311** Club Manager su due o più PC con archivio condiviso.
- **312** agente parte automaticamente al boot senza passaggi manuali.
- **313** aggiornamenti immediati, se tecnicamente possibile.
- **314** Claude valuta il metodo di lettura più sicuro, non alterare il software.
- **315** sincronizzare dati disponibili e autorizzati.
- **316** campi per categoria configurabili e minimizzazione dati.
- **317** sincronizzazione SOLO Club Manager → WordPress; non invertire.
- **318** nuovi soci creati sul sito rimangono su WordPress.
- **319 ⏳** chiave/associazione record da verificare dopo ispezione DB sorgente.
- **320** pannello conflitti/diff con permessi.
- **321** cancellazioni/archiviazioni in origine aggiornano lo stato, preservando lo storico compatibilmente con privacy.
- **322** coda locale/offline, ripresa e niente doppi inserimenti.
- **323** retry, diagnostica, alert e cronologia record non aggiornati.
- **324** dashboard avanzata stato sync.
- **325** chiavi dedicate, rotazione/revoca, log connessioni.
- **326** avvio automatico al boot Windows.
- **327** adattivo, non rallentare il software.
- **328 ⏳** sincronizzazione pagamenti da decidere dopo ispezione disponibilità e semantica.
- **329** relazioni familiari con verifica/permessi.
- **330** affidabilità solo dopo collaudo sicurezza/confronti e approvazione.

## Risposte 331–339 — tessere e archivio socio (NUOVO FILONE)

- **331** barcode trovato: recuperare dati reali, mostrare **anteprima**, controllare duplicati, consentire modifiche autorizzate, confermare associazione.
- **332** barcode non trovato: ricerca avanzata alternativa, stato sync, controllo duplicati, registrazione manuale solo autorizzata.
- **333** dati incompleti: evidenziare mancanti, integrare secondo permessi, validare, flag scheda Completa/Incompleta.
- **334** stati scadenza separati per tessera/iscrizione/corso, rinnovo guidato, regole operative configurabili.
- **335** scheda socio moderna/futuristica, informazioni principali in primo piano, sezioni personalizzabili, cronologia, ricerca/azioni rapide e autorizzazioni per dato.
- **336** conflitti tra modifiche WP e aggiornamenti Club Manager: regole per campo, confronto versioni, audit, approvazioni quando necessario, senza flusso WP→Club Manager.
- **337** l'archivio Club Manager persiste anche se programma chiuso; lettura a programma chiuso solo se tecnicamente possibile su PC acceso; se PC spento WordPress usa dati già sincronizzati, con indicazione ultima sync e recupero futuro.
- **338** archivio WordPress indipendente, dati consentiti, sync automatica one-way, storico/deduplica/ricerca, funzionamento anche con PC offline con l'ultima copia.
- **339** archivio generale sincronizzato distinto da elenco **soci attivati sul sito**. Attivazione tramite codice tessera e conferma, deduplica e operazioni massive autorizzate. Non equivale a stato iscrizione.
- **340 NON RISPOSTA**: domanda aperta sul metodo di attivazione multipla (selezione elenco vs lista barcode vs combinazione).

## Priorità e punti aperti

1. **P0**: ottenere nome preciso/versione di Club Manager, ubicazione e formato DB, metodi di backup/export/API, modalità lecita e sicura di lettura su PC acceso; nessun accesso diretto è stato verificato.
2. **P0**: verificare versioni effettive WP/PHP/Elementor e plugin attivi sul sito, preferibilmente copia di test.
3. **P1**: decidere liste d'attesa (Q208), pagamenti da Club Manager (Q328), metodo attivazioni multiple (Q340).
4. **P1**: verificare simbologia barcode e comportamento lettore reale (Q265).
5. **P1**: modello effettivo identificazione/matching sorgente (Q319) e sicurezza/retention dei documenti.
6. **P1**: dopo inventario, decidere Elementor Pro e ordine migrazioni (Q168–169).
7. **P2**: chiarire diritti di sconto per segreteria (Q214/218/286) prima del modulo economico.
8. **P2**: integrazioni esterne e AI spostate a versioni future.

**Regola di prevalenza:** le precisazioni cronologicamente successive dello stesso requisito prevalgono su interpretazioni generiche precedenti, senza cancellare le scelte non contraddittorie. Esempio: l'idea iniziale di rimpiazzare tutti i plugin NON richiede di sostituire subito Club Manager né Elementor; è una roadmap soggetta a verifica.

## Ottobre 2026 — Prototipo "Turni e presenze"

- L'utente sospende l'intervista dei requisiti e autorizza **solo** un primo prototipo da provare in LocalWP: orari dei dipendenti gestiti dalla segreteria, visione dei propri turni, registrazione Presente/Assente. Le altre fasi non sono autorizzate.
- Le presenze del personale (Presente/Assente) entrano nel prototipo; timbrature, ritardi, giustificazioni e ore lavorate restano escluse.
- Decisioni tecniche del prototipo (P1–P9), compresa l'eccezione temporanea sul ruolo "Dipendente Bracelli" creato dal plugin: vedi [PROTOTIPO-TURNI-PRESENZE.md](PROTOTIPO-TURNI-PRESENZE.md) §2.
