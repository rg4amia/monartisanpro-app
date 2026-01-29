/// French localization strings for ProSartisan Platform
class AppStrings {
  // Auth Screens
  static const String appName = 'ProSartisan';
  static const String login = 'Connexion';
  static const String register = 'Inscription';
  static const String email = 'Email';
  static const String password = 'Mot de passe';
  static const String confirmPassword = 'Confirmer le mot de passe';
  static const String phoneNumber = 'Numéro de téléphone';
  static const String forgotPassword = 'Mot de passe oublié?';
  static const String dontHaveAccount = 'Vous n\'avez pas de compte?';
  static const String alreadyHaveAccount = 'Vous avez déjà un compte?';
  static const String signIn = 'Se connecter';
  static const String signUp = 'S\'inscrire';

  // User Types
  static const String selectUserType = 'Sélectionnez votre type de compte';
  static const String client = 'Client';
  static const String artisan = 'Artisan';
  static const String fournisseur = 'Fournisseur';
  static const String clientDescription = 'Je cherche des artisans qualifiés';
  static const String artisanDescription = 'Je suis un artisan professionnel';
  static const String fournisseurDescription =
      'Je fournis des matériaux de construction';

  // Trade Categories
  static const String tradeCategory = 'Catégorie de métier';
  static const String plumber = 'Plombier';
  static const String electrician = 'Électricien';
  static const String mason = 'Maçon';

  // KYC
  static const String kycVerification = 'Vérification KYC';
  static const String kycRequired = 'Documents KYC requis';
  static const String idType = 'Type de pièce d\'identité';
  static const String cni = 'CNI';
  static const String passport = 'Passeport';
  static const String idNumber = 'Numéro de pièce';
  static const String uploadIdDocument = 'Télécharger la pièce d\'identité';
  static const String uploadSelfie = 'Télécharger un selfie';
  static const String takePhoto = 'Prendre une photo';
  static const String chooseFromGallery = 'Choisir depuis la galerie';
  static const String submit = 'Soumettre';

  // OTP
  static const String otpVerification = 'Vérification OTP';
  static const String enterOtp = 'Entrez le code OTP';
  static const String otpSentTo = 'Code envoyé à';
  static const String resendOtp = 'Renvoyer le code';
  static const String verify = 'Vérifier';

  // Business Info
  static const String businessName = 'Nom de l\'entreprise';
  static const String shopLocation = 'Emplacement du magasin';

  // Validation Messages
  static const String emailRequired = 'Email requis';
  static const String emailInvalid = 'Email invalide';
  static const String passwordRequired = 'Mot de passe requis';
  static const String passwordTooShort =
      'Le mot de passe doit contenir au moins 8 caractères';
  static const String passwordsDoNotMatch =
      'Les mots de passe ne correspondent pas';
  static const String phoneRequired = 'Numéro de téléphone requis';
  static const String phoneInvalid = 'Numéro de téléphone invalide';
  static const String tradeCategoryRequired = 'Catégorie de métier requise';
  static const String businessNameRequired = 'Nom de l\'entreprise requis';
  static const String idTypeRequired = 'Type de pièce d\'identité requis';
  static const String idNumberRequired = 'Numéro de pièce requis';
  static const String idDocumentRequired = 'Document d\'identité requis';
  static const String selfieRequired = 'Selfie requis';

  // Error Messages
  static const String loginFailed = 'Échec de la connexion';
  static const String registrationFailed = 'Échec de l\'inscription';
  static const String networkError = 'Erreur réseau. Veuillez réessayer.';
  static const String unknownError = 'Une erreur s\'est produite';
  static const String accountLocked =
      'Compte verrouillé. Réessayez dans 15 minutes.';
  static const String invalidCredentials = 'Email ou mot de passe incorrect';
  static const String otpInvalid = 'Code OTP invalide';
  static const String otpExpired = 'Code OTP expiré';

  // Success Messages
  static const String loginSuccess = 'Connexion réussie';
  static const String registrationSuccess = 'Inscription réussie';
  static const String otpSent = 'Code OTP envoyé';
  static const String otpVerified = 'Code OTP vérifié';
  static const String kycSubmitted = 'Documents KYC soumis avec succès';

  // General
  static const String loading = 'Chargement...';
  static const String next = 'Suivant';
  static const String back = 'Retour';
  static const String cancel = 'Annuler';
  static const String ok = 'OK';
  static const String skip = 'Passer';

  // API
  static const String apiBaseUrl = 'https://prosartisan.net/api/v1';
}
