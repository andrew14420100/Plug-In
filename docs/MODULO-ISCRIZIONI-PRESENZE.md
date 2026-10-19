# Modulo — Iscrizioni ai corsi e presenze dei partecipanti

**Stato:** requisiti funzionali da implementare e testare; questo documento non dichiara il modulo già funzionante.
**Ambito:** area riservata Bracelli Club, con accesso dalla dashboard della segreteria.
**Esclusioni esplicite:** tessere, codici a barre, Club Manager e presenze lavorative degli insegnanti non fanno parte di questo modulo.

## 1. Obiettivo e separazione dei registri

Gestire il ciclo di vita delle iscrizioni ai corsi e registrare la presenza effettiva dei partecipanti alle singole lezioni. Il registro dei partecipanti è distinto dal registro delle presenze lavorative degli istruttori: i due sistemi non devono condividere stati o dedurre automaticamente l'uno dall'altro.

## 2. Gestione iscrizioni

- Creare un'iscrizione a un corso esistente, selezionando partecipante, corso, periodo di validità ed eventuali note operative.
- Consultare, modificare, sospendere, concludere o annullare un'iscrizione secondo i permessi assegnati.
- Mostrare elenco iscritti per corso con ricerca, filtri, ordinamento e paginazione.
- Impedire duplicati incompatibili e segnalare sovrapposizioni di iscrizione secondo regole configurabili.
- Gestire capienza massima e disponibilità dei posti se configurate per il corso; la lista d'attesa resta opzionale e non va attivata finché la relativa regola non è stata approvata.
- Conservare lo storico delle variazioni, senza cancellare le presenze storiche quando un'iscrizione termina.
- Non creare automaticamente account WordPress per i partecipanti.

## 3. Registro presenze delle lezioni

- Generare/consultare le singole sessioni in base al calendario del corso, incluse eccezioni, recuperi e annullamenti.
- Per ogni sessione mostrare l'elenco degli iscritti pertinenti alla data.
- Stati iniziali: **Presente**, **Assente**, **Assenza giustificata**. L'eventuale stato **In ritardo** può essere un attributo separato e non deve sostituire lo stato di presenza.
- Salvare le modifiche sul server con conferma chiara dell'esito; evitare di mostrare un salvataggio riuscito prima della risposta del server.
- Consentire correzioni soltanto agli utenti autorizzati, registrando autore, data, stato precedente, nuovo stato e motivo quando richiesto.
- Definire una finestra configurabile per la correzione retroattiva; amministratori autorizzati possono gestire le eccezioni, sempre con audit.
- Non marcare automaticamente tutti presenti o assenti in assenza di una scelta esplicita.

## 4. Ruoli e permessi

- **Amministratore/ruolo autorizzato:** configurazione completa e consultazione dei report consentiti.
- **Segreteria:** gestione iscrizioni, consultazione corsi e registrazione/correzione presenze secondo capability assegnate.
- **Istruttore:** visualizzazione dei soli corsi e sessioni assegnati e registrazione presenze per tali sessioni; niente accesso implicito ai dati amministrativi o economici.
- Ogni operazione deve essere verificata lato server tramite capability e controllo del corso/sessione. Nascondere un pulsante nell'interfaccia non è una misura di sicurezza.
- Audit degli inserimenti, modifiche, annullamenti ed esportazioni; minimizzare i dati personali nei log.

## 5. Storico, statistiche ed esportazione

- Ricercare presenze per partecipante, corso, istruttore assegnato, sessione e intervallo di date, nei limiti dei permessi.
- Calcolare conteggi e percentuali in base a regole esplicite e documentate; mostrare il denominatore e distinguere assenze giustificate dalle assenze non giustificate.
- Report giornalieri, mensili e per periodo; esportazione CSV solo per utenti autorizzati, con campi minimizzati e registrazione dell'operazione.
- Nessun dato dimostrativo o contatore inventato in produzione: mostrare uno stato vuoto quando non esistono registrazioni.

## 6. Modello dati e integrità

- Relazioni tra corsi, sessioni, iscrizioni e registrazioni di presenza con chiavi interne stabili.
- Vincolo per impedire più registrazioni di presenza per lo stesso partecipante e la stessa sessione, salvo una motivata decisione tecnica diversa.
- Stati dell'iscrizione separati dallo stato di presenza della singola sessione.
- Migrazioni versionate, idempotenti e non distruttive; le disattivazioni del modulo non devono cancellare dati.
- Usare transazioni e controlli di concorrenza dove supportati; gestire conflitti se due operatori modificano la stessa registrazione.

## 7. UX

- Area segreteria con viste separate: **Iscrizioni**, **Registro lezione**, **Storico e statistiche**.
- Flusso rapido per selezionare corso e sessione, filtrare la lista e registrare le presenze senza confonderle con quelle del personale.
- Interfaccia responsive, accessibile, leggibile e coerente con il tema Bracelli Club.
- Stati caricamento, errore, nessun risultato e salvataggio devono essere chiari; nessun pulsante privo di azione reale.

## 8. Criteri di accettazione

1. Un utente autorizzato può iscrivere un partecipante a un corso e visualizzare l'iscrizione nell'elenco corretto.
2. Una sessione mostra gli iscritti pertinenti alla data e permette di registrare i tre stati previsti.
3. Le presenze restano associate alla singola sessione e non vengono sovrascritte da quelle di un'altra settimana.
4. Un istruttore non può leggere o modificare corsi non assegnati chiamando direttamente API o endpoint.
5. Un utente senza capability non può modificare iscrizioni, presenze o esportare dati.
6. Ogni correzione è tracciata e lo storico rimane coerente dopo la chiusura dell'iscrizione.
7. Duplicati, richieste concorrenti, sessioni annullate e date fuori dal periodo di iscrizione sono gestiti e testati.
8. Test automatici e manuali verificano sicurezza, accessibilità essenziale, responsive UI, errori di rete e assenza di dati fittizi.
9. Il modulo è dichiarato completato solo dopo test su LocalWP, packaging ZIP e verifica reale del flusso end-to-end.
