<div x-data="simulateurMicroReel()" class="tool-calculator-layout">
    
    <div class="tool-inputs-grid">
        <!-- Chiffre d'Affaires -->
        <div class="tool-input-group" style="grid-column: 1 / -1;">
            <label class="tool-field-label">
                Chiffre d'Affaires annuel estimé (€)
                <span>Le total de vos ventes ou prestations facturées sur l'année</span>
                <input type="number" x-model.number="ca" min="0" step="1000" class="tool-text-input">
            </label>
        </div>

        <!-- Type d'activité -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Type d'activité
                <span>Détermine votre abattement forfaitaire en micro-entreprise</span>
                <select x-model="activityType" class="tool-text-input">
                    <option value="bnc">Prestations de services / Libéral (BNC - 34%)</option>
                    <option value="bic">Prestations de services commerciales (BIC - 50%)</option>
                    <option value="vente">Vente de marchandises (BIC - 71%)</option>
                </select>
            </label>
        </div>

        <!-- Frais réels -->
        <div class="tool-input-group">
            <label class="tool-field-label">
                Total de vos frais professionnels annuels (€)
                <span>Achats, logiciels, loyers pro, sous-traitance, honoraires...</span>
                <input type="number" x-model.number="expenses" min="0" step="500" class="tool-text-input">
            </label>
        </div>
    </div>

    <!-- Alertes de seuils -->
    <div x-show="isAboveThreshold" style="background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px; padding: 16px; margin-bottom: 24px; color: #b45309; font-size: 14px; display: flex; align-items: flex-start; gap: 12px;">
        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
        <div>
            <strong>Attention : Vous dépassez le plafond de la micro-entreprise.</strong><br>
            Le plafond de votre activité est fixé à <span x-text="format(threshold)"></span> €. S'il est dépassé deux années consécutives, vous passerez automatiquement au régime réel.
        </div>
    </div>

    <!-- Résultats Comparatifs -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
        
        <!-- Colonne Micro -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; text-align: center; position: relative;">
            <div x-show="bestOption === 'micro'" style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #10b981; color: white; padding: 4px 16px; border-radius: 20px; font-size: 12px; font-weight: 800; text-transform: uppercase;">Plus avantageux</div>
            <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">Micro-Entreprise</h3>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 24px; min-height: 40px;">L'administration applique un abattement forfaitaire de <span x-text="abattementRate"></span>% sur votre CA, peu importe vos vrais frais.</p>
            
            <div style="margin-bottom: 12px; display: flex; justify-content: space-between; font-size: 14px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                <span style="color: #64748b;">Chiffre d'Affaires</span>
                <strong x-text="format(ca) + ' €'"></strong>
            </div>
            <div style="margin-bottom: 12px; display: flex; justify-content: space-between; font-size: 14px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                <span style="color: #64748b;">Abattement (<span x-text="abattementRate"></span>%)</span>
                <strong style="color: #ef4444;" x-text="'- ' + format(microAbattement) + ' €'"></strong>
            </div>
            <div style="margin-bottom: 24px; display: flex; justify-content: space-between; font-size: 16px;">
                <span style="font-weight: 700; color: #0f172a;">Base Imposable</span>
                <strong style="color: #0f172a;" x-text="format(microBase) + ' €'"></strong>
            </div>
        </div>

        <!-- Colonne Réel -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; text-align: center; position: relative;">
            <div x-show="bestOption === 'reel'" style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #10b981; color: white; padding: 4px 16px; border-radius: 20px; font-size: 12px; font-weight: 800; text-transform: uppercase;">Plus avantageux</div>
            <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">Régime Réel</h3>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 24px; min-height: 40px;">Vous déduisez le montant exact de vos dépenses professionnelles.</p>
            
            <div style="margin-bottom: 12px; display: flex; justify-content: space-between; font-size: 14px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                <span style="color: #64748b;">Chiffre d'Affaires</span>
                <strong x-text="format(ca) + ' €'"></strong>
            </div>
            <div style="margin-bottom: 12px; display: flex; justify-content: space-between; font-size: 14px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                <span style="color: #64748b;">Frais Réels (Déduits)</span>
                <strong style="color: #10b981;" x-text="'- ' + format(expenses) + ' €'"></strong>
            </div>
            <div style="margin-bottom: 24px; display: flex; justify-content: space-between; font-size: 16px;">
                <span style="font-weight: 700; color: #0f172a;">Base Imposable</span>
                <strong style="color: #0f172a;" x-text="format(reelBase) + ' €'"></strong>
            </div>
        </div>
    </div>
    
    <div style="margin-top: 32px; background: white; border: 2px solid var(--home-accent); border-radius: 12px; padding: 24px; text-align: center;">
        <div style="font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">Le verdict</div>
        <p style="font-size: 16px; color: #475569; margin: 0; line-height: 1.5;" x-html="verdictText"></p>
    </div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('simulateurMicroReel', () => ({
        ca: 40000,
        activityType: 'bnc',
        expenses: 15000,
        
        get abattementRate() {
            if (this.activityType === 'bnc') return 34;
            if (this.activityType === 'bic') return 50;
            return 71;
        },

        get threshold() {
            return this.activityType === 'vente' ? 188700 : 77700;
        },

        get isAboveThreshold() {
            return (parseFloat(this.ca) || 0) > this.threshold;
        },

        get microAbattement() {
            let ab = (parseFloat(this.ca) || 0) * (this.abattementRate / 100);
            return Math.max(ab, 305); // L'abattement minimum est de 305€
        },

        get microBase() {
            return Math.max(0, (parseFloat(this.ca) || 0) - this.microAbattement);
        },

        get reelBase() {
            return Math.max(0, (parseFloat(this.ca) || 0) - (parseFloat(this.expenses) || 0));
        },

        get bestOption() {
            return this.microBase < this.reelBase ? 'micro' : 'reel';
        },

        get verdictText() {
            if (this.microBase < this.reelBase) {
                let diff = this.reelBase - this.microBase;
                return `En restant en <strong>Micro-Entreprise</strong>, votre base d'imposition (impôts et URSSAF) sera plus basse de <strong>${this.format(diff)} €</strong>. Le régime réel n'est pas intéressant pour vous.`;
            } else if (this.reelBase < this.microBase) {
                let diff = this.microBase - this.reelBase;
                return `Le passage au <strong>Régime Réel</strong> est fiscalement plus intéressant ! Votre base d'imposition diminuera de <strong>${this.format(diff)} €</strong> en déduisant vos frais réels.`;
            } else {
                return `Les deux statuts vous offrent la même base imposable. La micro-entreprise reste préférable pour sa simplicité administrative.`;
            }
        },

        format(val) {
            return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Math.ceil(val));
        }
    }));
});
</script>
