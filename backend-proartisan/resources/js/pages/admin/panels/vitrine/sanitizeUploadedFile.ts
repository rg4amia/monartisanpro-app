/**
 * Normalise le nom d'un fichier téléversé (accents et caractères spéciaux
 * remplacés) avant l'envoi au serveur.
 */
export function sanitizeUploadedFile(file: File | null): File | null {
    if (!file) return null;
    const cleanName = file.name
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-zA-Z0-9._-]/g, '_');
    return new File([file], cleanName, { type: file.type });
}
