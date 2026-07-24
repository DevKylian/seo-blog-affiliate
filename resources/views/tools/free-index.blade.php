@extends('layouts.blog')

@section('title', 'Outils gratuits pour freelances')
@section('description', 'Calculateurs et checklists gratuits pour gérer son activité freelance.')

@section('content')
<section class="pro-hero" style="padding: 100px 20px; background: linear-gradient(135deg, #020617, #0f172a, #1e1b4b); color: white;">
    <div class="pro-hero-inner">
        <span class="pro-eyebrow">Outils gratuits</span>
        <h1 style="color: white; background: none; -webkit-text-fill-color: white;">Outils gratuits freelance, avant de choisir un logiciel.</h1>
        <p>TJM, revenu net, checklist de création : commencez par clarifier vos chiffres et vos obligations avant de déléguer.</p>
    </div>
</section>

<section class="blog-listing" style="padding: 60px 20px; background: #f8fafc;">
    <div class="free-tool-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px; max-width: 1000px; margin: 0 auto;">
        @foreach($tools as $tool)
            <a href="{{ route('free-tools.show', $tool['slug']) }}" style="background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; text-decoration: none; display: flex; flex-direction: column; transition: transform 0.3s ease, box-shadow 0.3s ease; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                <small style="color: #2563eb; font-weight: 800; font-size: 11px; text-transform: uppercase; margin-bottom: 8px;">Outil interactif</small>
                <strong style="color: #0f172a; font: 800 20px/1.3 'Manrope', sans-serif; margin-bottom: 12px;">{{ $tool['title'] }}</strong>
                <span style="color: #475569; font-size: 15px; line-height: 1.6;">{{ $tool['description'] }}</span>
            </a>
        @endforeach
    </div>
</section>
@endsection
