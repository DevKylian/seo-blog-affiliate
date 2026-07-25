<div x-data="seuilTva()" class="tool-calculator-layout">
    
    <div class="tool-inputs-grid">
        <!-- Type d'activité -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Activité principale
                <span>Les seuils de franchise de TVA diffèrent selon le type d'activité</span>
                <select x-model="activity" class="tool-select-input">
                    <option value="services">Prestations de services / Libéral</option>
                    <option value="vente">Vente de marchandises / Hébergement</option>
                </select>
            </label>
        </div>
        
        <!-- CA Année N-1 -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Chiffre d'Affaires de l'année précédente (N-1)
                <span>Nécessaire pour calculer la tolérance sur deux ans</span>
                <input type="number" x-model.number="ca_n1" min="0" step="1000" class="tool-text-input">
            </label>
        </div>

        <!-- CA Année N -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Chiffre d'Affaires de l'année en cours (N)
                <span>Votre facturation brute cumulée sur l'année en cours</span>
                <input type="number" x-model.number="ca_n" min="0" step="1000" class="tool-text-input">
            </label>
        </div>
    </div>

    <!-- Seuils d'information -->
    <div style="padding: 16px; background: white; border: 1px solid var(--tool-border); border-radius: 12px; font-size: 13px; color: var(--tool-muted); display: flex; justify-content: space-around; flex-wrap: wrap; gap: 16px;">
        <div>Seuil de base : <strong style="color:var(--tool-primary);" x-text="format(thresholds.base) + ' €'"></strong></div>
        <div>Seuil majoré : <strong style="color:var(--tool-primary);" x-text="format(thresholds.majore) + ' €'"></strong></div>
    </div>

    <!-- Résultats -->
    <div :class="results.class" style="border-radius: 20px; padding: 40px 32px; text-align: center; border: 1px solid;">
        <div class="tool-result-eyebrow">Votre statut TVA</div>
        <div class="tool-result-value" style="font-size: 24px; margin-bottom: 16px;" x-text="results.title"></div>
        <p style="font-size: 15px; line-height: 1.6; margin: 0 auto; font-weight: 500; max-width: 500px;" x-html="results.desc"></p>
    </div>
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('seuilTva', () => ({
        activity: 'services',
        ca_n1: 25000,
        ca_n: 38000,
        
        get thresholds() {
            if (this.activity === 'vente') {
                return { base: 91900, majore: 101000 };
            }
            return { base: 36800, majore: 39100 };
        },
        
        get results() {
            let t = this.thresholds;
            let n = parseFloat(this.ca_n) || 0;
            let n1 = parseFloat(this.ca_n1) || 0;

            if (n > t.majore) {
                return {
                    title: '🚨 Assujetti à la TVA (Immédiat)',
                    desc: 'Vous avez dépassé le seuil majoré. Vous devez facturer la TVA <strong>dès le premier jour du mois de dépassement</strong>.',
                    class: 'tool-result-danger-box'
                };
            } else if (n > t.base) {
                if (n1 > t.base) {
                    return {
                        title: '⚠️ Assujetti à la TVA (Période de tolérance dépassée)',
                        desc: 'Vous avez dépassé le seuil de base deux années de suite. Vous devez facturer la TVA <strong>depuis le 1er janvier de l\'année en cours</strong>.',
                        class: 'tool-result-warning-box'
                    };
                } else {
                    return {
                        title: '✅ Franchise en base maintenue (Tolérance)',
                        desc: 'Vous êtes dans la zone de tolérance. Vous ne facturez <strong>pas de TVA cette année</strong>, mais vous y serez assujetti dès le 1er janvier prochain si votre chiffre d\'affaires de l\'année en cours reste au-dessus du seuil de base.',
                        class: 'tool-result-success-box'
                    };
                }
            } else {
                return {
                    title: '✅ Franchise en base (Non assujetti)',
                    desc: 'Vous êtes en dessous du seuil de base. Vous n\'avez <strong>pas de TVA à facturer</strong> ni à déclarer.',
                    class: 'tool-result-info-box'
                };
            }
        },

        format(val) {
            return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(val);
        }
    }));
});
</script>
