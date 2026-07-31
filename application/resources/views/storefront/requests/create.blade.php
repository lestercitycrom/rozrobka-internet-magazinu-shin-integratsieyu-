@php($prefix=app()->getLocale()==='ru'?'storefront.preview.':'storefront.')
<x-layouts.storefront>
<form method="post" action="{{ route($prefix.'requests.store',$slug) }}" class="mx-auto max-w-2xl space-y-4 px-5 py-12">
@csrf
<input name="website" class="hidden" aria-hidden="true" tabindex="-1" autocomplete="off">
<h1 class="text-3xl font-black">{{ __('booking.title') }}</h1><p>{{ __('booking.intro') }}</p>
@if($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
<input type="hidden" name="service_slug" value="{{ $slug }}">
@foreach(['fulfilment','preferred_date','preferred_window','name','email','phone','address','note'] as $field)
<label class="block">{{ __('booking.'.($field==='preferred_date'?'date':($field==='preferred_window'?'window':$field))) }}
@if($field==='fulfilment')<select class="mt-1 min-h-11" name="fulfilment"><option value="workshop">{{ __('booking.workshop') }}</option><option value="mobile">{{ __('booking.mobile') }}</option></select>
@elseif($field==='note')<textarea class="mt-1 min-h-11" name="note"></textarea>
@else<input class="mt-1 min-h-11" name="{{ $field }}" @if($field==='email') type="email" @elseif($field==='preferred_date') type="date" @endif @required(in_array($field,['name','email','phone']))>
@endif</label>@endforeach
<label><input required name="consent" type="checkbox" value="1"> {{ __('booking.consent') }}</label>
<button class="min-h-11 px-5" type="submit">{{ __('booking.submit') }}</button>
</form>
</x-layouts.storefront>
