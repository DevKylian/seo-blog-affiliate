@extends('layouts.blog')

@section('title', 'Outils gratuits pour freelances')
@section('description', 'Calculateurs et checklists gratuits pour gérer son activité freelance.')

@section('content')
<section class="blog-hero tools-hero editorial-hero">
    <div>
        <span class="eyebrow">Outils gratuits</span>
        <h1>Outils gratuits freelance, avant de choisir un logiciel.</h1>
        <p>TJM, revenu net, checklist de creation : commencez par clarifier vos chiffres et vos obligations.</p>
    </div>
</section>

<section class="blog-listing">
    <div class="free-tool-grid">
        @foreach($tools as $tool)
            <a href="{{ route('free-tools.show', $tool['slug']) }}">
                <small>Outil</small>
                <strong>{{ $tool['title'] }}</strong>
                <span>{{ $tool['description'] }}</span>
            </a>
        @endforeach
    </div>
</section>
@endsection
