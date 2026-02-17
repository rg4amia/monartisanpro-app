<div class="space-y-4">
    <div class="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
        <h3 class="text-lg font-semibold mb-3">Formule de Calcul N'Zassa</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            Le score N'Zassa est calculé selon la formule suivante :
        </p>
        <div class="bg-white dark:bg-gray-900 p-3 rounded border border-gray-200 dark:border-gray-700 font-mono text-sm">
            Score Total = (Fiabilité × 40%) + (Intégrité × 30%) + (Qualité × 15%) + (Réactivité × 10%) + (Professionnalisme × 5%)
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Fiabilité</span>
                <span class="text-lg font-bold text-blue-600">{{ number_format($record->reliability_score, 2) }}/40</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-blue-600 h-2 rounded-full" style="width: {{ ($record->reliability_score / 40) * 100 }}%"></div>
            </div>
            <p class="text-xs text-gray-500 mt-1">Respect des délais, taux d'achèvement</p>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Intégrité</span>
                <span class="text-lg font-bold text-green-600">{{ number_format($record->integrity_score, 2) }}/30</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-green-600 h-2 rounded-full" style="width: {{ ($record->integrity_score / 30) * 100 }}%"></div>
            </div>
            <p class="text-xs text-gray-500 mt-1">Transparence, honnêteté</p>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Qualité</span>
                <span class="text-lg font-bold text-purple-600">{{ number_format($record->quality_score, 2) }}/15</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-purple-600 h-2 rounded-full" style="width: {{ ($record->quality_score / 15) * 100 }}%"></div>
            </div>
            <p class="text-xs text-gray-500 mt-1">Notes des clients, qualité du travail</p>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Réactivité</span>
                <span class="text-lg font-bold text-orange-600">{{ number_format($record->responsiveness_score, 2) }}/10</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2">
                <div class="bg-orange-600 h-2 rounded-full" style="width: {{ ($record->responsiveness_score / 10) * 100 }}%"></div>
            </div>
            <p class="text-xs text-gray-500 mt-1">Temps de réponse, communication</p>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Professionnalisme</span>
            <span class="text-lg font-bold text-indigo-600">{{ number_format($record->professionalism_score, 2) }}/5</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2">
            <div class="bg-indigo-600 h-2 rounded-full" style="width: {{ ($record->professionalism_score / 5) * 100 }}%"></div>
        </div>
        <p class="text-xs text-gray-500 mt-1">Comportement, présentation, courtoisie</p>
    </div>

    <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
            <span class="text-lg font-semibold text-gray-900 dark:text-gray-100">Score Total</span>
            <span class="text-2xl font-bold text-blue-600">{{ number_format($record->total_score, 2) }}/100</span>
        </div>
    </div>

    <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
        <h4 class="text-sm font-semibold mb-2">Critères de Badge</h4>
        <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
            <li class="flex items-center">
                <span class="mr-2">🥇</span>
                <span><strong>Or:</strong> Score ≥ 80/100 ET ≥ 20 projets complétés</span>
            </li>
            <li class="flex items-center">
                <span class="mr-2">🥈</span>
                <span><strong>Argent:</strong> Score ≥ 65/100 ET ≥ 10 projets complétés</span>
            </li>
            <li class="flex items-center">
                <span class="mr-2">🥉</span>
                <span><strong>Bronze:</strong> Score ≥ 50/100 ET ≥ 5 projets complétés</span>
            </li>
        </ul>
    </div>

    <div class="text-xs text-gray-500 text-center">
        Dernier calcul: {{ $record->last_calculated_at->format('d/m/Y à H:i') }}
    </div>
</div>
