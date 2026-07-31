# Emne: Avklaringer for EasyTire-kontoen og videre API-testing

Hei,

Takk for vedleggene. De inneholdt informasjonen vi manglet om XML-formatet.

Vi har nå sendt en `inquiry_A2` med namespace og struktur fra eksemplet deres. Forespørselen ble behandlet korrekt og returnerte `quote_A2` med `ErrorCode 0`, tilgjengelig antall og leveringsdato. Selve Inquiry-integrasjonen fungerer dermed som forventet.

Da vi brukte EasyTires kundenummer `[sendt separat]` i `BuyerParty/PartyID`, fikk vi derimot `ErrorCode 915`. Kan dere bekrefte hvilket `PartyID` og hvilken `AgencyCode` som er knyttet til API-brukeren `[sendt separat]`? Er det oppgitte nummeret kun kundenummeret i Norge, mens API-et forventer en annen intern kunde-ID?

Før vi tester hele ordreprosessen, ønsker vi også å avklare følgende:

- Hvilket endpoint skal de utstedte påloggingsopplysningene brukes mot? De fungerer på `https://salesapi.ndias.com/EndPoint`, mens UAT-adressen i dokumentasjonen returnerer `401 Unauthorized`.
- Returnerer `quote_A2` pris for den norske kontoen, eller skal `Net_Price` og `Gross_Price` fra datafilene brukes som gjeldende priser? Hvor ofte oppdateres prisfilene?
- Finnes det en funksjon for å reservere varer før betalingen fullføres?
- Kan en opprettet ordre kanselleres via API-et? Hvis ja, hvilken melding eller metode skal brukes?
- Hvilken melding anbefaler dere for ordrestatus og sporing: `order_status_request`, `NDIStatus`, `GetOrderDespatch` eller en annen løsning?
- Anbefaler dere A2 eller C1 for en ny integrasjon? Vi ser at C1 støtter en alternativ leveringsadresse.
- Finnes det begrensninger på antall forespørsler eller ordrelinjer, og hvilke timeout- og retry-verdier anbefaler dere?

Før vi sender den første ordren, ønsker vi en uttrykkelig bekreftelse på at en ordre sendt med dagens bruker og endpoint blir stoppet og ikke går til faktisk behandling. Send oss gjerne et trygt testprodukt og et anbefalt XML-eksempel for denne testen.

Når vi har riktig `PartyID` og bekreftelse på en sikker testordre, kan vi teste hele flyten fra Inquiry til ordrestatus.

Takk på forhånd.

Med vennlig hilsen  
Maksym  
EasyTire
