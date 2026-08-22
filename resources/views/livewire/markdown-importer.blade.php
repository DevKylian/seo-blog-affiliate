<div style="background: rgba(255, 255, 255, 0.05); padding: 15px; border-radius: 8px; border: 1px dashed rgba(255, 255, 255, 0.2); margin-bottom: 20px;">
    <h3 style="margin-top: 0; font-size: 1.1rem; color: #f8fafc;">Importer des articles générés (.md)</h3>
    <form wire:submit="importFiles" style="display: flex; align-items: center; gap: 15px;">
    
        <input type="file" wire:model="markdownFiles" multiple accept=".md" style="padding: 8px; border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 4px; background: rgba(0, 0, 0, 0.2); color: #f8fafc;">
        
        <button type="submit" style="background: #3b82f6; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: 600;" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="importFiles">Importer</span>
            <span wire:loading wire:target="importFiles">Importation...</span>
        </button>
    </form>
    
    @if ($message)
        <div style="margin-top: 10px; color: #4ade80; font-weight: 500;">
            {{ $message }}
        </div>
    @endif
    @error('markdownFiles.*') <span style="color: #f87171; display: block; margin-top: 5px;">{{ $message }}</span> @enderror
</div>
