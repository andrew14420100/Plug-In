# Istruzioni per Claude Code — Bracelli Club Plug-In

LEGGI questo file, README e **tutta** la cartella `docs/` prima di programmare. Questo progetto contiene dati potenzialmente delicati e verrà usato su un sito reale.

## Missione

Realizzare un **unico plugin WordPress** installabile via ZIP, con nucleo robusto e moduli opzionali indipendenti; dashboard moderna/futuristica a tema chiaro/scuro; funzioni amministrative, gestionali, SEO, automazioni e migrazioni. Plugin per il SOLO centro sportivo Bracelli Club. Nessun servizio a pagamento obbligatorio. Assistente AI interno e software gestionale desktop proprietario saranno fasi future.

## Vincoli e regole operative

- **Mai cambiare produzione** `centrosportivobracelliclub.it` senza una richiesta esplicita che lo autorizzi per quel cambiamento. La connessione a WP e i permessi non costituiscono autorizzazione a intervenire.
- **Mai alterare Club Manager**, tabelle e database; non indovinare il formato. Non installare driver, servizi o connettori sul PC senza permesso. Prima inventario in sola lettura e backup dell'ambiente interessato.
- Repository **PUBBLICO**: niente PII (dati soci, tessere reali, codici fiscali, documenti), token, chiavi di cifratura, `wp-config.php`, file `.env` o dump di produzione; non copiarli nei log né nei test.
- Per gli sviluppi usa **LocalWP su Windows** con dati fittizi e almeno migliaia di soci simulati. Le integrazioni reali richiedono verifiche specifiche e consenso.
- Versioni supportate di WordPress/PHP/Elementor devono essere *rilevate e provate*, non dedotte da dati passati; l'utente ha dichiarato WordPress e PHP aggiornati, hosting TopHost, Elementor/Elementor Pro.
- Usa convenzioni WP, `$wpdb->prefix` per eventuali tabelle, migratori versionati e idempotenti; preserva gli zeri iniziali nei barcode usando stringhe. Il codice fiscale non può essere unico per tutti i tipi di contatto: progettare identificatori stabili di origine + matching multiplo.
- Ruoli/capabilities e controlli di accesso **a livello server**, mai basati solo su UI. I documenti sanitari devono avere controlli di finalità e necessità e non devono essere raggiungibili tramite URL pubblici.
- Sanitizza e valida input, escaped output, query con parametri, REST `permission_callback`, protezione CSRF dove necessaria; i nonces NON sostituiscono l'autorizzazione.
- Mai sincronizzazione WordPress → Club Manager nella fase attuale. Per i record importati conserva provenienza, ID esterno, timestamp e log; gestisci conflitti senza sovrascrivere in silenzio le modifiche manuali.
- Automazioni leggere per TopHost; non compromettere le normali richieste. Misura prestazioni e limiti, non promettere “tempo reale” senza verifica.
- Stati distinti: **tessera**, **iscrizione**, **singolo corso**, **attivazione operativa sul sito**. Non confonderli. Il codice tessera permane nei rinnovi, ma è sostituibile in caso di perdita con storico.
- **Non implementare finti dati/demo in produzione** né schermate dichiarate funzionanti con backend assente. L'AI interna non fa parte della prima release.
- Rispettare gli esiti del questionario in `docs/DECISIONI.md`; in caso di ambiguità proporre una scelta argomentata senza cambiare requisiti già approvati.

## Workflow obbligatorio

1. Leggi i documenti e controlla contenuto effettivo del repository.
2. Per ogni fase definisci un ADR, contratti interni, sicurezza, migrazioni, UI e test con criteri accettazione.
3. Crea un branch di fase (es. `feature/fase-1-core`); **non pushare direttamente su main** se ci sono modifiche di codice. Usa PR per revisione.
4. Implementa con unit test e integration test; build React solo nei percorsi admin dedicati, bundle separati per modulo.
5. Lancia lint, static analysis, test PHP, JS e WP compatibilità su una matrice sensata; produci ZIP plugin con directory interna corretta e file di terze parti necessari già inclusi.
6. Mostra in output: cambiamenti, test realmente eseguiti, risultati, limitazioni e rischi. Non dichiarare verificato ciò che non hai eseguito sul PC dell'utente.
7. Rilascia solo da tag/versione verificati; avvio di migrazioni e ripristino dati richiede verifica specifica. Prevedi aggiornamento automatico opzionale con controlli e fall-back manuale, secondo i requisiti.
8. Non disattivare/eliminare altri plugin prima dell'inventario funzionale e della migrazione collaudata.
9. Mantieni README, changelog e documenti di fase allineati al codice.

## Primo incarico: SOLO FASE 0

Redigi `docs/ADR-001-core.md` (proposta tecnica), controlla repository e struttura prevista, definisci:
- mappa moduli + dipendenze;
- elenco plugin esistenti da inventariare in ambiente di test;
- strategia LocalWP di test con dati simulati;
- proposta schema dati soci/barcode provenienza/storico, gestione permessi, migrazioni;
- proposta sincronizzatore **separato** Windows → WordPress che resta una *integrazione futura da verificare*, non un servizio già realizzato;
- rischi e criteri di accettazione di Fase 1.

**Non sviluppare tutte le fasi in un'unica operazione.** Chiedere approvazione al completamento della Fase 0 prima di passare alla Fase 1.

## Fonti tecniche

- https://developer.wordpress.org/plugins/
- https://developer.wordpress.org/apis/security/nonces/
- https://developer.wordpress.org/reference/hooks/update_plugins_hostname/
- https://developers.elementor.com/
