# Oppfølging: API-kontoen returnerer HTTP 401

Hei,

vi har nå konfigurert opplysningene vi fikk tilsendt og testet en vanlig, skrivebeskyttet `inquiry_A2` mot:

`https://salesapi.ndias.com/EndPoint`

Vi brukte testkunden `1220000`, testproduktet `4765205551649102` og antall `2`, i tråd med oppsettet deres. Forespørselen blir imidlertid avvist med HTTP `401` allerede ved Basic Authentication. Den kommer derfor ikke så langt som til validering av PartyID eller produkt.

Kan dere kontrollere om den tilsendte brukeren er aktivert for Sales API-endepunktet, og bekrefte at brukernavn og passord er de riktige for Basic Authentication der? Hvis dette er innlogging til en annen NDI-tjeneste, trenger vi den separate API-kontoen for Sales API.

Vi har kun kjørt Inquiry. Ingen ordre eller andre skrivende API-kall er sendt.

Takk!
