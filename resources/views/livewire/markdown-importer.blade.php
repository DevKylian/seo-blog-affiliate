<div style="background: #f9fafb; padding: 15px; border-radius: 8px; border: 1px dashed #cbd5e1; margin-bottom: 20px;">
    <h3 style="margin-top: 0; font-size: 1.1rem; color: #1e293b;">Importer des articles générés (.md)</h3>
    <form wire:submit="importFiles" style="display: flex; align-items: center; gap: 15px;">
        
        
    
        <input type="file" wire:model="markdownFiles" multiple accept=".md" style="padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px; background: white;">
        
        <button type="submit" style="background: #2563eb; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600;" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="importFiles">Importer</span>
            <span wire:loading wire:target="importFiles">Importation...</span>
        </button>
    </form>
    
    @if ($message)
        <div style="margin-top: 10px; color: #15803d; font-weight: 500;">
            {{ $message }}
        </div>
    @endif
    @error('markdownFiles.*') <span style="color: #dc2626; display: block; margin-top: 5px;">{{ $message }}</span> @enderror
</div>
