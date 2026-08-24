<div x-data="simulateurSeuilOss()" class="tool-calculator-layout">
    
    <div class="tool-inputs-grid">
        <div class="tool-input-group" style="grid-column: 1 / -1;">
            <div style="background: #f0fdf4; border-left: 4px solid #16a34a; padding: 16px; border-radius: 0 8px 8px 0; margin-bottom: 24px;">
                <p style="margin: 0; font-size: 14px; color: #166534; line-height: 1.5;"><strong>Le guichet unique OSS (One Stop Shop)</strong> concerne les vendeurs e-commerce en B2C qui expédient des marchandises depuis la France vers d'autres pays de l'Union Européenne.</p>
            </div>
        </div>

        <div class="tool-input-group" style="grid-column: 1 / -1;">
            <label class="tool-field-label">
                Chiffre d'affaires HT de vos ventes à distance intra-UE (sur l'année)
                <span>Uniquement les ventes B2C expédiées vers les particuliers d'autres pays de l'UE</span>
                <input type="number" x-model.number="ventesUe" min="0" step="500" class="tool-text-input">
            </label>
        </div>
    </div>

    <!-- Barre de progression visuelle -->
    <div style="margin: 24px 0;">
        <div style="display: flex; justify-content: space-between; font-size: 13px; font-weight: 700; color: #64748b; margin-bottom: 8px;">
            <span>0 €</span>
            <span>Seuil : 10 000 €</span>
        </div>
        <div style="width: 100%; height: 12px; background: #e2e8f0; border-radius: 6px; overflow: hidden; position: relative;">
            <div style="height: 100%; transition: width 0.3s ease, background-color 0.3s ease;" 
                 :style="`width: Math.min(100, progressPercentage) + '%'; background: isAboveThreshold ? '#ef4444' : '#10b981';`">
            </div>
            <div style="position: absolute; top: 0; bottom: 0; left: 100%; border-left: 2px dashed #0f172a; margin-left: -2px; z-index: 10;" x-show="progressPercentage < 100"></div>
        </div>
    </div>

    <!-- Alertes et résultats -->
    <div x-show="!isAboveThreshold" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; text-align: center;">
        <div style="font-size: 40px; margin-bottom: 12px;">✅</div>
        <h3 style="font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">TVA Française applicable</h3>
        <p style="font-size: 15px; color: #475569; margin: 0; line-height: 1.5;">
            Vous êtes sous le seuil des 10 000 €. Vous continuez à facturer la TVA au taux français (20% en général) pour toutes vos expéditions B2C vers l'UE et à la déclarer sur votre déclaration de TVA française classique.
        </p>
    </div>

    <div x-show="isAboveThreshold" style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 24px; text-align: center;">
        <div style="font-size: 40px; margin-bottom: 12px;">🌍</div>
        <h3 style="font-size: 18px; font-weight: 800; color: #991b1b; margin-bottom: 8px;">Inscription au guichet OSS obligatoire</h3>
        <p style="font-size: 15px; color: #991b1b; margin: 0; line-height: 1.5; font-weight: 500;">
            Vous avez dépassé le seuil des 10 000 €. <br><br>
            Vous devez désormais facturer la TVA <strong>au taux du pays de destination</strong> de votre client (ex: 19% pour l'Allemagne, 21% pour l'Espagne) et reverser cette TVA via le Guichet Unique (OSS) sur impots.gouv.fr.
        </p>
    </div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('simulateurSeuilOss', () => ({
        ventesUe: 4500,
        
        get isAboveThreshold() {
            return (parseFloat(this.ventesUe) || 0) > 10000;
        },

        get progressPercentage() {
            let v = parseFloat(this.ventesUe) || 0;
            return (v / 10000) * 100;
        }
    }));
});
</script>
