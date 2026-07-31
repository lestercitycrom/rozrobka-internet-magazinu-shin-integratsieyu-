# Admin order filter integration

Use `AdminOrderFilters::from($request->query())` at the start of the order-list action. Convert its enum properties to `->value` only when applying Eloquent conditions. Map `InvalidArgumentException` to a normal validation error; never pass arbitrary status strings to the query.
