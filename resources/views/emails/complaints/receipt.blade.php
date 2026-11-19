@php
    $t = fn (string $key, array $r = []) => __('complaints.'.$key, $r, $locale);
    $docLabel = $t('doc_'.$complaint->doc_type);
    $typeLabel = $t($complaint->type === 'complaint' ? 'type_complaint' : 'type_claim');
    $goodLabel = $t($complaint->good_type === 'product' ? 'good_product' : 'good_service');
    $amount = $complaint->claimed_amount !== null
        ? ($complaint->currency === 'USD' ? 'US$ ' : 'S/ ').number_format((float) $complaint->claimed_amount, 2)
        : null;
    $legal = \App\Support\LegalInfo::lines();
@endphp
<x-mail::message>
# {{ $t('mail_title') }}

{{ $t('mail_hello', ['name' => $complaint->first_name]) }}

{{ $t('mail_intro') }}

**{{ $t('number') }}: {{ $complaint->code }}**
{{ $t('date') }}: {{ $complaint->created_at->format('d/m/Y H:i') }}

{{ $t('mail_deadline', ['due' => $complaint->response_due_at?->format('d/m/Y')]) }}

<x-mail::panel>
**{{ $t('mail_provider') }}**
@forelse ($legal as $label => $value)
{{ $label }}: {{ $value }}
@empty
Lima View Tours
@endforelse
</x-mail::panel>

**{{ $t('mail_consumer') }}**

- {{ $complaint->full_name }}
- {{ $docLabel }}: {{ $complaint->doc_number }}
- {{ $t('address') }}: {{ $complaint->address }}
- {{ $t('phone') }}: {{ $complaint->phone }}
- {{ $t('email') }}: {{ $complaint->email }}
@if ($complaint->is_minor)
- {{ $t('guardian_name') }}: {{ $complaint->guardian_name }}
@endif

**{{ $t('mail_good') }}**

- {{ $goodLabel }}: {{ $complaint->good_description }}
@if ($complaint->booking_reference)
- {{ $t('booking_reference') }}: {{ $complaint->booking_reference }}
@endif
@if ($amount)
- {{ $t('claimed_amount') }}: {{ $amount }}
@endif

**{{ $t('mail_claim') }}: {{ $typeLabel }}**

**{{ $t('detail') }}:**

{{ $complaint->detail }}

**{{ $t('consumer_request') }}:**

{{ $complaint->consumer_request }}

---

{{ $t('mail_footer') }}

Lima View Tours
</x-mail::message>
