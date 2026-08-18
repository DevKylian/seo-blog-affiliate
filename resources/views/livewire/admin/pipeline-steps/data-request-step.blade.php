<div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #cbd5e1;">
    <form wire:submit="processUpload" style="display: flex; gap: 10px; align-items: flex-end;">
        <div style="flex: 1;">
            <label style="font-size: 12px; font-weight: bold; display: block; margin-bottom: 6px; color: #334155;">Fichier CSV (Semrush)</label>
            <input type="file" wire:model="csvFile" style="font-size: 13px; font-family: inherit;">
            @error('csvFile') <span style="color: #ef4444; font-size: 11px; display: block; margin-top: 4px;">{{ $message }}</span> @enderror
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit" class="primary-button" style="padding: 6px 14px; font-size: 13px;" wire:loading.attr="disabled" wire:target="processUpload, simulateImport">
                <span wire:loading.remove wire:target="processUpload">Importer CSV →</span>
                <span wire:loading wire:target="processUpload">Import...</span>
            </button>
            
            <button type="button" wire:click="simulateImport" style="background: white; border: 1px solid #cbd5e1; color: #475569; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer;" wire:loading.attr="disabled" wire:target="processUpload, simulateImport">
                <span wire:loading.remove wire:target="simulateImport">Passer (Simuler données IA)</span>
                <span wire:loading wire:target="simulateImport">Simulation...</span>
            </button>
        </div>
    </form>
</div>
