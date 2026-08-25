<div class="dashboard-page os-page">
    <section class="page-heading">
        <div>
            <span class="eyebrow dark">CMS interne</span>
            <h1>Abonnés Newsletter</h1>
            <p>Liste des adresses e-mail collectées via le formulaire de la page d'accueil.</p>
        </div>
        <div style="display: flex; align-items: center;">
            <span class="badge" style="background: white; padding: 6px 12px; border-radius: 12px; font-weight: 700; border: 1px solid #e2e8f0; font-size: 12px; color: #0f172a;">{{ $subscribers->total() }} inscrit(s)</span>
        </div>
    </section>

    <article class="panel">
        <div class="panel-head">
            <div>
                <h2>Base de données emails</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>E-mail</th>
                        <th>Source</th>
                        <th>Date d'inscription</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($subscribers as $subscriber)
                        <tr>
                            <td style="font-weight: 700; color: #263047;">{{ $subscriber->email }}</td>
                            <td>
                                @if($subscriber->source === 'outil')
                                    <span style="background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase;">Outil</span>
                                @else
                                    <span style="background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase;">Newsletter</span>
                                @endif
                            </td>
                            <td>{{ $subscriber->created_at->format('d/m/Y H:i') }}</td>
                            <td style="text-align: right;">
                                <button wire:click="delete({{ $subscriber->id }})" wire:confirm="Supprimer cet e-mail ?" style="color: #ef4444; background: none; border: none; cursor: pointer; text-decoration: underline; font-size: 11px;">Supprimer</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px; color: #7c8598;">Aucun abonné pour le moment.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($subscribers->hasPages())
            <div style="padding: 16px; border-top: 1px solid #f0f1f5;">
                {{ $subscribers->links() }}
            </div>
        @endif
    </article>
</div>
