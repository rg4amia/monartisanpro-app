import axios from 'axios';
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';

// Mock institutional docs for quick demo ingestion
const MOCK_INSTITUTIONAL_DOCS = [
  {
    id: "doc-1",
    title: "Guide LBTP 2023 - Spécifications Dalles Béton",
    raw_text: "SECTION 3.1: DOSAGE DES BÉTONS DE STRUCTURE. Le béton destiné aux ouvrages porteurs (dalles, poteaux, poutres) doit impérativement être dosé à 350 kg/m³ de ciment Portland Pur de classe CEM I 42.5 R ou CEM II/A 42.5. Le malaxage mécanique à tambour rotatif est obligatoire pendant une durée minimale de 180 secondes. L'ajout d'eau en cours de prise pour améliorer la maniabilité est formellement interdit sous peine de chute drastique de la résistance mécanique sous 28 jours.",
    markdown_tables: `| Composant | Dosage par m³ de béton | Spécification |
| :--- | :--- | :--- |
| Ciment | 350 kg | CEM I ou II 42.5 R |
| Sable sec | 400 L | Sable siliceux lavé |
| Gravier | 800 L | Gravier concassé 15/25 |
| Eau max | 175 L | Eau propre (pH > 6) |`
  },
  {
    id: "doc-2",
    title: "Norme BNETD - Enduits extérieurs de protection",
    raw_text: "PROT-402: MÉTHODOLOGIE DE L'ENDUIT TRADITIONNEL. Les maçonneries en blocs de ciment doivent recevoir un enduit de protection appliqué en 3 couches successives : 1. Gobetis d'accrochage d'épaisseur 3 à 5 mm dosé à 500 kg/m³ de ciment de classe CEM II 32.5 R. 2. Corps d'enduit de dressage d'épaisseur 10 à 15 mm dosé à 350 kg/m³. 3. Couche de finition talochée d'épaisseur 5 mm dosée à 250 kg/m³.",
    markdown_tables: `| Couche d'Enduit | Épaisseur | Dosage Ciment / m³ | Utilisation |
| :--- | :--- | :--- | :--- |
| 1. Gobetis | 3 - 5 mm | 500 kg (CEM II 32.5) | Accrochage rugueux |
| 2. Corps d'enduit | 10 - 15 mm | 350 kg | Dressage et planéité |
| 3. Finition | 5 mm | 250 kg | Lissage esthétique |`
  }
];

interface LogItem {
  timestamp: string;
  category: 'system' | 'vlm' | 'llm-downscale' | 'rag';
  message: string;
}

export default function LlmAdminPanel() {
  // Data lists
  const [stagingItems, setStagingItems] = useState<any[]>([]);
  const [productionItems, setProductionItems] = useState<any[]>([]);
  const [importHistory, setImportHistory] = useState<any[]>([]);
  const [customDocs, setCustomDocs] = useState<any[]>([]);

  // Selection states
  const [selectedDocId, setSelectedDocId] = useState<string>("doc-1");
  const [selectedStagingItem, setSelectedStagingItem] = useState<any | null>(null);

  // Pipeline steps progress states
  const [rawTextPreview, setRawTextPreview] = useState<string>("");
  const [vlmMarkdownPreview, setVlmMarkdownPreview] = useState<string>("");
  const [pipelineStep, setPipelineStep] = useState<'idle' | 'vlm_done' | 'llm_done'>('idle');
  const [isProcessingVlm, setIsProcessingVlm] = useState<boolean>(false);
  const [isProcessingLlm, setIsProcessingLlm] = useState<boolean>(false);
  const [uploadProgress, setUploadProgress] = useState<number | null>(null);

  // Logs terminal — seed with boot message to avoid setState in effect
  const [logs, setLogs] = useState<LogItem[]>([{
    timestamp: new Date().toLocaleTimeString('fr-FR'),
    category: 'system',
    message: "Console d'administration LLM initialisée. Connexion active."
  }]);
  const logsEndRef = useRef<HTMLDivElement>(null);

  // Chat simulator
  const [chatMessage, setChatMessage] = useState<string>("");
  const [chatHistory, setChatHistory] = useState<any[]>([]);
  const [isChatLoading, setIsChatLoading] = useState<boolean>(false);
  const [networkState, setNetworkState] = useState<'wifi' | '3g' | 'offline'>('wifi');
  const [localCache, setLocalCache] = useState<any[]>([]);

  const addLog = useCallback((category: LogItem['category'], message: string) => {
    const newItem: LogItem = {
      timestamp: new Date().toLocaleTimeString('fr-FR'),
      category,
      message
    };
    setLogs(prev => [...prev, newItem]);
  }, []);

  // Load initial data
  const fetchData = useCallback(async () => {
    try {
      const [stag, prod, imp] = await Promise.all([
        axios.get('/admin/api/llm/staging'),
        axios.get('/admin/api/llm/production'),
        axios.get('/admin/api/llm/imports')
      ]);
      setStagingItems(stag.data);
      setProductionItems(prod.data);
      setImportHistory(imp.data);
      setLocalCache(prod.data); // Seed local RAG cache for the mobile offline demo
    } catch {
      addLog('system', "Erreur lors du chargement des données de la base de données");
    }
  }, [addLog]);

  // Mount-only init — null-check is the compiler-approved ref init pattern
  const didInitRef = useRef<true | null>(null);
  if (didInitRef.current == null) {
    didInitRef.current = true;
    void fetchData();
  }

  useEffect(() => {
    if (logsEndRef.current) {
      logsEndRef.current.scrollIntoView({ behavior: 'smooth' });
    }
  }, [logs]);

  // Document selection details update
  const allDocs = useMemo(() => [...MOCK_INSTITUTIONAL_DOCS, ...customDocs], [customDocs]);
  const activeDoc = useMemo(() => allDocs.find(d => d.id === selectedDocId), [allDocs, selectedDocId]);

  // Adjust state during render when the selected doc changes (React docs pattern)
  const [prevActiveDoc, setPrevActiveDoc] = useState(activeDoc);
  if (activeDoc && activeDoc !== prevActiveDoc) {
    setPrevActiveDoc(activeDoc);
    setRawTextPreview(activeDoc.raw_text);
    setVlmMarkdownPreview("");
    setPipelineStep('idle');
  }

  // Handle file uploads (e.g. PDFs)
  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploadProgress(10);
    const reader = new FileReader();
    reader.onload = async () => {
      try {
        const base64Content = (reader.result as string).split(',')[1];
        setUploadProgress(50);

        // 1. Upload to fileshare backend API
        const uploadRes = await axios.post('/admin/api/llm/upload', {
          filename: file.name,
          content: base64Content
        });

        setUploadProgress(80);

        // 2. Create import history record
        const docId = "custom-" + uploadRes.data.id;
        const importedAt = new Date().toISOString().replace('T', ' ').substring(0, 19);
        await axios.post('/admin/api/llm/imports', {
          id: docId,
          filename: file.name,
          file_size: file.size,
          imported_at: importedAt,
          status: 'PENDING_VLM'
        });

        // 3. Register as a custom selectable doc in state
        const newCustomDoc = {
          id: docId,
          title: `Import : ${file.name}`,
          raw_text: `CONTENU TECHNIQUE IMPORTÉ DE ${file.name}.\n\n` + (file.type.startsWith('text') ? window.atob(base64Content) : "Fichier brut (PDF). Extraction VLM requise pour récupérer la structure et le texte."),
          markdown_tables: "| Colonne 1 | Colonne 2 |\n|---|---|\n| Valeur à extraire | Par VLM |"
        };

        setCustomDocs(prev => [...prev, newCustomDoc]);
        setSelectedDocId(docId);
        setUploadProgress(100);
        setTimeout(() => setUploadProgress(null), 1000);

        addLog('system', `Fichier ${file.name} téléversé avec succès. ID : ${docId}`);
        fetchData();
      } catch (err: any) {
        setUploadProgress(null);
        addLog('system', `Erreur téléversement : ${err.message}`);
        alert("Une erreur s'est produite lors de l'importation.");
      }
    };
    reader.readAsDataURL(file);
  };

  // Run simulated VLM Parsing
  const runVlmParsing = async () => {
    if (!activeDoc) return;
    setIsProcessingVlm(true);
    addLog('vlm', `Lancement du traitement LlamaParse (Extraction de tables) pour : "${activeDoc.title}"`);

    setTimeout(async () => {
      setIsProcessingVlm(false);
      setVlmMarkdownPreview(activeDoc.markdown_tables);
      setPipelineStep('vlm_done');
      addLog('vlm', `Extraction réussie. Markdown structural récupéré.`);

      if (selectedDocId.startsWith("custom-")) {
        try {
          await axios.put(`/admin/api/llm/imports/${selectedDocId}`, {
            vlm_extracted: true,
            status: "PENDING_LLM"
          });
          fetchData();
        } catch { /* update best-effort, non-critical */ }
      }
    }, 1000);
  };

  // Run simulated LLM Downscaling to staging
  const runLlmDownscaling = async () => {
    if (!activeDoc) return;
    setIsProcessingLlm(true);
    addLog('llm-downscale', "Envoi des données brutes + tableaux de dosages au LLM de déclassement...");

    setTimeout(async () => {
      setIsProcessingLlm(false);
      setPipelineStep('llm_done');

      // Generate a mock json based on document title
      let generatedJson: any = {};
      const generatedId = "stage-" + Math.floor(Math.random() * 1000 + 200);

      if (activeDoc.title.includes("Dalles Béton")) {
        generatedJson = {
          id: generatedId,
          norme_origine: {
            source: "LBTP",
            reference_article: "SECTION 3.1",
            titre_original: "Dosage des bétons de structure (Dalles)",
            texte_brut: activeDoc.raw_text
          },
          alternative_prosartisan: {
            titre_vulgarise: "Mélange et coulage manuel de béton de structure (dalles & poteaux)",
            methode_execution: "Effectuer un gâchage manuel méticuleux uniquement sur une aire propre et plane (plaque de tôle d'acier ou dalle béton nettoyée) pour éviter d'incorporer de la terre ou des débris organiques. Mélanger d'abord le ciment, le sable et le gravier à sec. Retourner le tas au moins 3 fois jusqu'à obtenir une couleur grise homogène. Ajouter l'eau au centre du cratère de manière progressive. Gâcher vigoureusement. Piquer le béton frais à la barre de fer après coulage pour chasser les bulles d'air. Maintenir humide l'ouvrage (arrosage matinal doux) pendant les 7 premiers jours.",
            dosages_recommandes: [
              { element: "Ciment CPJ 42.5 (CIMAF / LafargeHolcim / Dangote)", ratio: "1 sac (50kg)", unite_mesure_locale: "Sac" },
              { element: "Sable de carrière propre (grains moyens)", ratio: "1.5 brouettes de 60L", unite_mesure_locale: "Brouette (60L)" },
              { element: "Gravier concassé type 15/25", ratio: "2.5 brouettes de 60L", unite_mesure_locale: "Brouette (60L)" },
              { element: "Eau propre du robinet", ratio: "22 Litres (environ 2 seaux de maçon de 10L)", unite_mesure_locale: "Seau de maçon (10L)" }
            ],
            materiaux_recommandes: [
              { nom: "Ciment CPJ 42.5", substitut_acceptable: "CPJ 32.5 (FORMELLEMENT INTERDIT POUR LES DALLES)", disponibilite: "Quincaillerie" },
              { nom: "Sable de carrière", substitut_acceptable: "Sable de lagune", disponibilite: "Quincaillerie" },
              { nom: "Gravier 15/25 concassé", substitut_acceptable: "Gravier roulé de rivière", disponibilite: "Zone Industrielle" }
            ]
          },
          cout_estime_local: {
            gamme_prix: "Moyen",
            estimation_m2_fcfa: "12 000 - 18 000 FCFA par m² de dalle coulée",
            justification_economique: "Le choix du ciment CPJ 42.5 est non négociable pour la sécurité structurelle. Le coût s'explique par la qualité mécanique requise. Expliquez au client que rogner sur la qualité du ciment mettra sa famille en danger."
          },
          metadata: {
            tags_pathologies: ["fissure_structure", "infiltration_dalle", "fissure_dalle"],
            type_ouvrage: "Dallage"
          }
        };
      } else {
        const cleanTitle = activeDoc.title.replace(/^Import\s*:\s*/i, '').replace(/\.[^/.]+$/, '').replace(/[-_]/g, ' ');
        const capitalizedTitle = cleanTitle.charAt(0).toUpperCase() + cleanTitle.slice(1);

        generatedJson = {
          id: generatedId,
          norme_origine: {
            source: "Import",
            reference_article: "UPLOAD",
            titre_original: activeDoc.title,
            texte_brut: activeDoc.raw_text
          },
          alternative_prosartisan: {
            titre_vulgarise: capitalizedTitle,
            methode_execution: `Méthode vulgarisée générée pour le fichier importé : ${capitalizedTitle}. Assurer le respect des règles d'application locales pour les ouvrages décrits dans ce document.`,
            dosages_recommandes: [
              { element: "Ciment CPJ 32.5 R (CIMAF / LafargeHolcim)", ratio: "1 sac (50kg)", unite_mesure_locale: "Sac" },
              { element: "Sable fin propre", ratio: "2.5 brouettes", unite_mesure_locale: "Brouette" }
            ],
            materiaux_recommandes: [
              { nom: "Ciment CPJ 32.5 R", substitut_acceptable: "CPJ 42.5", disponibilite: "Quincaillerie" }
            ]
          },
          cout_estime_local: {
            gamme_prix: "Faible",
            estimation_m2_fcfa: "3 500 - 5 000 FCFA par m²",
            justification_economique: "Dosage optimisé pour limiter les coûts et assurer une bonne résistance locale."
          },
          metadata: {
            tags_pathologies: [cleanTitle.toLowerCase().replace(/\s+/g, '_').substring(0, 30)],
            type_ouvrage: "Maçonnerie"
          }
        };
      }

      try {
        // Write to staging DB
        await axios.post('/admin/api/llm/staging', {
          id: generatedId,
          raw_pdf_source: activeDoc.title,
          original_extracted_text: activeDoc.raw_text,
          generated_json: generatedJson,
          status: 'PENDING'
        });

        if (selectedDocId.startsWith("custom-")) {
          await axios.put(`/admin/api/llm/imports/${selectedDocId}`, {
            llm_downscaled: true,
            status: "INGESTED"
          });
        }

        addLog('llm-downscale', `Downscaling réussi. Fiche isolée en base de Staging avec ID : ${generatedId}`);
        fetchData();
      } catch (err: any) {
        addLog('system', `Erreur downscale : ${err.message}`);
      }
    }, 1200);
  };

  // Staging changes handlers
  const handleStagingChange = (fieldPath: string[], value: any) => {
    if (!selectedStagingItem) return;
    const updated = { ...selectedStagingItem };
    let current = updated.generated_json;
    for (let i = 0; i < fieldPath.length - 1; i++) {
      current = current[fieldPath[i]];
    }
    current[fieldPath[fieldPath.length - 1]] = value;
    setSelectedStagingItem(updated);
  };

  // Update Staging Item in DB
  const saveStagingItem = async () => {
    if (!selectedStagingItem) return;
    try {
      await axios.put(`/admin/api/llm/staging/${selectedStagingItem.id}`, selectedStagingItem.generated_json);
      addLog('system', `Fiche ${selectedStagingItem.id} sauvegardée localement.`);
      fetchData();
    } catch (err: any) {
      alert("Erreur lors de la sauvegarde : " + err.message);
    }
  };

  // Approve Staging Item to production vector db
  const approveStagingItem = async () => {
    if (!selectedStagingItem) return;
    try {
      await axios.post(`/admin/api/llm/staging/${selectedStagingItem.id}/approve`);
      addLog('system', `Fiche ${selectedStagingItem.id} APPROUVÉE et indexée dans l'index Vectoriel de Production.`);
      setSelectedStagingItem(null);
      fetchData();
    } catch (err: any) {
      alert("Erreur lors de l'approbation : " + err.message);
    }
  };

  // Reject Staging Item
  const rejectStagingItem = async () => {
    if (!selectedStagingItem) return;
    const reason = prompt("Veuillez saisir le motif du rejet :");
    if (reason === null) return;
    try {
      await axios.post(`/admin/api/llm/staging/${selectedStagingItem.id}/reject`, {
        reviewer_notes: reason
      });
      addLog('system', `Fiche ${selectedStagingItem.id} REJETÉE. Raison : ${reason}`);
      setSelectedStagingItem(null);
      fetchData();
    } catch (err: any) {
      alert("Erreur lors du rejet : " + err.message);
    }
  };

  // Delete Staging Item
  const deleteStagingItem = async () => {
    if (!selectedStagingItem) return;
    if (!confirm("Voulez-vous vraiment supprimer cette fiche de staging ? Cette action est irréversible.")) return;
    try {
      await axios.delete(`/admin/api/llm/staging/${selectedStagingItem.id}`);
      addLog('system', `Fiche ${selectedStagingItem.id} SUPPRIMÉE de la base de Staging.`);
      setSelectedStagingItem(null);
      fetchData();
    } catch (err: any) {
      alert("Erreur lors de la suppression : " + err.message);
    }
  };

  // Clear Ingestion History
  const clearIngestionHistory = async () => {
    if (!confirm("Voulez-vous vraiment vider tout l'historique d'ingestion ainsi que les fichiers téléversés ?")) return;
    try {
      await axios.delete('/admin/api/llm/imports');
      addLog('system', "Historique des ingestions et fichiers d'importation réinitialisés.");
      setCustomDocs([]);
      fetchData();
    } catch (err: any) {
      alert("Erreur lors de la réinitialisation : " + err.message);
    }
  };

  const isQueryInScope = (query: string) => {
    const keywords = [
      'dosage', 'ciment', 'beton', 'béton', 'dalle', 'mur', 'brique', 'agglo', 'sable', 'gravier',
      'enduit', 'plomb', 'tuyau', 'fuite', 'robinet', 'electr', 'électr', 'cable', 'câble', 'fil',
      'courant', 'prise', 'disjoncteur', 'peint', 'humid', 'fissur', 'infiltr', 'chantier', 'macon',
      'maçon', 'travaux', 'devis', 'renov', 'rénov', 'constru', 'batiment', 'bâtiment', 'carrel',
      'toit', 'charp', 'bois', 'fer', 'soud', 'prosartisan', 'referent', 'référent', 'jcode',
      'sequestre', 'séquestre', 'wallet', 'portland', 'cpj', 'mortier', 'gachage', 'gâchage',
      'lbtp', 'bnetd'
    ];
    const cleanQuery = query.toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "");
    return keywords.some(kw => cleanQuery.includes(kw));
  };

  // Send message inside Chat simulator
  const sendChatMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!chatMessage.trim()) return;

    const userText = chatMessage;
    setChatMessage("");
    setChatHistory(prev => [...prev, { sender: 'maçon', text: userText }]);
    setIsChatLoading(true);

    addLog('rag', `Simulation Mobile : Requête RAG reçue de l'artisan : "${userText}"`);

    if (!isQueryInScope(userText)) {
      setTimeout(() => {
        setIsChatLoading(false);
        const reply = "Désolé, cette question ne fait pas partie du périmètre fonctionnel de ProsArtisan.";
        setChatHistory(prev => [...prev, { sender: 'assistant', text: reply }]);
        addLog('rag', "Réponse hors-périmètre (Bloqué par filtre de scope).");
      }, 400);
      return;
    }

    // Offline simulation
    if (networkState === 'offline') {
      setTimeout(() => {
        setIsChatLoading(false);
        // Search in localCache
        const matches = localCache.filter(item => {
          const tags = item.metadata?.tags_pathologies || [];
          const title = item.alternative_prosartisan?.titre_vulgarise?.toLowerCase() || '';
          const userLower = userText.toLowerCase();
          return tags.some((t: string) => userLower.includes(t.toLowerCase()) || t.toLowerCase().includes(userLower)) || title.includes(userLower);
        });

        let reply = "";
        if (matches.length > 0) {
          const alt = matches[0].alternative_prosartisan;
          reply = `[MODE COUVERTURE HORS-LIGNE]\nBonjour Boss ! Trouvé dans le cache local :\n\n` +
            `**${alt.titre_vulgarise}**\n` +
            `**Méthode :** ${alt.methode_execution}\n\n` +
            `**Dosages :**\n` +
            alt.dosages_recommandes.map((d: any) => `- ${d.element} : ${d.ratio} (${d.unite_mesure_locale})`).join('\n');
        } else {
          reply = `[MODE COUVERTURE HORS-LIGNE]\nBonjour Boss ! Je n'ai pas trouvé de fiche technique correspondante dans mon cache local. Veille à utiliser du ciment CPJ 42.5 pour les structures porteuses (dalles/poteaux) et du CPJ 32.5 pour les enduits et agglos de remplissage.`;
        }

        setChatHistory(prev => [...prev, { sender: 'assistant', text: reply, offline: true }]);
        addLog('rag', "Réponse générée depuis le cache local (RAG Offline).");
      }, 500);
      return;
    }

    // Online/3G request
    try {
      const res = await axios.post('/admin/api/llm/chat', { message: userText });
      setIsChatLoading(false);
      setChatHistory(prev => [...prev, {
        sender: 'assistant',
        text: res.data.response,
        sources: res.data.sources
      }]);
      addLog('rag', `Réponse de l'assistant formulée via RAG ${networkState === '3g' ? '3G' : 'Fibre'} avec ${res.data.sources.length} document(s) source(s).`);
    } catch (err: any) {
      setIsChatLoading(false);
      addLog('rag', `Erreur de communication RAG : ${err.message}`);
      setChatHistory(prev => [...prev, { sender: 'assistant', text: "Erreur lors de la communication avec l'assistant." }]);
    }
  };

  return (
    <div className="bg-[#0b0f19] text-[#f3f4f6] min-h-[700px] p-6 font-sans antialiased rounded-[32px] shadow-lg border border-slate-800">

      {/* HEADER SECTION */}
      <div className="flex flex-col md:flex-row justify-between items-start md:items-center border-b border-slate-800 pb-4 mb-6 gap-4">
        <div>
          <div className="flex items-center gap-2">
            <span className="bg-amber-500/10 text-amber-400 text-xs px-2.5 py-1 rounded-full font-bold border border-amber-500/20 uppercase tracking-wider">
              ProsArtisan IA
            </span>
            <div className="h-2 w-2 rounded-full bg-green-500 animate-pulse"></div>
            <span className="text-[11px] text-green-400 font-bold uppercase tracking-wider">
              Service Local Actif
            </span>
          </div>
          <h1 className="text-2xl font-black text-white mt-1">Supervision LLM & Pipeline RAG</h1>
          <p className="text-xs text-slate-400 mt-0.5">Ingestion sémantique, validation Human-in-the-Loop et indexation vectorielle.</p>
        </div>

        <div className="flex items-center gap-2">
          <label className="bg-slate-800/80 hover:bg-slate-700/80 text-xs text-white font-bold px-4 py-2.5 rounded-full border border-slate-700 cursor-pointer transition-colors flex items-center gap-2">
            <span>📥 Importer Norme (PDF)</span>
            <input type="file" className="hidden" accept=".txt,.md,.pdf" onChange={handleFileUpload} />
          </label>
          <button
            onClick={fetchData}
            className="bg-slate-800/80 hover:bg-slate-700/80 text-xs text-amber-400 font-bold px-4 py-2.5 rounded-full border border-slate-700 transition-all flex items-center gap-2"
          >
            🔄 Actualiser la base
          </button>
        </div>
      </div>

      {/* UPLOAD PROGRESS BAR */}
      {uploadProgress !== null && (
        <div className="bg-slate-800/50 border border-slate-700 rounded-2xl p-4 mb-6">
          <div className="flex justify-between text-xs text-slate-300 font-bold mb-2">
            <span>Téléversement du document en cours...</span>
            <span>{uploadProgress}%</span>
          </div>
          <div className="w-full bg-slate-700 h-2 rounded-full overflow-hidden">
            <style>{`.upload-progress-fill { width: ${uploadProgress}% }`}</style>
            <div className="bg-amber-500 h-full transition-all duration-300 upload-progress-fill"></div>
          </div>
        </div>
      )}

      {/* CORE WORKSPACE GRID */}
      <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">

        {/* PANEL 1: INGESTION PIPELINE */}
        <div className="xl:col-span-2 space-y-6">

          <div className="bg-slate-900/60 border border-slate-800 rounded-3xl p-5 backdrop-blur-md">
            <div className="flex items-center gap-2 font-black text-white text-sm uppercase tracking-wider mb-3">
              <span className="text-amber-500">01.</span> Pipeline d'Ingestion Sémantique Localisée (PISL)
            </div>

            <p className="text-xs text-slate-400 mb-4 leading-relaxed">
              Sélectionnez une spécification technique brute. Utilisez le parsing VLM pour extraire les structures tabulaires, puis lancez le déclassement LLM pour générer des instructions adaptées aux quincailleries et dosages locaux.
            </p>

            <div className="flex flex-wrap gap-2 items-center mb-4">
              <select
                value={selectedDocId}
                onChange={(e) => setSelectedDocId(e.target.value)}
                aria-label="Sélectionner un document de spécification"
                className="bg-slate-800/90 text-sm text-white px-4 py-2 rounded-full border border-slate-700 outline-none focus:border-amber-500 max-w-full md:max-w-[320px]"
              >
                {allDocs.map(d => (
                  <option key={d.id} value={d.id}>{d.title}</option>
                ))}
              </select>

              <button
                onClick={runVlmParsing}
                disabled={isProcessingVlm || pipelineStep !== 'idle'}
                className={`text-xs font-bold px-4 py-2.5 rounded-full transition-all flex items-center gap-1.5 ${pipelineStep === 'idle'
                    ? 'bg-amber-500 text-slate-950 hover:bg-amber-400'
                    : 'bg-slate-800 text-slate-500 border border-slate-700 cursor-not-allowed'
                  }`}
              >
                {isProcessingVlm ? 'Extraction...' : '1. Lancer LlamaParse (VLM)'}
              </button>

              <button
                onClick={runLlmDownscaling}
                disabled={isProcessingLlm || pipelineStep !== 'vlm_done'}
                className={`text-xs font-bold px-4 py-2.5 rounded-full transition-all flex items-center gap-1.5 ${pipelineStep === 'vlm_done'
                    ? 'bg-indigo-600 text-white hover:bg-indigo-500'
                    : 'bg-slate-800 text-slate-500 border border-slate-700 cursor-not-allowed'
                  }`}
              >
                {isProcessingLlm ? 'Déclassement...' : '2. Downscaler la Norme (LLM)'}
              </button>
            </div>

            {/* PREVIEWS CONTAINER */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="bg-slate-950/80 border border-slate-800/80 rounded-2xl p-4">
                <div className="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-2 border-b border-slate-800 pb-1 flex justify-between">
                  <span>Source Originale Brut</span>
                  <span className="text-amber-500">PDF original</span>
                </div>
                <div className="text-xs text-slate-300 font-mono h-[140px] overflow-y-auto custom-scrollbar leading-relaxed">
                  {rawTextPreview || "Aucun texte disponible."}
                </div>
              </div>

              <div className="bg-slate-950/80 border border-slate-800/80 rounded-2xl p-4">
                <div className="text-[10px] text-purple-400 font-bold uppercase tracking-wider mb-2 border-b border-slate-800 pb-1 flex justify-between">
                  <span>Rendu Markdown extrait (VLM)</span>
                  <span>Table-Aware</span>
                </div>
                <div className="text-xs text-purple-300 font-mono h-[140px] overflow-y-auto custom-scrollbar leading-relaxed">
                  {vlmMarkdownPreview ? (
                    <pre className="whitespace-pre-wrap">{vlmMarkdownPreview}</pre>
                  ) : (
                    <span className="text-slate-600 italic">En attente de l'extraction VLM...</span>
                  )}
                </div>
              </div>
            </div>

            {/* INGSETION HISTORY TABLE */}
            <div className="mt-5 border-t border-slate-800 pt-4">
              <div className="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 flex justify-between items-center">
                <span>Historique de Suivi des Ingestions</span>
                <div className="flex gap-4 items-center">
                  <span>Base MySQL</span>
                  <button onClick={clearIngestionHistory} className="text-rose-500 hover:text-rose-400 font-bold transition-all normal-case font-sans text-xs">Vider l'historique 🗑️</button>
                </div>
              </div>
              <div className="max-h-[140px] overflow-y-auto custom-scrollbar border border-slate-800/60 rounded-xl">
                <table className="w-full text-left text-xs text-slate-300">
                  <thead>
                    <tr className="bg-slate-950 border-b border-slate-800 text-slate-400 font-bold sticky top-0">
                      <th className="p-2.5">Fichier</th>
                      <th className="p-2.5">Date</th>
                      <th className="p-2.5 text-center">VLM</th>
                      <th className="p-2.5 text-center">LLM</th>
                      <th className="p-2.5">Statut</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-800/50">
                    {importHistory.length === 0 ? (
                      <tr>
                        <td colSpan={5} className="p-4 text-center text-slate-500 italic">Aucun historique d'importation disponible.</td>
                      </tr>
                    ) : (
                      importHistory.map(row => (
                        <tr key={row.id} className="hover:bg-slate-900/40">
                          <td className="p-2.5 font-medium max-w-[200px] truncate">{row.filename}</td>
                          <td className="p-2.5 text-slate-400">{row.imported_at}</td>
                          <td className="p-2.5 text-center">{row.vlm_extracted ? '✅' : '⏳'}</td>
                          <td className="p-2.5 text-center">{row.llm_downscaled ? '✅' : '⏳'}</td>
                          <td className="p-2.5">
                            <span className={`text-[10px] px-2 py-0.5 rounded-full font-bold uppercase ${row.status === 'INGESTED' ? 'bg-green-500/10 text-green-400' : 'bg-amber-500/10 text-amber-400'
                              }`}>
                              {row.status}
                            </span>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          {/* PANEL 2: HUMAN IN THE LOOP (STAGING) */}
          <div className="bg-slate-900/60 border border-slate-800 rounded-3xl p-5 backdrop-blur-md">
            <div className="flex items-center gap-2 font-black text-white text-sm uppercase tracking-wider mb-3">
              <span className="text-pink-500">02.</span> Base de Staging (Validation Humaine)
            </div>

            <p className="text-xs text-slate-400 mb-4 leading-relaxed">
              Toutes les propositions déclassées par l'IA arrivent ici. Modifiez les ratios de dosages, ajustez les prix et validez l'indexation finale pour l'application mobile.
            </p>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 min-h-[260px]">

              {/* STAGING SIDEBAR */}
              <div className="bg-slate-950/70 border border-slate-800 rounded-2xl p-3 overflow-y-auto max-h-[300px] custom-scrollbar">
                <div className="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-2">Fiches Staging ({stagingItems.length})</div>
                <div className="space-y-1">
                  {stagingItems.length === 0 ? (
                    <div className="text-slate-600 text-xs italic p-4 text-center">Aucune fiche à valider.</div>
                  ) : (
                    stagingItems.map(item => {
                      const alt = item.generated_json?.alternative_prosartisan || {};
                      const isSelected = selectedStagingItem?.id === item.id;
                      return (
                        <button
                          key={item.id}
                          onClick={() => setSelectedStagingItem(item)}
                          className={`w-full text-left p-2.5 rounded-xl transition-all border text-xs ${isSelected
                              ? 'bg-amber-500/10 border-amber-500 text-white font-bold'
                              : 'bg-slate-900/40 border-slate-800/80 text-slate-300 hover:bg-slate-800/30'
                            }`}
                        >
                          <div className="truncate font-semibold">{alt.titre_vulgarise || "Fiche sans titre"}</div>
                          <div className="text-[10px] text-slate-500 mt-0.5 flex justify-between">
                            <span>ID: {item.id}</span>
                            <span className="font-bold text-pink-400">{item.status}</span>
                          </div>
                        </button>
                      );
                    })
                  )}
                </div>
              </div>

              {/* STAGING DETAILS EDITOR */}
              <div className="md:col-span-2 bg-slate-950/40 border border-slate-800 rounded-2xl p-4 overflow-y-auto max-h-[300px] custom-scrollbar">
                {selectedStagingItem ? (
                  <div className="space-y-4 text-xs">

                    <div className="flex justify-between items-center border-b border-slate-800 pb-2 mb-2">
                      <span className="font-bold text-slate-200">DÉTAIL DE LA FICHE : {selectedStagingItem.id}</span>
                      <div className="flex gap-1.5">
                        <button onClick={saveStagingItem} className="bg-slate-800 hover:bg-slate-700 text-white px-3 py-1 rounded font-bold">Sauvegarder</button>
                        <button onClick={approveStagingItem} className="bg-green-600 hover:bg-green-500 text-white px-3 py-1 rounded font-bold">Approuver ✅</button>
                        <button onClick={rejectStagingItem} className="bg-red-900 hover:bg-red-800 text-white px-3 py-1 rounded font-bold">Rejeter ❌</button>
                        <button onClick={deleteStagingItem} className="bg-rose-700 hover:bg-rose-600 text-white px-3 py-1 rounded font-bold">Supprimer 🗑️</button>
                      </div>
                    </div>

                    <div>
                      <label htmlFor="titre-vulgarise" className="block text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1">Titre Vulgarisé</label>
                      <input
                        id="titre-vulgarise"
                        type="text"
                        value={selectedStagingItem.generated_json.alternative_prosartisan.titre_vulgarise}
                        onChange={(e) => handleStagingChange(['alternative_prosartisan', 'titre_vulgarise'], e.target.value)}
                        className="w-full bg-slate-900 border border-slate-800 rounded px-3 py-1.5 text-white outline-none focus:border-amber-500 font-medium"
                      />
                    </div>

                    <div>
                      <label htmlFor="methode-execution" className="block text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1">Méthodologie d'exécution locale</label>
                      <textarea
                        id="methode-execution"
                        rows={3}
                        value={selectedStagingItem.generated_json.alternative_prosartisan.methode_execution}
                        onChange={(e) => handleStagingChange(['alternative_prosartisan', 'methode_execution'], e.target.value)}
                        className="w-full bg-slate-900 border border-slate-800 rounded px-3 py-1.5 text-white outline-none focus:border-amber-500 leading-relaxed font-mono"
                      />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label htmlFor="cout-estime-local" className="block text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1">Estimation Coût local (FCFA)</label>
                        <input
                          id="cout-estime-local"
                          type="text"
                          value={selectedStagingItem.generated_json.cout_estime_local.estimation_m2_fcfa}
                          onChange={(e) => handleStagingChange(['cout_estime_local', 'estimation_m2_fcfa'], e.target.value)}
                          className="w-full bg-slate-900 border border-slate-800 rounded px-3 py-1.5 text-white outline-none"
                        />
                      </div>
                      <div>
                        <label htmlFor="gamme-prix" className="block text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1">Gamme de Prix</label>
                        <select
                          id="gamme-prix"
                          value={selectedStagingItem.generated_json.cout_estime_local.gamme_prix}
                          onChange={(e) => handleStagingChange(['cout_estime_local', 'gamme_prix'], e.target.value)}
                          className="w-full bg-slate-900 border border-slate-800 rounded px-3 py-1.5 text-white outline-none"
                        >
                          <option value="Faible">Faible</option>
                          <option value="Moyen">Moyen</option>
                          <option value="Eleve">Élevé</option>
                        </select>
                      </div>
                    </div>

                    <div>
                      <label htmlFor="justification-economique" className="block text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1">Justification Économique (Argumentaire Maçon)</label>
                      <textarea
                        id="justification-economique"
                        rows={2}
                        value={selectedStagingItem.generated_json.cout_estime_local.justification_economique}
                        onChange={(e) => handleStagingChange(['cout_estime_local', 'justification_economique'], e.target.value)}
                        className="w-full bg-slate-900 border border-slate-800 rounded px-3 py-1.5 text-white outline-none"
                      />
                    </div>
                  </div>
                ) : (
                  <div className="text-slate-500 italic text-center py-16 text-xs">Sélectionnez une fiche de Staging à gauche pour l'examiner et la valider.</div>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* PANEL 3: UNDER THE HOOD LOGS & MOBILE SIMULATOR */}
        <div className="space-y-6">

          {/* UNDER THE HOOD TERMINAL LOGS */}
          <div className="bg-slate-950 border border-slate-800 rounded-3xl p-4">
            <div className="text-[10px] font-bold text-slate-400 uppercase tracking-widest border-b border-slate-800 pb-2 mb-3 flex justify-between items-center">
              <span>Logs du système IA</span>
              <button onClick={() => setLogs([])} className="text-xs text-amber-500 hover:underline">Effacer</button>
            </div>
            <div className="h-[140px] overflow-y-auto custom-scrollbar font-mono text-[10px] leading-relaxed space-y-1">
              {logs.length === 0 ? (
                <div className="text-slate-700 italic">Aucun événement enregistré.</div>
              ) : (
                logs.map((log, index) => (
                  <div key={index} className="flex gap-2">
                    <span className="text-slate-600">[{log.timestamp}]</span>
                    <span className={`font-bold ${log.category === 'vlm' ? 'text-purple-400' :
                        log.category === 'llm-downscale' ? 'text-indigo-400' :
                          log.category === 'rag' ? 'text-cyan-400' : 'text-slate-400'
                      }`}>
                      {log.category.toUpperCase()}
                    </span>
                    <span className="text-slate-300">{log.message}</span>
                  </div>
                ))
              )}
              <div ref={logsEndRef} />
            </div>
          </div>

          {/* MOBILE SIMULATOR */}
          <div className="bg-slate-900/40 border border-slate-800 rounded-3xl p-5">
            <div className="text-xs font-bold text-slate-300 mb-2 flex justify-between items-center">
              <span>📱 Simulateur Mobile (RAG Test)</span>

              {/* NETWORK CONTROL SIMULATOR */}
              <div className="flex bg-slate-950 p-1 rounded-full border border-slate-800 text-[10px]">
                <button
                  onClick={() => setNetworkState('wifi')}
                  className={`px-2 py-0.5 rounded-full font-semibold transition-colors ${networkState === 'wifi' ? 'bg-amber-500 text-slate-950' : 'text-slate-400'}`}
                >
                  Fibre
                </button>
                <button
                  onClick={() => setNetworkState('3g')}
                  className={`px-2 py-0.5 rounded-full font-semibold transition-colors ${networkState === '3g' ? 'bg-amber-500 text-slate-950' : 'text-slate-400'}`}
                >
                  3G
                </button>
                <button
                  onClick={() => setNetworkState('offline')}
                  className={`px-2 py-0.5 rounded-full font-semibold transition-colors ${networkState === 'offline' ? 'bg-amber-500 text-slate-950' : 'text-slate-400'}`}
                >
                  Hors-ligne
                </button>
              </div>
            </div>

            {/* MOCKUP PHONE SHELL */}
            <div className="bg-[#0f172a] rounded-[2.5rem] border-4 border-slate-800 p-3 shadow-2xl relative">
              <div className="bg-black/80 rounded-[2rem] p-3 min-h-[380px] flex flex-col justify-between">

                {/* STATUS BAR */}
                <div className="flex justify-between items-center text-[10px] text-slate-400 font-bold px-2 py-1">
                  <span>14:04</span>
                  <div className="flex gap-1.5 items-center">
                    <span>{networkState === 'wifi' ? '📶' : networkState === '3g' ? '📶 3G' : '✈️ Offline'}</span>
                    <span>🔋 92%</span>
                  </div>
                </div>

                {/* CONVERSATION AREA */}
                <div className="flex-1 overflow-y-auto custom-scrollbar my-2 px-1 space-y-3 max-h-[280px]">
                  <div className="bg-slate-800/80 rounded-2xl p-2.5 text-[11px] leading-relaxed max-w-[85%] text-slate-200">
                    Salut Boss ! Je suis ton assistant RAG. Tu peux me poser des questions de dosages ou de maçonnerie pour tes devis.
                  </div>

                  {chatHistory.map((msg, index) => (
                    <div
                      key={index}
                      className={`flex flex-col ${msg.sender === 'maçon' ? 'items-end' : 'items-start'}`}
                    >
                      <div className={`rounded-2xl p-2.5 text-[11px] leading-relaxed max-w-[85%] ${msg.sender === 'maçon'
                          ? 'bg-amber-500 text-slate-950 font-medium'
                          : 'bg-slate-800 text-slate-200 border border-slate-700/60'
                        }`}>
                        {msg.offline && <div className="text-[9px] text-amber-400 font-bold uppercase tracking-wider mb-1">Cache Local (Offline)</div>}
                        <div className="whitespace-pre-wrap">{msg.text}</div>
                      </div>

                      {msg.sources && msg.sources.length > 0 && (
                        <div className="text-[8px] text-slate-500 mt-1 px-1 flex gap-1 font-mono">
                          Sources RAG : {msg.sources.map((s: any) => `[${s.title}]`).join(', ')}
                        </div>
                      )}
                    </div>
                  ))}

                  {isChatLoading && (
                    <div className="bg-slate-800/80 rounded-2xl p-2.5 text-[11px] max-w-[50px] text-center text-slate-400 animate-pulse">
                      ...
                    </div>
                  )}
                </div>

                {/* INPUT FORM */}
                <form onSubmit={sendChatMessage} className="flex gap-1.5 mt-2">
                  <input
                    type="text"
                    placeholder="Pose ta question (ex: fissure)..."
                    value={chatMessage}
                    onChange={(e) => setChatMessage(e.target.value)}
                    className="flex-1 bg-slate-900 border border-slate-700 text-xs text-white rounded-full px-4 py-2 outline-none focus:border-amber-500 placeholder-slate-500"
                  />
                  <button type="submit" className="bg-amber-500 hover:bg-amber-400 text-slate-950 p-2 rounded-full text-xs transition-colors">
                    ➡️
                  </button>
                </form>

              </div>
            </div>

            <div className="mt-3 flex justify-between items-center text-[10px] text-slate-500 font-bold uppercase">
              <span>Production Vector DB : {productionItems.length} fiches</span>
              <button
                onClick={() => { setChatHistory([]); addLog('rag', "Historique du chat vidé."); }}
                className="text-red-400 hover:underline cursor-pointer bg-transparent border-0"
              >
                Vider le chat
              </button>
            </div>
          </div>
        </div>
      </div>

    </div>
  );
}
