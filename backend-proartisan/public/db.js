/**
 * db.js
 * Client de base de données asynchrone pour le prototype ProsArtisan IA.
 * Communique avec le serveur API Python (server.py) connecté à la base SQLite.
 */

export const INITIAL_PATHOLOGY_PRESETS = [
  {
    id: "pathology-1",
    title: "Remontée capillaire sévère",
    image: "https://images.unsplash.com/photo-1584622650111-993a426fbf0a?auto=format&fit=crop&w=400&q=80",
    description: "Humidité ascensionnelle qui fait sauter l'enduit et la peinture sur le bas des murs en parpaings creux de 15 cm.",
    tags: ["remontee_capillaire", "humidite_bas", "salpetre", "parpaing_creux"]
  },
  {
    id: "pathology-2",
    title: "Fissure de flexion sur linteau",
    image: "https://images.unsplash.com/photo-1590069261209-f8e9b8642343?auto=format&fit=crop&w=400&q=80",
    description: "Fissure horizontale ou oblique à la base d'un linteau en béton armé au-dessus d'une porte ou fenêtre.",
    tags: ["fissure_structure", "linteau_beton", "cisaillement", "ferraillage"]
  },
  {
    id: "pathology-3",
    title: "Infiltration sur dalle terrasse",
    image: "https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=400&q=80",
    description: "Infiltration d'eau de pluie à travers une dalle de toiture-terrasse non étanchéifiée ou fissurée.",
    tags: ["infiltration_dalle", "toit_terrasse", "etancheite_defaillante", "fissure_dalle"]
  }
];

class APIDatabaseClient {
  // --- AUTH UTILS ---
  getHeaders() {
    const token = localStorage.getItem("auth_token");
    return {
      "Content-Type": "application/json",
      ...(token ? { "Authorization": `Bearer ${token}` } : {})
    };
  }

  handleResponse(res) {
    if (res.status === 401) {
      localStorage.removeItem("auth_token");
      localStorage.removeItem("user_email");
      if (!window.location.pathname.endsWith("login.html")) {
        if (window.location.pathname.includes("client")) {
          window.location.href = "login.html?redirect=client.html";
        } else {
          window.location.href = "login.html";
        }
      }
      throw new Error("Session expirée. Veuillez vous reconnecter.");
    }
    return res;
  }

  apiFetch(path, options = {}) {
    let baseUrl = window.API_BASE_URL || "";
    let cleanPath = path;

    if (baseUrl.endsWith('/api/v1') && cleanPath.startsWith('/api/v1')) {
      cleanPath = cleanPath.substring('/api/v1'.length);
    } else if (baseUrl.endsWith('/api/v1') && cleanPath.startsWith('/api/')) {
      cleanPath = cleanPath.substring('/api'.length);
    }

    const url = baseUrl + cleanPath;
    return fetch(url, options);
  }

  // --- AUTH API ---
  async register(email, password) {
    try {
      const res = await this.apiFetch("/api/auth/register", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password })
      });
      return await res.json();
    } catch (err) {
      console.error("Erreur register:", err);
      return { error: err.message };
    }
  }

  async login(email, password) {
    try {
      const res = await this.apiFetch("/api/auth/login", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, password })
      });
      const data = await res.json();
      if (data.token) {
        localStorage.setItem("auth_token", data.token);
        localStorage.setItem("user_email", data.email);
      }
      return data;
    } catch (err) {
      console.error("Erreur login:", err);
      return { error: err.message };
    }
  }

  async forgotPassword(email) {
    try {
      const res = await this.apiFetch("/api/auth/forgot-password", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email })
      });
      return await res.json();
    } catch (err) {
      console.error("Erreur forgot-password:", err);
      return { error: err.message };
    }
  }

  async resetPassword(email, resetToken, newPassword) {
    try {
      const res = await this.apiFetch("/api/auth/reset-password", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, reset_token: resetToken, new_password: newPassword })
      });
      return await res.json();
    } catch (err) {
      console.error("Erreur reset-password:", err);
      return { error: err.message };
    }
  }

  async verifyToken() {
    const token = localStorage.getItem("auth_token");
    if (!token) return false;
    try {
      const res = await this.apiFetch("/api/auth/me", {
        headers: this.getHeaders()
      });
      if (res.status === 200) {
        return true;
      } else {
        localStorage.removeItem("auth_token");
        localStorage.removeItem("user_email");
        return false;
      }
    } catch (err) {
      console.error("Erreur verification token:", err);
      return false;
    }
  }

  async oauth2Callback(email, code) {
    try {
      const res = await this.apiFetch("/api/auth/oauth2/callback", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email, code })
      });
      const data = await res.json();
      if (data.token) {
        localStorage.setItem("auth_token", data.token);
        localStorage.setItem("user_email", data.email);
      }
      return data;
    } catch (err) {
      console.error("Erreur oauth2-callback:", err);
      return { error: err.message };
    }
  }

  // --- STAGING DB ---
  async getStagingItems() {
    try {
      const res = await this.apiFetch("/api/staging", { headers: this.getHeaders() });
      this.handleResponse(res);
      if (!res.ok) throw new Error("Erreur réseau");
      return await res.json();
    } catch (err) {
      console.error("Impossible de récupérer les fiches en staging:", err);
      return [];
    }
  }

  async addStagingItem(item) {
    try {
      const res = await this.apiFetch("/api/staging", {
        method: "POST",
        headers: this.getHeaders(),
        body: JSON.stringify(item)
      });
      this.handleResponse(res);
      return await res.json();
    } catch (err) {
      console.error("Impossible d'ajouter la fiche en staging:", err);
      return null;
    }
  }

  async updateStagingItem(id, updatedJson) {
    try {
      const res = await this.apiFetch(`/api/staging/${id}`, {
        method: "PUT",
        headers: this.getHeaders(),
        body: JSON.stringify(updatedJson)
      });
      this.handleResponse(res);
      return await res.json();
    } catch (err) {
      console.error("Impossible de modifier la fiche en staging:", err);
      return null;
    }
  }

  async approveStagingItem(id) {
    try {
      const res = await this.apiFetch(`/api/staging/${id}/approve`, {
        method: "POST",
        headers: this.getHeaders()
      });
      this.handleResponse(res);
      return await res.json();
    } catch (err) {
      console.error("Impossible d'approuver la fiche:", err);
      return null;
    }
  }

  async rejectStagingItem(id, reviewerNotes = "") {
    try {
      const res = await this.apiFetch(`/api/staging/${id}/reject`, {
        method: "POST",
        headers: this.getHeaders(),
        body: JSON.stringify({ reviewer_notes: reviewerNotes })
      });
      this.handleResponse(res);
      return await res.json();
    } catch (err) {
      console.error("Impossible de rejeter la fiche:", err);
      return null;
    }
  }

  // --- PRODUCTION VECTOR DB ---
  async getProductionItems() {
    try {
      const res = await this.apiFetch("/api/production", { headers: this.getHeaders() });
      this.handleResponse(res);
      if (!res.ok) throw new Error("Erreur réseau");
      return await res.json();
    } catch (err) {
      console.error("Impossible de récupérer les fiches en production:", err);
      return [];
    }
  }

  /**
   * Effectue la recherche hybride sémantique + filtres via le serveur Python SQLite
   */
  async hybridSearch(queryTags, metadataFilters = {}, imageB64 = null, imageUrl = null, imageName = null) {
    const res = await this.apiFetch("/api/v1/search", {
      method: "POST",
      headers: this.getHeaders(),
      body: JSON.stringify({
        tags: queryTags,
        filters: metadataFilters,
        image_b64: imageB64,
        image_url: imageUrl,
        image_name: imageName
      })
    });
    if (!res.ok) {
      throw new Error(`Erreur serveur (${res.status})`);
    }
    return await res.json();
  }

  async sendChatMessage(message, history = []) {
    try {
      const res = await this.apiFetch("/api/v1/chat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          message: message,
          history: history
        })
      });
      return await res.json();
    } catch (err) {
      console.error("Erreur chatbot API:", err);
      return { error: err.message, response: "Désolé Boss, mon serveur de chat est inaccessible." };
    }
  }

  async getImportHistory() {
    try {
      const res = await this.apiFetch("/api/imports", { headers: this.getHeaders() });
      this.handleResponse(res);
      if (!res.ok) throw new Error("Erreur réseau");
      return await res.json();
    } catch (err) {
      console.error("Impossible de récupérer l'historique des imports:", err);
      return [];
    }
  }

  async logImport(item) {
    try {
      const res = await this.apiFetch("/api/imports", {
        method: "POST",
        headers: this.getHeaders(),
        body: JSON.stringify(item)
      });
      this.handleResponse(res);
      return await res.json();
    } catch (err) {
      console.error("Impossible d'historiser l'importation:", err);
      return null;
    }
  }

  async updateImportStatus(id, updates) {
    try {
      const res = await this.apiFetch(`/api/imports/${id}`, {
        method: "PUT",
        headers: this.getHeaders(),
        body: JSON.stringify(updates)
      });
      this.handleResponse(res);
      return await res.json();
    } catch (err) {
      console.error("Impossible de mettre à jour le statut de l'import:", err);
      return null;
    }
  }
}

export const dbInstance = new APIDatabaseClient();
