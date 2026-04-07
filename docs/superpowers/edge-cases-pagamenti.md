# Casi limite — Pagamenti e mensilità

Scrivi i tuoi commenti sotto ogni caso dopo "Risposta:".

---

## Mensilità

### 1. Primo pagamento in assoluto
Lo studente paga per la prima volta. Il sistema setta `current_cycle_started_at` a oggi.
Nessun dubbio qui.

Risposta: sono d'accordo.

---

### 2. Pagamento in ritardo
Lo studente dovrebbe pagare il 16 marzo, paga il 25 marzo.
L'ancora NON si sposta: il prossimo pagamento è atteso il 16 aprile.

Risposta: corretto

---

### 3. Studente salta mesi senza essere sospeso
Lo studente non paga per 2 mesi e non è stato messo in "sospeso" dal trainer.
Risulta in ritardo di 2 mensilità. Cosa fa il trainer? Lo sospende retroattivamente? Oppure lo studente deve pagare i mesi arretrati?

Risposta: deve decidere il trainer. Può metterlo in sospeso in qualunque momento.

---

### 4. Sospensione e riattivazione
Lo studente viene sospeso, poi riattivato. Al primo pagamento dopo la riattivazione parte un nuovo ciclo.
Domanda: il salvataggio del ciclo vecchio in `past_cycles` avviene automaticamente al momento della sospensione?

Risposta: Sì, alla sospensione il ciclo corrente si chiude automaticamente e viene archiviato in `past_cycles` (con date inizio/fine e pagamenti associati). Alla riattivazione, il primo nuovo pagamento apre un ciclo fresco.

---

### 5. Studente senza gruppi e senza override tariffa
Lo studente non è assegnato a nessun gruppo e non ha un `monthly_fee_override`.
Quale tariffa si usa? Blocchiamo la registrazione del pagamento? Permettiamo al trainer di inserire l'importo manualmente?

Risposta: se non è assegnato a un gruppo e non ha un monthly_fee_override, al momento della registrazione pagamento il sistema apre direttamente il dialog di importo manuale (come caso #8) e obbliga il trainer a inserire la cifra.

---

### 6. Studente rimosso da un gruppo
Lo studente è in 2 gruppi (Under 16 a €40 e Agonisti a €60). Viene rimosso da Under 16.
La tariffa cambia da €40 a €60 dal momento della rimozione? I pagamenti già registrati restano con il vecchio importo?

Risposta: i pagamenti registrati non cambiano mai. se sono stati registrati non devono cambiare. La tariffa cambia da 40 a 60.

---

### 7. Ancora al 31 del mese
Il primo pagamento è il 31 gennaio. Il prossimo mese è febbraio che ha 28 giorni.
Il pagamento atteso è il 28 febbraio? O il 3 marzo? O il 31 marzo (saltiamo febbraio)?

Risposta: 28 febbraio

---

### 8. Importo diverso dalla tariffa
Lo studente ha una tariffa calcolata di €50 ma il trainer registra un pagamento di €45.
Accettiamo qualsiasi importo? Il sistema segnala la differenza? O forziamo l'importo calcolato?

Risposta: il trainer dovrebbe potere fare con un click il pagamento alla cifra calcolata da gruppi, tariffe e override vari. Se deve immettere una cifra diversa (per motivi di disponibilità, di resto o altra motivazione pratica) tenendo premuto il pulsante può fare comparire un dialog che permette di inserire una cifra diversa dalla cifra standard. In questo modo il sistema può registrare un "debito" o un "credito" da saldare/riscattare al prossimo pagamento.

---

### 9. Pagamento anticipato di più mesi
Lo studente vuole pagare 3 mesi in anticipo.
Il trainer registra 3 pagamenti separati (uno per mese)? Oppure 1 pagamento con importo triplo? Nel secondo caso, come contiamo i mesi coperti?

Risposta: il trainer deve potere registrare il pagamento unico per tracciare la transazione (1 pagamento con importo triplo), il sistema calcola i mesi che verranno pagati.

---

## Iscrizione

### 10. Iscrizione scaduta ma lo studente continua a pagare le mensilità
L'iscrizione è scaduta. Lo studente paga comunque la mensilità.
Blocchiamo la registrazione della mensilità? Mostriamo un avviso? Oppure le due cose sono completamente indipendenti?

Risposta: Mostriamo avviso ma permettiamo la registrazione della mensilità

---

### 11. Rinnovo iscrizione anticipato
Lo studente rinnova l'iscrizione 2 mesi prima della scadenza.
La nuova scadenza parte da oggi (perdendo i 2 mesi) o dalla vecchia scadenza (estendendo)?

Risposta: dalla veccha scadenza estendendo

---

### 12. Iscrizione scade durante la sospensione
Lo studente è sospeso. Nel frattempo l'iscrizione scade.
Quando torna deve pagare sia l'iscrizione che la mensilità.
Nessun dubbio qui, solo da gestire nella UI.

Risposta: si, sono d'accordo

---

### 13. Cambio durata default iscrizione
Il tenant cambia la durata di default da 12 a 6 mesi.
Le iscrizioni già registrate mantengono la loro scadenza originale? Solo le nuove usano il nuovo default?

Risposta: non lo so ancora. Per adesso facciamo che solo le nuove usano il nuovo default.

---

## Altri casi?

Aggiungi qui sotto eventuali casi che non ho considerato:
