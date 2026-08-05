# NDI access recheck — 2026-08-05

Owner: Mister Red
Environment: isolated EasyTire staging

## Support answer

NDI confirmed that the API account is active, that no IP pre-approval is
required, and that their supplied credential pair works internally. They also
provided an exact read-only A2 Inquiry example using `DocumentID=A2`,
`Variant=5`, `EANUCCArticleID`, and the established XML namespace.

No credentials from the answer are stored in this repository.

## Independent live verification

One A2 Inquiry was sent from the EasyTire staging server using values extracted
directly from NDI's PDF. No C1 order or other state-changing operation was
called.

Result:

- HTTP status: `200`;
- response: `quote_A2` in namespace `http://www.reifen.net`;
- NDI `ErrorCode`: `0`;
- returned identifier matched the requested test article;
- requested quantity: `4`;
- available quantity: `4`;
- delivery date: `2026-08-05`.

The `PartyID` and agency code already stored on staging matched the PDF. The
stored username and password did not match the newly supplied pair. This is the
confirmed cause of the earlier HTTP `401`; it was not an IP restriction or a
failure of the NDI endpoint.

## Application correction

The A2 serializer now follows the confirmed element order and identifier shape:

```text
DocumentID -> Variant -> CustomerReference -> BuyerParty -> OrderLine
```

`DocumentID` is constrained to `A2`, `Variant` defaults to `5`, and outgoing
article identifiers use `EANUCCArticleID`. The quote parser accepts both
`EANUCCArticleID` from the new confirmed example and
`ManufacturersArticleID` from NDI's earlier supplied fixture.

Staging credentials must be updated only in the protected environment file.
The read-only Inquiry switch may be enabled after deployment. C1 ordering and
all other state-changing API switches remain disabled.

## Decision

NDI Inquiry access is technically operational. The earlier access incident is
resolved once staging uses the newly supplied credentials. This confirms the
real-time availability and delivery-date part of the planned checkout flow; it
does not change the existing rule that catalogue data and prices come from NDI
files.
