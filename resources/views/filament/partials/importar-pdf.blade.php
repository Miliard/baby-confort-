{{-- Lee el PDF de etiquetas de Sistrack y registra todas las guías con su cliente. --}}
<p class="mb-4 text-sm text-gray-500 dark:text-gray-400">
    Subí el <span class="font-semibold">PDF de etiquetas</span> que te da Sistrack después de importar.
    Se leen todas las guías con su cliente y teléfono <span class="font-semibold">exactos</span>,
    y quedan listos los mensajes para enviar.
</p>

<label for="pdf-input"
    class="block cursor-pointer rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 p-6 text-center transition hover:border-primary-500 dark:border-white/20 dark:bg-white/5">
    <div class="text-4xl leading-none">📄</div>
    <div class="mt-2 text-sm font-bold text-gray-950 dark:text-white">Elegir el PDF de etiquetas</div>
    <div class="text-xs text-gray-500 dark:text-gray-400">Lee todas las guías de una vez</div>
</label>
<input id="pdf-input" type="file" accept="application/pdf" class="hidden">

<div id="pdf-progreso" class="mt-4 hidden">
    <div class="mb-1.5 flex justify-between text-sm font-bold text-gray-950 dark:text-white">
        <span id="pdf-texto">Leyendo el PDF…</span>
        <span id="pdf-num" class="font-medium text-gray-500 dark:text-gray-400"></span>
    </div>
    <div class="h-2.5 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
        <div id="pdf-barra" class="h-full w-0 rounded-full bg-primary-600 transition-all duration-300"></div>
    </div>
</div>

<div id="pdf-resultado" class="mt-4"></div>
