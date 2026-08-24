<div x-data="simulateurSasu()" class="tool-calculator-layout">
    
    <div class="tool-inputs-grid">
        <div class="tool-input-group" style="grid-column: 1 / -1;">
            <label class="tool-field-label">
                Trésorerie disponible avant rémunération (€)
                <span>Le montant total que la société peut vous verser (Bénéfice avant impôt)</span>
                <input type="number" x-model.number="benefice" min="0" step="1000" class="tool-text-input">
            </label>
        </div>
    </div>

    <!-- Résultats Comparatifs -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 24px;">
        
        <!-- Colonne Salaire -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; position: relative;">
            <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">100% Salaire (Assimilié Salarié)</h3>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 24px; min-height: 40px;">Fortement imposé mais offre une protection sociale (retraite, maladie).</p>
            
            <div style="margin-bottom: 12px; display: flex; justify-content: space-between; font-size: 14px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                <span style="color: #64748b;">Trésorerie initiale</span>
                <strong x-text="format(benefice) + ' €'"></strong>
            </div>
            <div style="margin-bottom: 12px; display: flex; justify-content: space-between; font-size: 14px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                <span style="color: #64748b;">Charges sociales (~75%)</span>
                <strong style="color: #ef4444;" x-text="'- ' + format(chargesSociales) + ' €'"></strong>
            </div>
            <div style="margin-bottom: 24px; display: flex; justify-content: space-between; font-size: 16px;">
                <span style="font-weight: 700; color: #0f172a;">Rémunération Nette</span>
                <strong style="color: #10b981; font-weight: 900; font-size: 20px;" x-text="format(salaireNet) + ' €'"></strong>
            </div>
            <div style="font-size: 12px; color: #64748b; text-align: center; background: #e2e8f0; padding: 8px; border-radius: 6px;">
                Validation de trimestres de retraite : <strong style="color: #0f172a;">OUI</strong>
            </div>
        </div>

        <!-- Colonne Dividendes -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; position: relative;">
            <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">100% Dividendes (PFU)</h3>
            <p style="font-size: 13px; color: #64748b; margin-bottom: 24px; min-height: 40px;">Fiscalité plus douce mais aucune couverture sociale (ni retraite).</p>
            
            <div style="margin-bottom: 12px; display: flex; justify-content: space-between; font-size: 14px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                <span style="color: #64748b;">Trésorerie initiale</span>
                <strong x-text="format(benefice) + ' €'"></strong>
            </div>
            <div style="margin-bottom: 12px; display: flex; justify-content: space-between; font-size: 14px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                <span style="color: #64748b;">Impôt sur les sociétés (IS)</span>
                <strong style="color: #ef4444;" x-text="'- ' + format(impotSociete) + ' €'"></strong>
            </div>
            <div style="margin-bottom: 12px; display: flex; justify-content: space-between; font-size: 14px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 8px;">
                <span style="color: #64748b;">Flat Tax (PFU 30%)</span>
                <strong style="color: #ef4444;" x-text="'- ' + format(flatTax) + ' €'"></strong>
            </div>
            <div style="margin-bottom: 24px; display: flex; justify-content: space-between; font-size: 16px;">
                <span style="font-weight: 700; color: #0f172a;">Dividende Net</span>
                <strong style="color: #10b981; font-weight: 900; font-size: 20px;" x-text="format(dividendeNet) + ' €'"></strong>
            </div>
            <div style="font-size: 12px; color: #64748b; text-align: center; background: #fee2e2; padding: 8px; border-radius: 6px;">
                Validation de trimestres de retraite : <strong style="color: #ef4444;">NON</strong>
            </div>
        </div>
    </div>
    
    <div style="margin-top: 32px; background: white; border: 2px solid var(--home-accent); border-radius: 12px; padding: 24px;">
        <div style="font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 12px;">📊 L'analyse du simulateur</div>
        <p style="font-size: 15px; color: #475569; margin: 0; line-height: 1.6;" x-html="verdictText"></p>
    </div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('simulateurSasu', () => ({
        benefice: 60000,
        
        get chargesSociales() {
            let b = parseFloat(this.benefice) || 0;
            // En SASU, les cotisations représentent environ 75% à 80% du salaire net.
            // Donc Benefice = Salaire Net + (Salaire Net * 0.75) => Benefice = Salaire Net * 1.75
            let net = b / 1.75;
            return b - net;
        },

        get salaireNet() {
            let b = parseFloat(this.benefice) || 0;
            return b - this.chargesSociales;
        },

        get impotSociete() {
            let b = parseFloat(this.benefice) || 0;
            if (b <= 0) return 0;
            
            // IS 2026 : 15% jusqu'à 42 500 €, 25% au-delà
            let is15 = Math.min(b, 42500) * 0.15;
            let is25 = Math.max(0, b - 42500) * 0.25;
            
            return is15 + is25;
        },
        
        get beneficeApresIS() {
            return (parseFloat(this.benefice) || 0) - this.impotSociete;
        },

        get flatTax() {
            // Flat tax (PFU) de 30% sur le bénéfice distribué
            return this.beneficeApresIS * 0.30;
        },

        get dividendeNet() {
            return this.beneficeApresIS - this.flatTax;
        },

        get verdictText() {
            let diff = this.dividendeNet - this.salaireNet;
            if (diff > 0) {
                return `En optant pour 100% de dividendes, vous maximisez votre rémunération immédiate de <strong>${this.format(diff)} € nets</strong>. 
                <br><br>Cependant, <strong style="color:#ef4444;">attention au piège</strong> : les dividendes ne vous octroient <strong>aucune couverture sociale</strong>. La meilleure stratégie en SASU est souvent un mix : se verser un petit salaire pour valider ses trimestres de retraite, et prendre le reste en dividendes.`;
            } else {
                return `Le salaire est plus intéressant ou équivalent.`;
            }
        },

        format(val) {
            return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(Math.ceil(val));
        }
    }));
});
</script>
