# Storefront interaction contract

Issue: #29  
Applies to: public Bokmål storefront and hidden Russian QA route

## Hover

- Primary buttons darken their filled surface; outline buttons use a neutral surface/border change; ghost/glass buttons strengthen their existing inverse surface.
- Text links stay flat and use colour/underline only. They never receive a raised-card shadow.
- Cards may use a restrained border/elevation change, but no component translates, changes border width or changes measured dimensions.
- Red remains the action accent. Hover is not represented by a generic red frame.
- Current/selected state is persistent and must remain distinguishable from the transient hover state.
- Active controls deepen their existing semantic surface without moving. Disabled controls use reduced opacity and a not-allowed cursor without inheriting hover elevation.

## Keyboard focus

- Public controls use one three-pixel `focus-visible` outline with a three-pixel offset and no stacked shadow ring.
- Light surfaces use the strong brand focus token; dark surfaces use the inverse white focus token.
- Pointer focus does not inherit keyboard-only treatment. Error/selected styles remain independent from focus.
- Real Tab order, submenu `focus-within`, mobile-menu focus handoff and the skip link remain unchanged.

## Shared shell

- The sticky header is fully opaque before and after scrolling, including no-backdrop-filter and reduced-motion cases.
- Standalone mobile actions follow the 44px target convention; inline prose and legal links are not artificially inflated.
- With JavaScript disabled all footer links are visible. With JavaScript enabled at 640px and below, accessible section toggles collapse repeated navigation; every destination remains reachable.
