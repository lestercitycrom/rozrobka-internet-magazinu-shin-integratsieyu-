# Emne: Tekniske spørsmål om NDI Sales API og testmiljøet

Hei,

Takk for tilgangen og dokumentasjonen. Vi har nå begynt å teste integrasjonen mot NDI Sales API.

Innloggingen fungerer, og vi får tilgang til endpointet, WSDL og XSD-skjemaene. Problemet oppstår når vi sender en `inquiry_A2` bygget etter skjemaet til:

`https://salesapi.ndias.com/EndPoint`

Serveren svarer med HTTP 200, men innholdet er:

```text
There is an error in XML document (0, 0).
```

Den samme feilen kommer når forespørselen sendes via deres egen `servicetest`-side. Vi kommer derfor ikke videre til en vanlig `quote_A2` med pris, lagerstatus og leveringstid.

Kan dere undersøke om dette skyldes en feil i testmiljøet eller manglende aktivering på testkontoen?

For at vi skal kunne teste og implementere integrasjonen riktig, trenger vi også følgende avklaringer:

- Kan dere sende oss en komplett og fungerende `inquiry_A2` for denne testkontoen, sammen med forventet `quote_A2`?
- Kan dere oppgi ett gyldig testprodukt, helst både NDI-artikkelnummer og EAN?
- Hvilke verdier skal brukes i `BuyerParty/PartyID` og `AgencyCode` for kontoen vår?
- Er XML direkte mot `/EndPoint` over HTTPS den anbefalte løsningen for en ny integrasjon? Testsidens SOAP C1-metoder er merket som foreldet.
- WSDL-en oppgir en HTTP-adresse for `Service.svc`. Kan dere bekrefte at all kommunikasjon i produksjon skal gå over HTTPS, og hvilket endpoint som er det offisielle?
- Returnerer `quote_A2` alltid gjeldende nettopris, tilgjengelig antall og forventet leveringsdato?
- Finnes det en egen funksjon for å reservere varer før betaling?
- Kan en ordre kanselleres via API-et? Hvis ja, hvilken melding eller metode skal brukes?
- Hvilken metode anbefaler dere for å hente ordrestatus: `NDIStatus`, `GetOrderDespatch` eller en annen melding?
- Finnes det begrensninger på antall forespørsler eller antall ordrelinjer per `inquiry` og ordre?
- Hvilke timeout- og retry-innstillinger anbefaler dere?

Før vi tester selve ordreopprettelsen, ønsker vi også en bekreftelse på at ordre i testmiljøet blir stoppet og ikke sendes til faktisk behandling. Send oss gjerne et eksempel på en testordre og tilhørende svar/statusmelding.

Når vi har et fungerende inquiry/quote-eksempel, tester vi hele flyten og gir raskt beskjed dersom vi finner noe mer.

Takk på forhånd.

Med vennlig hilsen  
Maksym  
EasyTire
