<x-layouts.storefront>
<form method="post" class="mx-auto max-w-xl space-y-4 px-5 py-12">
@csrf
<input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
<input name="website" class="hidden" aria-hidden="true" tabindex="-1" autocomplete="off">
<h1 class="text-3xl font-black">{{ __('commerce.checkout') }}</h1><p>{{ __('commerce.pickup') }}</p>
@if($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
@foreach(['name','email','phone','registration','note'] as $field)
<label class="block" for="checkout-{{ $field }}">{{ __('commerce.'.$field) }}</label>
@if($field==='note')<textarea id="checkout-note" class="min-h-11 w-full" name="note">{{ old('note') }}</textarea>
@else<input id="checkout-{{ $field }}" class="min-h-11 w-full" name="{{ $field }}" @if($field==='email') type="email" autocomplete="email" @elseif($field==='name') autocomplete="name" @elseif($field==='phone') type="tel" autocomplete="tel" @endif @required(in_array($field,['name','email','phone'])) value="{{ old($field) }}">@endif
@endforeach
<label><input required type="checkbox" name="consent" value="1"> {{ __('commerce.consent') }}</label><label><input required type="checkbox" name="terms" value="1"> {{ __('commerce.terms') }}</label><button class="min-h-11 px-5" type="submit">{{ __('commerce.submit') }}</button>
</form>
</x-layouts.storefront>
