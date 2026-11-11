{{-- Correo de PlanPorVencer. Lleva su propio encabezado y pie: el de Laravel usa APP_NAME. --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="config('app.url')">
Rankit
</x-mail::header>
</x-slot:header>

# Hola, {{ $nombre }}

@foreach ($lineas as $linea)
{{ $linea }}

@endforeach
<x-mail::button :url="$url">
{{ $boton }}
</x-mail::button>

@if ($nota)
{{ $nota }}

@endif
— Rankit

<x-slot:subcopy>
<x-mail::subcopy>
Si el botón no abre, copia esta liga en tu navegador: [{{ $url }}]({{ $url }})
</x-mail::subcopy>
</x-slot:subcopy>

<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} Rankit · rankit.pro
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
