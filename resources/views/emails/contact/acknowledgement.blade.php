<x-mail::message>
@if($locale === 'en')
# We received your message!

Hi {{ $lead->name }},

Thank you for reaching out to **Lima View Tours**. We have received your message and our team will get back to you within 2 business days.

**Your message:**

{{ $lead->message }}

Meanwhile, feel free to explore our tours at:

<x-mail::button :url="route('tours.index', ['locale' => 'en'])" color="primary">
View our tours
</x-mail::button>
@else
# ¡Recibimos tu mensaje!

Hola {{ $lead->name }},

Gracias por contactarte con **Lima View Tours**. Hemos recibido tu mensaje y nuestro equipo te responderá en un plazo de 2 días hábiles.

**Tu mensaje:**

{{ $lead->message }}

Mientras tanto, puedes explorar nuestros tours en:

<x-mail::button :url="route('tours.index', ['locale' => 'es'])" color="primary">
Ver nuestros tours
</x-mail::button>
@endif

Lima View Tours
</x-mail::message>
