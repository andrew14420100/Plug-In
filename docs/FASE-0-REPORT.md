# Rapporto Fase 0

Formato da `docs/TEST.md` ("Riepilogo output di Claude per ogni fase").

```text
FASE: 0 — Inventario e ADR (solo documentazione)
Commit/branch: claude/plug-in-fase-0-dy10dq (PR verso main)
Moduli realmente implementati: nessuno (Fase 0 non prevede codice)
```

## Deliverable

| Deliverable ROADMAP Fase 0 | Documento | Stato |
| --- | --- | --- |
| ADR con pro/contro, schema preliminare, matrice moduli/dipendenze, rischi TopHost | `docs/ADR-001-core.md` | ✅ redatto, da approvare |
| Verifica repository | ADR §1.1 | ✅ eseguita |
| Verifica versioni WP/PHP ed Elementor del sito | `docs/FASE-0-INVENTARIO.md` §1 | ⏳ **non verificabile da qui**: procedura in sola lettura pronta |
| Inventario plugin e piano di sostituzione | `docs/FASE-0-INVENTARIO.md` §2 | ⏳ elenco da confermare + schede e comandi pronti; nessun plugin toccato |
| Piano LocalWP e fixture sintetiche 5.000+ soci | `docs/FASE-0-LOCALWP.md` | ✅ piano (generatore in Fase 2) |
| Piano migrazioni idempotenti e permessi | ADR §6, §7 | ✅ proposta |
| Ricognizione scritta Club Manager | `docs/FASE-0-INVENTARIO.md` §4 | ⏳ questionario/checklist pronti; nessun accesso eseguito |
| Proposta sincronizzatore separato Windows → WP | ADR §10 | ✅ proposta, **non realizzato** |
| Rischi e criteri di accettazione Fase 1 | ADR §12, §13 | ✅ |

## Verifiche realmente eseguite

| Verifica | Comando/metodo | Esito |
| --- | --- | --- |
| Contenuto repository e storia | `git log --stat`, `find` | 9 commit, solo documentazione; nessun codice, CI, tag o licenza |
| Lettura integrale documenti | `CLAUDE.md`, `README.md`, `.gitignore`, 6 file `docs/` | Completata; requisiti riportati nell'ADR con riferimenti alle domande |
| Versioni ufficiali correnti | `api.wordpress.org/core/version-check/1.7`, `api.wordpress.org/plugins/info/1.2`, `php.net/releases` | WP 7.1.3; PHP supportati 8.2–8.5 (ultima 8.5.11); Elementor 4.3.5; Yoast 28.7; CF7 6.2.2; Amelia 2.4.11 |
| Scansione PII/segreti nel repository | `grep` con pattern per codice fiscale, email, token/chiavi, IBAN, cellulari | Nessuna corrispondenza |
| Collegamenti interni tra documenti | script di controllo dei link relativi Markdown | Tutti i riferimenti risolti |

## Verifiche NON eseguite e motivo

| Verifica | Motivo |
| --- | --- |
| Versioni WP/PHP/DB/server del sito Bracelli | Vietato intervenire in produzione; nessuna copia del sito disponibile in questa sessione. Serve il rilievo Site Health (sola lettura) da parte dell'utente |
| Inventario plugin attivi, template Elementor, shortcode | Come sopra; richiede copia LocalWP o elenco dalla schermata Plugin |
| Limiti piano TopHost (cron, memoria, mail, spazio, fuori webroot) | Richiede accesso al pannello/sito |
| Club Manager (prodotto, versione, formato archivio, export) | Software sul PC dell'utente, non accessibile; ogni verifica richiede autorizzazione |
| Simbologia barcode (Q265) | Serve una tessera reale e un lettore; nessuna immagine o codice reale va nel repository |
| Prove LocalWP | LocalWP è sul PC Windows dell'utente; questa sessione è un container Linux senza WordPress |

## Compatibilità verificata

Nessuna. Le versioni sopra sono quelle pubblicate ufficialmente, **non** quelle del sito; i minimi PHP 8.2 / WP 6.9 sono una proposta (ADR D2).

## Benchmark

Nessuno (non c'è codice). Il piano di misura è in `FASE-0-LOCALWP.md` §4.

## File ZIP e checksum

Nessuno (Fase 0 non produce pacchetti).

## Decisioni aperte che restano tali

Q208 liste d'attesa, Q252/Q291–293 export/versione/formato Club Manager, Q265 simbologia barcode, Q319 chiave di correlazione, Q328 pagamenti da Club Manager, Q340 metodo di attivazione multipla, Q168–169 Elementor Pro e ordine migrazioni, Q214/218/286 permessi sconti segreteria. Nessuna è stata decisa in questa fase.

**Nuovo punto segnalato:** licenza del repository (assente; ADR §12).

## Rischi residui principali

Vedi ADR §12. I più rilevanti per avviare la Fase 1: versioni PHP/WP del sito ignote (possibile revisione dei minimi) e limiti TopHost non misurati.

## Cosa serve per l'approvazione

1. Approvazione (o correzioni) di `docs/ADR-001-core.md`, in particolare D1–D14 e i criteri di accettazione Fase 1 (§13).
2. Facoltativo ma consigliato prima della Fase 1: rilievo Site Health del sito (`FASE-0-INVENTARIO.md` §1) e versione PHP di TopHost.
3. Indicazione se includere l'**updater GitHub** già in Fase 1 o rinviarlo (ADR §4.3, §9).
4. Decisione sulla licenza (può attendere la prima release).
5. Autorizzazione separata, quando l'utente lo ritiene, per la ricognizione Club Manager sul PC (`FASE-0-INVENTARIO.md` §4).
