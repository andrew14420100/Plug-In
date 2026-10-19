# Modulo Corsi — Fase 1: catalogo e sale

Plugin 0.4.0 nel repository; pacchetto di collaudo 0.1.10 sulla grafica 0.1.5. Branch locale `feature/corsi-fase-1`, basato su `audit/wordpress-org` (commit 2401bc7, CI verde). Prova solo in locale: nessun merge, nessuna release.

## 1. Requisiti approvati per questa fase

| Requisito | Come è realizzato |
| --- | --- |
| Creare, modificare, archiviare e duplicare corsi, con storico | Scheda del corso nell'area riservata. Ogni modifica va nel registro attività (`corsi_course`); l'archiviazione non cancella nulla |
| Schede più ricche (descrizione, livello o fascia d'età, colore) | Nuove colonne `description`, `level`, `color`. Il colore si sceglie da un elenco fisso con buon contrasto |
| Sale gestite solo dalla segreteria, nessun nome inventato | Tabella `bc_corsi_rooms`, vuota dopo l'aggiornamento. Nomi unici, anche ignorando le maiuscole; le sale si archiviano, non si eliminano |
| Vecchio campo «sede» visibile finché non c'è una sala | La colonna `location` resta. È mostrata e modificabile finché l'orario non ha una sala, poi resta conservata |
| Sala per tutto il corso | «Sala per tutto il corso»: tutti gli orari attivi e da confermare, oppure solo quelli senza sala. Tutto o niente |
| Sovrapposizioni bloccate sul server | `RoomService::conflicts()` dentro un lock MySQL (`GET_LOCK`). Considera gli orari attivi dei corsi attivi, lo stesso giorno e le fasce che si intersecano |
| Gestore corsi non più assegnato, permessi esistenti invariati | Ruolo «legacy»: non creato sulle nuove installazioni, non proponibile ad altri utenti, conservato per chi lo ha. Conversione in Segreteria solo con conferma, una persona alla volta |
| Dipendente senza permessi sui corsi | Invariato e coperto da un test |
| Soci, iscrizioni e capienza fuori ambito | Nessun campo aggiunto |
| Dati esistenti preservati | Migrazione `2026_10_0008` solo additiva e ripetibile. Nessuna reimportazione, nessuna cancellazione |

Per le eccezioni per data (fase 2) il controllo delle sale sarà esteso alle lezioni spostate, ai recuperi e alle chiusure. Oggi gestisce gli orari settimanali, che sono l'unico tipo di lezione esistente.

## 2. Decisioni tecniche

1. **Archiviazione.**
   - Archiviare cambia solo lo stato del corso (`archived`): orari e presenze restano come sono.
   - Calendario, lezioni degli insegnanti e quadro delle presenze mostrano solo i corsi attivi.
   - Il ripristino viene rifiutato se, nel frattempo, la sala è stata occupata da un altro corso.
2. **Corsi creati nell'area riservata.**
   - Hanno la chiave `manuale-<uuid>`.
   - L'importazione rifiuta le righe con questo prefisso, così un CSV non può mai toccarli.
3. **Importazione.** Un corso modificato dallo staff (`manual = 1`) o archiviato non viene sovrascritto né riattivato. Nel rapporto compare tra i «Corsi modificati o archiviati dallo staff (non sovrascritti)», come già avveniva per gli orari.
4. **Duplicazione.**
   - Il nuovo corso parte dal corso originale; gli orari sono copiati con stato `draft` («da confermare») e senza insegnante.
   - Gli orari da confermare non compaiono nel calendario, non sono lezioni e non occupano la sala.
   - Alla conferma il controllo delle sovrapposizioni è obbligatorio.
5. **Sovrapposizione.**
   - Due fasce si sovrappongono se `inizio1 < fine2` e `inizio2 < fine1`: 17:00–18:00 e 18:00–19:00 non si sovrappongono.
   - Gli orari annullati e i corsi archiviati non occupano la sala.
   - Su SQLite (Playground) il lock non serve, perché le scritture sono già una alla volta.
6. **Campi dei moduli.** Il nome di un corso o di una sala viaggia nel campo `nome` e non `name`, che è una variabile di query di WordPress: se c'era un errore, il modulo ripresentato rispondeva 404. L'ha scoperto un test nel browser.
7. **Gestore corsi.**
   - Viene rimosso dall'elenco dei ruoli assegnabili (filtro `editable_roles`), tranne che nel profilo di chi lo ha già: salvare quel profilo non gli cambia ruolo.
   - La pagina Permessi mostra solo i ruoli esistenti, quindi funziona anche senza il ruolo.

## 3. Permessi

| Azione | Amministratore | Segreteria | Insegnante | Socio | Dipendente | Gestore corsi (solo chi lo ha già) |
| --- | --- | --- | --- | --- | --- | --- |
| Catalogo, sale, sala del corso, conferma orari | sì | sì | no | no | no | sì (permessi invariati) |
| Calendario dei corsi (con sale) | sì | sì | sì, in sola lettura | sì | no | sì |
| Conversione di un Gestore corsi | sì, con conferma | no | no | no | no | no |

Ogni azione è verificata sul server: capability, nonce legato all'azione e al record, poi la regola del servizio. Un nonce valido non basta: c'è un test che lo dimostra.

## 4. Migrazione e dati esistenti

`2026_10_0008_create_catalog_and_rooms`:
- crea `bc_corsi_rooms`;
- aggiunge a `bc_corsi_courses` le colonne `description`, `level`, `color`, `manual`, `created_by`, `updated_by`;
- aggiunge a `bc_corsi_slots` la colonna `room_id` (0 = nessuna sala).

Ogni colonna viene aggiunta solo se manca e poi verificata. Nessuna riga viene modificata. La disinstallazione con eliminazione dei dati elimina anche `bc_corsi_rooms`.

Verifiche eseguite sulle migrazioni:

| Prova | Risultato |
| --- | --- |
| Test di integrazione | Migrazione eseguita due volte: corsi, orari (comprese le modifiche manuali e la data di assegnazione) e presenze restano identici byte per byte; nessuna sala creata, nessuna sala assegnata |
| Aggiornamento reale 0.1.9 → 0.1.10 | Database con i 60 corsi dei volantini più i corsi fittizi (63 corsi, 105 orari), una sede scritta a mano e una presenza: impronta md5 dei dati identica prima e dopo |
| Aggiornamento 0.3.1 → 0.4.0 | Ciclo di vita, incluso il caso con eliminazione dei dati |

## 5. Test eseguiti (container Linux, non sul PC del proprietario)

| Controllo | Esito |
| --- | --- |
| PHPCS (regole WordPress del progetto) | 0 errori |
| PHPStan | Non eseguibile qui (download da GitHub bloccato). Lo esegue la CI dopo il push |
| PHPUnit unit | 38 OK |
| PHPUnit integrazione, WP 7.1.3 / MariaDB 10.11 / PHP 8.3, ordine casuale | 102 OK (86 + 16 nuovi in `CatalogTest`) |
| PHPUnit integrazione, WP 6.9.10 | 102 OK |
| Prova di mutazione | Con il controllo delle sovrapposizioni disattivato, 4 test di `CatalogTest` falliscono: i test lo verificano davvero |
| ESLint, Stylelint, TypeScript, Jest | OK |
| E2E Chromium, pacchetto 0.4.0 (repository) | 36 OK (31 + 5 nuovi in `sale-catalogo.spec.ts`), `debug.log` vuoto; ciclo di vita completo OK |
| E2E Chromium, pacchetto 0.1.10 (grafica 0.1.5) | 27 OK (corsi, catalogo e sale, turni, sicurezza); le prove della dashboard sono scritte per la grafica del repository |
| Pagine admin 0.1.10 | 9 pagine Bracelli: 200, nessun errore JavaScript |
| Plugin Check 2.1.0 | 0.4.0: solo i 2 errori di licenza. 0.1.10: licenza e readme in italiano (attesi per la linea locale) |
| WordPress Playground (SQLite) con 0.1.10 | Installazione da ZIP, migrazioni, import di 60 corsi e 91 orari, sale, presenze, ruoli: OK |

Test nuovi (`tests/integration/CatalogTest.php`):
- migrazione additiva e ripetibile;
- sale uniche, gestite solo dalla segreteria (rifiutate a insegnante, socio e dipendente);
- sovrapposizione bloccata in modifica e in aggiunta, fasce che si toccano ammesse, altro giorno ammesso;
- gli orari annullati e i corsi archiviati liberano la sala, e il ripristino in conflitto viene rifiutato;
- assegnazione della sala al corso «tutto o niente» e «solo senza sala»;
- sala usata non archiviabile, sala archiviata non selezionabile;
- creazione e modifica del corso con storico e flag manuale; colore fuori elenco rifiutato;
- l'importazione non sovrascrive i corsi manuali o archiviati e non crea duplicati; la chiave riservata viene rifiutata;
- un corso archiviato esce da calendario e lezioni ma conserva orari e presenze;
- la duplicazione produce orari da confermare, la conferma viene bloccata dal conflitto e riesce dopo la correzione;
- ricerca e filtri;
- l'area riservata rifiuta l'azione di un insegnante anche con nonce valido; il nome HTML viene ripulito;
- il dipendente non ha permessi sui corsi;
- il Gestore corsi non viene creato, conserva i permessi e non è assegnabile ad altri;
- la conversione richiede la conferma e cambia un solo utente;
- il ruolo non si elimina finché qualcuno lo ha; la segreteria non può convertire;
- la pagina Permessi funziona senza il ruolo.

Test nel browser nuovi (`tests/e2e/sale-catalogo.spec.ts`):
- creazione delle sale e nome doppio rifiutato;
- sovrapposizione rifiutata con il nome del corso e il modulo che resta compilato;
- duplicazione e conferma;
- archiviazione;
- telefono da 375 px senza scorrimento orizzontale.

## 6. Collaudo in LocalWP (da fare sul PC del proprietario)

1. Backup del sito di prova, poi **Plugin → Aggiungi nuovo → Carica** `bracelli-club-0.1.10.zip` e scegli «sostituisci».
2. Apri una volta la bacheca da amministratore. In **Bracelli Club → Diagnostica** «Migrazioni database» deve essere «tutte applicate». La riga «Ruolo "Gestore corsi"» dice quanti utenti lo hanno.
3. Controlla che corsi, orari, insegnanti assegnati e presenze siano quelli di prima: **Corsi e orari** e **Presenze insegnanti**.
4. Come segreteria:
   - scheda **Sale**: aggiungi le sale reali;
   - poi **Sala per tutto il corso** su alcuni corsi;
   - prova una sovrapposizione voluta e verifica che venga rifiutata.
5. Crea un corso, duplicalo, conferma gli orari copiati, archivia e ripristina.
6. Come socio, verifica il **Calendario dei corsi**: si vedono le sale e non si vedono i corsi archiviati né gli orari da confermare.
7. **Gestore corsi:** se esistono utenti con questo ruolo, non devono cambiare permessi. La conversione in **Bracelli Club → Permessi** si fa solo se decidi tu.

## 7. Limiti noti

- Le eccezioni per data (chiusure, annullamenti, recuperi) e la stagione arrivano nella fase 2. Fino ad allora il controllo delle sale riguarda solo gli orari settimanali.
- Il controllo delle sovrapposizioni riguarda le sale, non gli insegnanti: un insegnante può risultare su due corsi nella stessa fascia.
- Nessuna stampa o esportazione: è la fase 4.
- PHPStan e i test su MySQL 8 / PHP 8.2–8.5 girano nella CI solo dopo un push autorizzato.
