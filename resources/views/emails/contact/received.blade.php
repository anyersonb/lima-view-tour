<x-mail::message>
# Nuevo mensaje de contacto

Tienes un nuevo mensaje enviado a través del formulario de contacto.

**Nombre:** {{ $lead->name }} {{ $lead->lastname }}
**Email:** {{ $lead->email }}
**Teléfono:** {{ $lead->phone ?? '—' }}
**Idioma:** {{ strtoupper($lead->locale) }}
**IP:** {{ $lead->ip ?? '—' }}
**Fecha:** {{ $lead->created_at->format('d/m/Y H:i') }}

---

**Mensaje:**

{{ $lead->message }}

---

<x-mail::button :url="config('app.url') . '/admin/contact-leads'" color="primary">
Ver en el panel de administración
</x-mail::button>

Lima View Tours
</x-mail::message>
