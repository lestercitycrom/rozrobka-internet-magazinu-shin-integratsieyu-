# Oppfølging om produktbilder og mediekatalog

Hei,

Vi har nå gjennomført en ny, full teknisk kontroll av hele datapakken og bildeimporten vår.

- Dekkfilen inneholder 1 082 varer. 621 varer har en gyldig bilde-ID, mens 461 varer har `PlainUrl` med tom `id=`.
- Felgfilen inneholder 502 varer, men har ikke noe felt for bilde-ID, bilde-URL eller annen mediekobling.
- Alle 621 bildekoblinger er validert i bildeimporten. De tilhørende bildene er lastet ned uten feil og utgjør 34 unike PNG-filer.
- De øvrige XML-, PDF- og API-filene i pakken inneholder ingen produktbilder eller medieendepunkt.

Vi fant samtidig to forhold som vi gjerne vil avklare med dere:

- 346 av de 461 dekkene uten bilde-ID har samme fabrikat og nøyaktig samme mønsternavn som varer som har én entydig bilde-ID. Vi ønsker ikke å arve dette bildet automatisk uten bekreftelse fra dere.
- EDIWheel beskriver en egen Marketing Catalogue (MktCat) med bilder per dekkdesign/mønster. Denne katalogen, koblingsnøkkelen og tilgangen fulgte ikke med datapakken vi mottok.

Vi kan også se at en av NDI-konsernets offisielle danske nettbutikksider har bilde for minst ett eksakt varenummer fra felgfilen, selv om bildekoblingen mangler i eksporten: `730009519511445442`, Mega Wheels Pyxis 9,5×19. Vi ønsker å bruke en støttet datafeed, ikke hente bilder direkte fra nettbutikken.

Kan dere hjelpe oss med følgende?

- Tilbyr NDI Norge EDIWheel MktCat eller en annen offisiell bilde- eller mediefeed? Send gjerne endepunkt eller SFTP-sti, autentisering, skjema/versjon og et eksempel.
- Kan dere levere en komplett mediekobling for de 461 dekkene og 502 felgene som mangler bilde-ID i dagens filer?
- Hvilken stabil nøkkel skal brukes for å koble dekkbildene til `Export.xlsx`: varenummer, EAN, `Supplier_itemID`, design-ID eller mønster-ID? Vi ser at `Supplier_itemID` kan deles av spesialvarianter, blant annet `13143NXK` (`used 8 mm`) og `13885NXK` (`avm.`). Er feltet derfor uegnet som automatisk bildekobling?
- Finnes det en tilsvarende offisiell bildefeed for felgene fra Mega Wheels og Sento, med kobling via `Varenummer` eller en entydig modell- og finish-/fargenøkkel?
- Betyr tom `PlainUrl id=` at varen bevisst ikke har bilde, eller mangler bilde-ID-en i denne eksporten?
- Kan samme godkjente bilde brukes på alle dimensjoner og varianter av samme fabrikat og mønster, eller bare på varenumre med eksplisitt bilde-ID?
- For dagens `Imagev2.aspx`-lenker: støttes HTTPS og automatisert bulk-nedlasting offisielt, og gjelder det autentisering eller rate limits? Er bildet bak en UUID uforanderlig, eller kan det endres, utløpe eller trekkes tilbake? Finnes ETag, Last-Modified eller en annen oppdateringsmekanisme?
- Kan dere skriftlig bekrefte at NDI har rett til å gi EasyTire tillatelse til å laste ned, mellomlagre, bruke CDN/sikkerhetskopi, skalere, beskjære, konvertere format og vise produktbilder og merkevarelogoer kommersielt? Vi trenger også eventuelle regler for kreditering, territorium, varighet, lagringstid og sletting.
- Hvordan leveres full- og deltaoppdateringer, hvor ofte oppdateres de, og finnes det endrings- eller slettemarkører? Oppgi gjerne faste filnavn og SFTP-mappe slik at importen kan automatiseres.

Da kan vi koble bildene korrekt og holde dem oppdatert uten gjetting eller scraping.

På forhånd takk.
