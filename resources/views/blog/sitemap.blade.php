{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('home') }}</loc><lastmod>{{ now()->toAtomString() }}</lastmod></url>
    <url><loc>{{ route('blog.index') }}</loc><lastmod>{{ now()->toAtomString() }}</lastmod></url>
    <url><loc>{{ route('free-tools.index') }}</loc><lastmod>{{ now()->toAtomString() }}</lastmod></url>
    <url><loc>{{ route('free-tools.show', 'calculateur-tjm-freelance') }}</loc><lastmod>{{ now()->toAtomString() }}</lastmod></url>
    <url><loc>{{ route('free-tools.show', 'calculateur-revenu-freelance') }}</loc><lastmod>{{ now()->toAtomString() }}</lastmod></url>
    <url><loc>{{ route('free-tools.show', 'checklist-creation-micro-entreprise') }}</loc><lastmod>{{ now()->toAtomString() }}</lastmod></url>
    <url><loc>{{ route('tools.index') }}</loc><lastmod>{{ now()->toAtomString() }}</lastmod></url>
    @foreach($tools as $tool)<url><loc>{{ route('tools.show',$tool->slug) }}</loc><lastmod>{{ $tool->updated_at->toAtomString() }}</lastmod></url><url><loc>{{ route('tools.pricing',$tool->slug) }}</loc><lastmod>{{ $tool->updated_at->toAtomString() }}</lastmod></url>@endforeach
    @foreach($articles as $article)<url><loc>{{ $article->public_url }}</loc><lastmod>{{ $article->updated_at->toAtomString() }}</lastmod></url>@endforeach
</urlset>
