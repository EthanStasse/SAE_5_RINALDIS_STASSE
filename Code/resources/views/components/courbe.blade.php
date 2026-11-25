@props(['courbe', 'titre', 'unite'])

@use('App\Support\Courbe')

@php
    $bas = Courbe::HAUTEUR - Courbe::MARGE_BAS;
    $droite = Courbe::LARGEUR - Courbe::MARGE_DROITE;
@endphp

<svg viewBox="0 0 {{ Courbe::LARGEUR }} {{ Courbe::HAUTEUR }}" class="h-auto w-full" role="img" aria-label="{{ $titre }} sur les dernières 24 heures">
    {{-- Graduations verticales et lignes de fond --}}
    @foreach ($courbe->graduationsY() as $graduation)
        <line x1="{{ Courbe::MARGE_GAUCHE }}" x2="{{ $droite }}" y1="{{ $graduation['y'] }}" y2="{{ $graduation['y'] }}" class="stroke-stone-200 dark:stroke-stone-700" stroke-width="1" />
        <text x="{{ Courbe::MARGE_GAUCHE - 6 }}" y="{{ $graduation['y'] + 4 }}" text-anchor="end" class="fill-stone-500 dark:fill-stone-400 text-[11px]">{{ $graduation['libelle'] }}</text>
    @endforeach

    {{-- Graduations horizontales (heure de Paris) --}}
    @foreach ($courbe->graduationsX() as $graduation)
        <line x1="{{ $graduation['x'] }}" x2="{{ $graduation['x'] }}" y1="{{ Courbe::MARGE_HAUT }}" y2="{{ $bas }}" class="stroke-stone-100 dark:stroke-stone-800" stroke-width="1" />
        <text x="{{ $graduation['x'] }}" y="{{ Courbe::HAUTEUR - 8 }}" text-anchor="middle" class="fill-stone-500 dark:fill-stone-400 text-[11px]">{{ $graduation['libelle'] }}</text>
    @endforeach

    {{-- Seuils --}}
    @foreach ($courbe->lignesSeuils() as $ligne)
        <line x1="{{ Courbe::MARGE_GAUCHE }}" x2="{{ $droite }}" y1="{{ $ligne['y'] }}" y2="{{ $ligne['y'] }}" class="stroke-red-400" stroke-width="1.5" stroke-dasharray="6 4" data-seuil="{{ $ligne['libelle'] }}" />
        <text x="{{ $droite - 2 }}" y="{{ $ligne['y'] - 4 }}" text-anchor="end" class="fill-red-600 text-[11px]">seuil {{ $ligne['libelle'] }}</text>
    @endforeach

    {{-- Courbe : un morceau par période sans trou de mesure --}}
    @foreach ($courbe->segments() as $segment)
        @if (count($segment) === 1)
            <circle cx="{{ $segment[0]['x'] }}" cy="{{ $segment[0]['y'] }}" r="2.5" class="fill-green-700 dark:fill-green-400" />
        @else
            <polyline points="{{ collect($segment)->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ') }}" fill="none" class="stroke-green-700 dark:stroke-green-400" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
        @endif
    @endforeach

    <line x1="{{ Courbe::MARGE_GAUCHE }}" x2="{{ $droite }}" y1="{{ $bas }}" y2="{{ $bas }}" class="stroke-stone-300 dark:stroke-stone-600" stroke-width="1" />
</svg>
