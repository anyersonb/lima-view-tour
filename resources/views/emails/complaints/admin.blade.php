<x-mail::message>
# Nueva hoja en el Libro de Reclamaciones

**{{ $complaint->code }}** — {{ $complaint->type === 'complaint' ? 'Queja' : 'Reclamo' }}

**Plazo legal de respuesta:** {{ $complaint->response_due_at?->format('d/m/Y') }} (15 días hábiles). Respóndela desde el panel: Libro de Reclamaciones.

- **Consumidor:** {{ $complaint->full_name }}
- **Documento:** {{ strtoupper($complaint->doc_type) }} {{ $complaint->doc_number }}
- **Correo:** {{ $complaint->email }}
- **Teléfono:** {{ $complaint->phone }}
@if ($complaint->booking_reference)
- **Reserva:** {{ $complaint->booking_reference }}
@endif
- **Idioma:** {{ strtoupper($complaint->locale) }}

**Detalle:**

{{ $complaint->detail }}

**Pedido del consumidor:**

{{ $complaint->consumer_request }}

<x-mail::button :url="url('/admin/complaints/'.$complaint->id.'/edit')" color="primary">
Abrir en el panel
</x-mail::button>

Lima View Tours
</x-mail::message>
