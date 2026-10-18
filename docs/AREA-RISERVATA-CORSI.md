# Area riservata, corsi e presenze degli insegnanti — plugin 0.3.0 (solo prove in locale)

Branch: `prototype/presenze-locale-v0.1.0` (PR #3, bozza). Moduli nuovi: `area` 0.1.0 e `corsi` 0.1.0. **Non è una release** e non va installato sul sito pubblico.

## 1. Cosa è autorizzato e cosa no

Solo quattro funzioni:
1. area riservata con accesso tramite nome utente o email e password;
2. pulsante «Area riservata» con icona, inseribile nell'header tramite shortcode;
3. corsi attivi caricati dai volantini forniti dall'utente;
4. vista dell'insegnante con calendario e registrazione Presente/Assente.

Escluso: pagamenti, certificati medici, ritardi, timbrature, prenotazioni, newsletter, Club Manager, gestione completa dei soci, presenze degli allievi.

## 2. Decisioni

| # | Decisione | Motivo |
| --- | --- | --- |
| A1 | Un **unico portale** (modulo `area`) con le sezioni Corsi e Turni: stesso accesso, stessa grafica, stesso indirizzo | Evita due portali per la stessa funzione |
| A2 | Indirizzo canonico: `…/?bracelli_portal=1`, che funziona su qualsiasi server. L'indirizzo breve `…/area-bracelli/` viene usato solo dopo una verifica automatica | Su XAMPP senza `.htaccess` l'indirizzo breve restituiva il 404 di Apache |
| A3 | L'accesso usa l'autenticazione di WordPress (`wp_signon`): accetta nome utente o email; nessuna password salvata dal plugin; messaggio d'errore unico | Nessun sistema parallelo di credenziali |
| A4 | Nessuna registrazione pubblica: gli account si creano solo da Utenti → Aggiungi nuovo | Richiesta esplicita |
| A5 | Gli utenti dell'area senza permessi di redazione o amministrazione (segreteria, insegnanti, dipendenti, soci) **non entrano nella bacheca WordPress**: login e `/wp-admin/` portano all'area riservata | «Nessun accesso alla bacheca per utenti non amministratori» |
| A6 | Insegnante = ruolo esistente **«Istruttore Bracelli»** (capability `bracelli_corsi_teach`). Nuovo ruolo **«Socio Bracelli»** (calendario dei corsi). Nessun ruolo viene assegnato agli account esistenti | Riuso dei ruoli; permessi minimi |
| A7 | Modello dati distinto: **corso** → **orari settimanali** (giorno e ora, insegnante = account) → **presenza dell'insegnante** per orario e data | I quattro concetti richiesti, senza confonderli |
| A8 | Il nome dell'insegnante eventualmente presente nei dati resta un'etichetta («nei dati: …») e non collega mai un account. Il collegamento lo fa la segreteria scegliendo l'account | Nessun accesso concesso sulla base del solo nome |
| A9 | L'insegnante registra la presenza dal giorno della lezione e fino a 30 giorni dopo; può cambiarla solo nel giorno della lezione. Solo per le proprie lezioni e solo nei giorni in cui la lezione è prevista | Regola semplice, da confermare dopo la prova |
| A10 | Gli orari non si eliminano: si annullano e restano nello storico (registro attività). Un orario modificato dalla segreteria diventa «manuale» e l'importazione non lo sovrascrive | Nessuna cancellazione definitiva; conflitti mai risolti in silenzio |
| A11 | Importazione da CSV ripetibile: chiave stabile per corso, anteprima senza scritture, conferma, rapporto. Una seconda esecuzione non crea duplicati e non cancella nulla | Requisito di idempotenza |
| A12 | I dati reali dei corsi stanno in `private/` (escluso da Git) e nel database locale; nel repository ci sono solo dati fittizi di test | Repository pubblico |

## 3. Permessi

| Capability | Chi | Consente |
| --- | --- | --- |
| `bracelli_corsi_view` | Socio, Istruttore, Segreteria, Gestore corsi, Amministratore | Calendario dei corsi attivi |
| `bracelli_corsi_teach` | Istruttore (non l'amministratore) | Solo le proprie lezioni e la propria presenza |
| `bracelli_corsi_manage` | Segreteria, Gestore corsi, Amministratore | Orari, assegnazione degli insegnanti, annullamenti, quadro delle presenze |
| `bracelli_corsi_import` | Solo Amministratore (non assegnabile) | Importazione da file |

I controlli avvengono sul server, in `CourseService`. Un orario assegnato a un collega risulta «non trovato». Ogni azione ha un nonce legato all'azione e al record; le query hanno segnaposto e l'output è sottoposto a escape. La segreteria non riceve privilegi amministrativi.

## 4. Formato del file dei corsi (CSV)

Colonne: `chiave;sezione;corso;gruppo;giorno;inizio;fine;insegnante;sede;note`. Va bene anche il separatore `,`; l'intestazione è obbligatoria.

- Una riga per ogni orario settimanale: giorno in italiano, orari `hh:mm`.
- Una riga senza giorno né orario registra il corso senza orari, segnalato come da completare.
- La colonna `insegnante` è solo il nome indicato nei dati (vedi A8).

Si importa da **Bracelli Club → Importa corsi**, oppure da terminale:

```
wp bracelli-corsi import file.csv        # solo anteprima
wp bracelli-corsi import file.csv --yes  # importa
```

## 5. Importazione dei corsi dai volantini (aggiornati al 16/09/2026)

File privato: `private/corsi/corsi-volantini-2026-09-16.csv`, non presente nel repository.

| Sezione | Corsi | Orari settimanali |
| --- | --- | --- |
| Arti marziali e sport da combattimento | 21 | 45 |
| Tennis tavolo | 2 | 4 |
| Pattinaggio artistico | 5 | 0 |
| Calcio e calcio a 5 | 7 | 14 |
| Ginnastica funzionale – Flowing Academy | 1 | 10 |
| Parkour | 3 | 6 |
| Ginnastica | 4 | 5 |
| Danza acrobatica aerea | 1 | 0 |
| Danza & ballo | 14 | 7 |
| Scuola musica e canto | 2 | 0 |
| **Totale** | **60** | **91** |

Prova su un sito usa e getta:
- prima importazione: 60 corsi e 91 orari creati;
- seconda importazione: 0 creati, 60 corsi e 91 orari invariati;
- 39 righe segnalate come voci da chiarire.

**Voci da chiarire (nessun dato inventato):**
- **Senza orario nei volantini:**
  - Avviamento Arti Marziali (Lun-Mer-Ven);
  - Aikido (Mar-Gio);
  - i 5 corsi di Pattinaggio (giorni indicati, nota «Quando piove non si fa lezione»; in «Corso agonismo» il volantino scrive «Mertedì», letto come Martedì e segnalato);
  - Danza acrobatica aerea (Lun, Mar, Mer, Gio; «per i dettagli contatta il centro»);
  - Ginnastica Artistica Pre Agonistica;
  - dieci corsi di Danza & ballo: Danza Moderna, Modern 10/14, Modern da 15, Classica Gioco, Propedeutica, Classica 1° e 2°, Balli di Gruppo Giada, Balli di Gruppo Manuel, Salsa e Bachata, Tango Argentino;
  - strumento e canto individuali («da concordare»).
- **Jeet Kune Do + Kali & Silat:** gli orari coincidono con quelli dei due corsi separati. Probabile iscrizione combinata: corso registrato senza orari propri, per non duplicare le lezioni.
- **Ginnastica Artistica:** «Corsi pomeridiani Lunedì, martedì e venerdì 15:00-20:00» è una fascia complessiva, non l'orario di una lezione.
- **Ginnastica funzionale – Flowing Academy:** importate le 10 fasce orarie indicate; da confermare se ogni fascia ha un insegnante.
- **Flamenco e Hip Hop:** orari letti da testo molto piccolo, da confermare.
- **«Giada» e «Manuel»** potrebbero essere nomi di insegnanti: non collegati ad alcun account.
- **Insegnanti:** nei volantini non compare alcun insegnante. Tutti gli orari sono «Da assegnare»: la segreteria collega gli account da **Orari dei corsi**.
- **Non importati perché non sono corsi:** affitto campo calcetto, affitto sale per feste. La nota sui corsi per ragazzi con Sindrome di Down o altre speciali abilità resta informativa.

## 6. Prova in LocalWP (o nell'installazione locale di prova)

1. Installa lo ZIP (Plugin → Aggiungi nuovo → Carica plugin, scegli «sostituisci» se il plugin è già presente) e apri una volta la bacheca da amministratore: così si completano le migrazioni.
2. Imposta **Impostazioni → Generali → Fuso orario: Roma**.
3. **Bracelli Club → Importa corsi:** carica `corsi-volantini-2026-09-16.csv` → Mostra anteprima → Conferma e importa.
4. **Utenti → Aggiungi nuovo**, con email inventate `@example.org`:

   | Utente | Ruolo |
   | --- | --- |
   | `segreteria1` | Segreteria Bracelli |
   | `insegnante1`, `insegnante2` | Istruttore Bracelli |
   | `socio1` | Socio Bracelli |

5. **Bracelli Club → Area riservata:** indica l'indirizzo esatto e mostra un'anteprima del pulsante.
6. Prova:
   - la segreteria assegna `insegnante1` a un corso di oggi (Orari dei corsi → Modifica → «Assegna questo insegnante anche agli altri orari attivi del corso»);
   - l'insegnante accede **con l'email** e preme Presente;
   - la segreteria vede la presenza in **Presenze insegnanti**;
   - il socio vede solo il calendario.

### Pulsante nell'header con Elementor
1. Modelli → Theme Builder (o l'header del tema) → modifica l'header della copia locale.
2. Elimina il pulsante blu «Prenota le lezioni gratuite».
3. Trascina nello stesso punto il widget **Shortcode** (gratuito, non serve Elementor Pro) e scrivi `[bracelli_area_riservata]`.
4. Facoltativo: `stile="scuro"` o `stile="chiaro"`, `testo="…"`.
5. Allinea a destra il widget con le stesse spaziature del pulsante precedente e controlla l'anteprima su desktop e telefono.

Lo shortcode non modifica automaticamente l'header: va inserito a mano, solo nella copia locale.

## 7. Verifiche eseguite (container Linux, non sul PC dell'utente)

| Verifica | Esito |
| --- | --- |
| PHPCS, PHPStan livello 6 | 0 errori |
| Test unitari PHP | 38 OK |
| Test di integrazione su WP 7.1.3 (anche ordine casuale) e WP 6.9.10 | 83 OK, di cui 18 nuovi per i corsi (importazione, ripetibilità, conflitti, permessi, date, accessi incrociati, portale, shortcode, conteggio del file reale) |
| ESLint, Stylelint, TypeScript, Jest | 0 problemi, 14 test OK |
| End-to-end in Chromium con il plugin installato dallo ZIP (Apache) | 31 OK, di cui 9 nuovi: accesso, errori generici, nessuna registrazione, accesso con email, pulsante shortcode, assegnazione dell'insegnante, Presente salvato e visibile dopo un nuovo accesso, tentativo sul turno di un collega rifiutato, quadro della segreteria, calendario del socio, importazione da bacheca, smartphone; `debug.log` vuoto |
| Ciclo di vita, aggiornamento 0.1.0 → 0.3.0 e 0.2.0 → 0.3.0 | OK: nessuna perdita di dati; tabelle e ruoli eliminati solo con l'opzione esplicita |
| Plugin Check | Solo le segnalazioni già note (licenza, readme in italiano, `load_plugin_textdomain`) |
| Importazione del file reale (anteprima, importazione, ripetizione) | Vedi §5 |

**Non eseguito:**
- prova sul PC dell'utente (Windows, XAMPP o LocalWP);
- shortcode inserito in un header Elementor reale: provato in una pagina di WordPress, Elementor non è installato nell'ambiente di prova;
- lettori di schermo reali;
- TopHost.

## 8. Limiti e rischi

1. **Nessuna protezione contro i tentativi ripetuti di accesso:** valgono solo quelle di WordPress o di plugin di sicurezza già presenti. Va risolto prima di qualsiasi uso fuori dall'ambiente di prova, dopo l'inventario dei plugin di sicurezza (richiesta F6).
2. Il recupero della password usa il flusso di WordPress (`wp-login.php?action=lostpassword`), che ha la grafica di WordPress e richiede l'invio di email.
3. La segreteria non usa più la dashboard Bracelli nella bacheca WordPress (regola A5): le sue funzioni operative sono nell'area riservata.
4. Le regole su date e modifiche (A9) e la lettura delle voci ambigue (§5) vanno confermate dall'utente.
5. Festività e chiusure non sono ancora gestite: il calendario mostra le lezioni ricorrenti anche nei giorni di chiusura.
