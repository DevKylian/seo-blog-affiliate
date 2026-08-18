<div>
    {{-- If your happiness depends on money, you will never be happy with yourself. --}}

    <div class="mt-4 border-t border-gray-700 pt-4">
        <h5 class="text-sm font-bold text-gray-300">Validation Requise</h5>
        @if($artifact)
            <div class="bg-gray-900 p-4 rounded text-sm overflow-auto text-gray-300 mt-2 mb-4 max-h-64">
                <pre>{{ json_encode($artifact->data, JSON_PRETTY_PRINT) }}</pre>
            </div>
        @endif
        <button wire:click="validateStep" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
            Valider et Continuer
        </button>
    </div>
</div>
