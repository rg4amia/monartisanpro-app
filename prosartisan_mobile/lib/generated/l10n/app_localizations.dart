import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_en.dart';
import 'app_localizations_fr.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('en'),
    Locale('fr'),
  ];

  /// The title of the application
  ///
  /// In fr, this message translates to:
  /// **'ProSartisan'**
  String get appTitle;

  /// No description provided for @welcome.
  ///
  /// In fr, this message translates to:
  /// **'Bienvenue'**
  String get welcome;

  /// No description provided for @login.
  ///
  /// In fr, this message translates to:
  /// **'Connexion'**
  String get login;

  /// No description provided for @register.
  ///
  /// In fr, this message translates to:
  /// **'S\'inscrire'**
  String get register;

  /// No description provided for @logout.
  ///
  /// In fr, this message translates to:
  /// **'Déconnexion'**
  String get logout;

  /// No description provided for @email.
  ///
  /// In fr, this message translates to:
  /// **'Adresse e-mail'**
  String get email;

  /// No description provided for @password.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe'**
  String get password;

  /// No description provided for @confirmPassword.
  ///
  /// In fr, this message translates to:
  /// **'Confirmer le mot de passe'**
  String get confirmPassword;

  /// No description provided for @firstName.
  ///
  /// In fr, this message translates to:
  /// **'Prénom'**
  String get firstName;

  /// No description provided for @lastName.
  ///
  /// In fr, this message translates to:
  /// **'Nom'**
  String get lastName;

  /// No description provided for @phoneNumber.
  ///
  /// In fr, this message translates to:
  /// **'Numéro de téléphone'**
  String get phoneNumber;

  /// No description provided for @forgotPassword.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe oublié ?'**
  String get forgotPassword;

  /// No description provided for @rememberMe.
  ///
  /// In fr, this message translates to:
  /// **'Se souvenir de moi'**
  String get rememberMe;

  /// No description provided for @dontHaveAccount.
  ///
  /// In fr, this message translates to:
  /// **'Vous n\'avez pas de compte ?'**
  String get dontHaveAccount;

  /// No description provided for @alreadyHaveAccount.
  ///
  /// In fr, this message translates to:
  /// **'Vous avez déjà un compte ?'**
  String get alreadyHaveAccount;

  /// No description provided for @userTypes.
  ///
  /// In fr, this message translates to:
  /// **'Types d\'utilisateur'**
  String get userTypes;

  /// No description provided for @client.
  ///
  /// In fr, this message translates to:
  /// **'Client'**
  String get client;

  /// No description provided for @artisan.
  ///
  /// In fr, this message translates to:
  /// **'Artisan'**
  String get artisan;

  /// No description provided for @supplier.
  ///
  /// In fr, this message translates to:
  /// **'Fournisseur'**
  String get supplier;

  /// No description provided for @selectUserType.
  ///
  /// In fr, this message translates to:
  /// **'Sélectionnez votre type d\'utilisateur'**
  String get selectUserType;

  /// No description provided for @categories.
  ///
  /// In fr, this message translates to:
  /// **'Catégories'**
  String get categories;

  /// No description provided for @plumber.
  ///
  /// In fr, this message translates to:
  /// **'Plombier'**
  String get plumber;

  /// No description provided for @electrician.
  ///
  /// In fr, this message translates to:
  /// **'Électricien'**
  String get electrician;

  /// No description provided for @mason.
  ///
  /// In fr, this message translates to:
  /// **'Maçon'**
  String get mason;

  /// No description provided for @selectCategory.
  ///
  /// In fr, this message translates to:
  /// **'Sélectionnez votre catégorie'**
  String get selectCategory;

  /// No description provided for @missions.
  ///
  /// In fr, this message translates to:
  /// **'Missions'**
  String get missions;

  /// No description provided for @createMission.
  ///
  /// In fr, this message translates to:
  /// **'Créer une mission'**
  String get createMission;

  /// No description provided for @missionTitle.
  ///
  /// In fr, this message translates to:
  /// **'Titre de la mission'**
  String get missionTitle;

  /// No description provided for @missionDescription.
  ///
  /// In fr, this message translates to:
  /// **'Description de la mission'**
  String get missionDescription;

  /// No description provided for @location.
  ///
  /// In fr, this message translates to:
  /// **'Localisation'**
  String get location;

  /// No description provided for @budget.
  ///
  /// In fr, this message translates to:
  /// **'Budget'**
  String get budget;

  /// No description provided for @budgetMin.
  ///
  /// In fr, this message translates to:
  /// **'Budget minimum'**
  String get budgetMin;

  /// No description provided for @budgetMax.
  ///
  /// In fr, this message translates to:
  /// **'Budget maximum'**
  String get budgetMax;

  /// No description provided for @currency.
  ///
  /// In fr, this message translates to:
  /// **'FCFA'**
  String get currency;

  /// No description provided for @submit.
  ///
  /// In fr, this message translates to:
  /// **'Soumettre'**
  String get submit;

  /// No description provided for @cancel.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get cancel;

  /// No description provided for @save.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer'**
  String get save;

  /// No description provided for @delete.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer'**
  String get delete;

  /// No description provided for @edit.
  ///
  /// In fr, this message translates to:
  /// **'Modifier'**
  String get edit;

  /// No description provided for @view.
  ///
  /// In fr, this message translates to:
  /// **'Voir'**
  String get view;

  /// No description provided for @quotes.
  ///
  /// In fr, this message translates to:
  /// **'Devis'**
  String get quotes;

  /// No description provided for @submitQuote.
  ///
  /// In fr, this message translates to:
  /// **'Soumettre un devis'**
  String get submitQuote;

  /// No description provided for @quoteAmount.
  ///
  /// In fr, this message translates to:
  /// **'Montant du devis'**
  String get quoteAmount;

  /// No description provided for @materialsAmount.
  ///
  /// In fr, this message translates to:
  /// **'Montant matériaux'**
  String get materialsAmount;

  /// No description provided for @laborAmount.
  ///
  /// In fr, this message translates to:
  /// **'Montant main-d\'œuvre'**
  String get laborAmount;

  /// No description provided for @quoteDescription.
  ///
  /// In fr, this message translates to:
  /// **'Description du devis'**
  String get quoteDescription;

  /// No description provided for @acceptQuote.
  ///
  /// In fr, this message translates to:
  /// **'Accepter le devis'**
  String get acceptQuote;

  /// No description provided for @rejectQuote.
  ///
  /// In fr, this message translates to:
  /// **'Rejeter le devis'**
  String get rejectQuote;

  /// No description provided for @quotesReceived.
  ///
  /// In fr, this message translates to:
  /// **'Devis reçus'**
  String get quotesReceived;

  /// No description provided for @noQuotesYet.
  ///
  /// In fr, this message translates to:
  /// **'Aucun devis reçu pour le moment'**
  String get noQuotesYet;

  /// No description provided for @worksite.
  ///
  /// In fr, this message translates to:
  /// **'Chantier'**
  String get worksite;

  /// No description provided for @milestones.
  ///
  /// In fr, this message translates to:
  /// **'Jalons'**
  String get milestones;

  /// No description provided for @submitProof.
  ///
  /// In fr, this message translates to:
  /// **'Soumettre une preuve'**
  String get submitProof;

  /// No description provided for @validateMilestone.
  ///
  /// In fr, this message translates to:
  /// **'Valider le jalon'**
  String get validateMilestone;

  /// No description provided for @contestMilestone.
  ///
  /// In fr, this message translates to:
  /// **'Contester le jalon'**
  String get contestMilestone;

  /// No description provided for @takePhoto.
  ///
  /// In fr, this message translates to:
  /// **'Prendre une photo'**
  String get takePhoto;

  /// No description provided for @uploadPhoto.
  ///
  /// In fr, this message translates to:
  /// **'Télécharger une photo'**
  String get uploadPhoto;

  /// No description provided for @photoRequired.
  ///
  /// In fr, this message translates to:
  /// **'Photo requise'**
  String get photoRequired;

  /// No description provided for @gpsRequired.
  ///
  /// In fr, this message translates to:
  /// **'Localisation GPS requise'**
  String get gpsRequired;

  /// No description provided for @payments.
  ///
  /// In fr, this message translates to:
  /// **'Paiements'**
  String get payments;

  /// No description provided for @escrow.
  ///
  /// In fr, this message translates to:
  /// **'Séquestre'**
  String get escrow;

  /// No description provided for @materialToken.
  ///
  /// In fr, this message translates to:
  /// **'Jeton matériel'**
  String get materialToken;

  /// No description provided for @tokenCode.
  ///
  /// In fr, this message translates to:
  /// **'Code du jeton'**
  String get tokenCode;

  /// No description provided for @validateToken.
  ///
  /// In fr, this message translates to:
  /// **'Valider le jeton'**
  String get validateToken;

  /// No description provided for @tokenExpiry.
  ///
  /// In fr, this message translates to:
  /// **'Expiration du jeton'**
  String get tokenExpiry;

  /// No description provided for @paymentMethods.
  ///
  /// In fr, this message translates to:
  /// **'Méthodes de paiement'**
  String get paymentMethods;

  /// No description provided for @wave.
  ///
  /// In fr, this message translates to:
  /// **'Wave'**
  String get wave;

  /// No description provided for @orangeMoney.
  ///
  /// In fr, this message translates to:
  /// **'Orange Money'**
  String get orangeMoney;

  /// No description provided for @mtnMoney.
  ///
  /// In fr, this message translates to:
  /// **'MTN Mobile Money'**
  String get mtnMoney;

  /// No description provided for @paymentConfirmed.
  ///
  /// In fr, this message translates to:
  /// **'Paiement confirmé'**
  String get paymentConfirmed;

  /// No description provided for @paymentFailed.
  ///
  /// In fr, this message translates to:
  /// **'Échec du paiement'**
  String get paymentFailed;

  /// No description provided for @reputation.
  ///
  /// In fr, this message translates to:
  /// **'Réputation'**
  String get reputation;

  /// No description provided for @nzassaScore.
  ///
  /// In fr, this message translates to:
  /// **'Score N\'Zassa'**
  String get nzassaScore;

  /// No description provided for @rating.
  ///
  /// In fr, this message translates to:
  /// **'Évaluation'**
  String get rating;

  /// No description provided for @rateArtisan.
  ///
  /// In fr, this message translates to:
  /// **'Évaluer l\'artisan'**
  String get rateArtisan;

  /// No description provided for @submitRating.
  ///
  /// In fr, this message translates to:
  /// **'Soumettre l\'évaluation'**
  String get submitRating;

  /// No description provided for @reliability.
  ///
  /// In fr, this message translates to:
  /// **'Fiabilité'**
  String get reliability;

  /// No description provided for @integrity.
  ///
  /// In fr, this message translates to:
  /// **'Intégrité'**
  String get integrity;

  /// No description provided for @quality.
  ///
  /// In fr, this message translates to:
  /// **'Qualité'**
  String get quality;

  /// No description provided for @reactivity.
  ///
  /// In fr, this message translates to:
  /// **'Réactivité'**
  String get reactivity;

  /// No description provided for @microCreditEligible.
  ///
  /// In fr, this message translates to:
  /// **'Éligible au micro-crédit'**
  String get microCreditEligible;

  /// No description provided for @disputes.
  ///
  /// In fr, this message translates to:
  /// **'Litiges'**
  String get disputes;

  /// No description provided for @reportDispute.
  ///
  /// In fr, this message translates to:
  /// **'Signaler un litige'**
  String get reportDispute;

  /// No description provided for @disputeType.
  ///
  /// In fr, this message translates to:
  /// **'Type de litige'**
  String get disputeType;

  /// No description provided for @disputeDescription.
  ///
  /// In fr, this message translates to:
  /// **'Description du litige'**
  String get disputeDescription;

  /// No description provided for @uploadEvidence.
  ///
  /// In fr, this message translates to:
  /// **'Télécharger des preuves'**
  String get uploadEvidence;

  /// No description provided for @mediation.
  ///
  /// In fr, this message translates to:
  /// **'Médiation'**
  String get mediation;

  /// No description provided for @arbitration.
  ///
  /// In fr, this message translates to:
  /// **'Arbitrage'**
  String get arbitration;

  /// No description provided for @disputeResolved.
  ///
  /// In fr, this message translates to:
  /// **'Litige résolu'**
  String get disputeResolved;

  /// No description provided for @notifications.
  ///
  /// In fr, this message translates to:
  /// **'Notifications'**
  String get notifications;

  /// No description provided for @notificationSettings.
  ///
  /// In fr, this message translates to:
  /// **'Paramètres de notification'**
  String get notificationSettings;

  /// No description provided for @pushNotifications.
  ///
  /// In fr, this message translates to:
  /// **'Notifications push'**
  String get pushNotifications;

  /// No description provided for @smsNotifications.
  ///
  /// In fr, this message translates to:
  /// **'Notifications SMS'**
  String get smsNotifications;

  /// No description provided for @emailNotifications.
  ///
  /// In fr, this message translates to:
  /// **'Notifications e-mail'**
  String get emailNotifications;

  /// No description provided for @whatsappNotifications.
  ///
  /// In fr, this message translates to:
  /// **'Notifications WhatsApp'**
  String get whatsappNotifications;

  /// No description provided for @profile.
  ///
  /// In fr, this message translates to:
  /// **'Profil'**
  String get profile;

  /// No description provided for @editProfile.
  ///
  /// In fr, this message translates to:
  /// **'Modifier le profil'**
  String get editProfile;

  /// No description provided for @kycVerification.
  ///
  /// In fr, this message translates to:
  /// **'Vérification KYC'**
  String get kycVerification;

  /// No description provided for @uploadDocuments.
  ///
  /// In fr, this message translates to:
  /// **'Télécharger les documents'**
  String get uploadDocuments;

  /// No description provided for @idDocument.
  ///
  /// In fr, this message translates to:
  /// **'Document d\'identité'**
  String get idDocument;

  /// No description provided for @selfie.
  ///
  /// In fr, this message translates to:
  /// **'Selfie'**
  String get selfie;

  /// No description provided for @kycPending.
  ///
  /// In fr, this message translates to:
  /// **'KYC en attente'**
  String get kycPending;

  /// No description provided for @kycVerified.
  ///
  /// In fr, this message translates to:
  /// **'KYC vérifié'**
  String get kycVerified;

  /// No description provided for @kycRejected.
  ///
  /// In fr, this message translates to:
  /// **'KYC rejeté'**
  String get kycRejected;

  /// No description provided for @search.
  ///
  /// In fr, this message translates to:
  /// **'Rechercher'**
  String get search;

  /// No description provided for @searchArtisans.
  ///
  /// In fr, this message translates to:
  /// **'Rechercher des artisans'**
  String get searchArtisans;

  /// No description provided for @nearbyArtisans.
  ///
  /// In fr, this message translates to:
  /// **'Artisans à proximité'**
  String get nearbyArtisans;

  /// No description provided for @distance.
  ///
  /// In fr, this message translates to:
  /// **'Distance'**
  String get distance;

  /// No description provided for @km.
  ///
  /// In fr, this message translates to:
  /// **'km'**
  String get km;

  /// No description provided for @showOnMap.
  ///
  /// In fr, this message translates to:
  /// **'Afficher sur la carte'**
  String get showOnMap;

  /// No description provided for @mapView.
  ///
  /// In fr, this message translates to:
  /// **'Vue carte'**
  String get mapView;

  /// No description provided for @listView.
  ///
  /// In fr, this message translates to:
  /// **'Vue liste'**
  String get listView;

  /// No description provided for @settings.
  ///
  /// In fr, this message translates to:
  /// **'Paramètres'**
  String get settings;

  /// No description provided for @language.
  ///
  /// In fr, this message translates to:
  /// **'Langue'**
  String get language;

  /// No description provided for @french.
  ///
  /// In fr, this message translates to:
  /// **'Français'**
  String get french;

  /// No description provided for @english.
  ///
  /// In fr, this message translates to:
  /// **'Anglais'**
  String get english;

  /// No description provided for @changeLanguage.
  ///
  /// In fr, this message translates to:
  /// **'Changer de langue'**
  String get changeLanguage;

  /// No description provided for @darkMode.
  ///
  /// In fr, this message translates to:
  /// **'Mode sombre'**
  String get darkMode;

  /// No description provided for @lightMode.
  ///
  /// In fr, this message translates to:
  /// **'Mode clair'**
  String get lightMode;

  /// No description provided for @about.
  ///
  /// In fr, this message translates to:
  /// **'À propos'**
  String get about;

  /// No description provided for @version.
  ///
  /// In fr, this message translates to:
  /// **'Version'**
  String get version;

  /// No description provided for @termsOfService.
  ///
  /// In fr, this message translates to:
  /// **'Conditions d\'utilisation'**
  String get termsOfService;

  /// No description provided for @privacyPolicy.
  ///
  /// In fr, this message translates to:
  /// **'Politique de confidentialité'**
  String get privacyPolicy;

  /// No description provided for @errors.
  ///
  /// In fr, this message translates to:
  /// **'Erreurs'**
  String get errors;

  /// No description provided for @networkError.
  ///
  /// In fr, this message translates to:
  /// **'Erreur de réseau'**
  String get networkError;

  /// No description provided for @serverError.
  ///
  /// In fr, this message translates to:
  /// **'Erreur du serveur'**
  String get serverError;

  /// No description provided for @validationError.
  ///
  /// In fr, this message translates to:
  /// **'Erreur de validation'**
  String get validationError;

  /// No description provided for @unknownError.
  ///
  /// In fr, this message translates to:
  /// **'Erreur inconnue'**
  String get unknownError;

  /// No description provided for @tryAgain.
  ///
  /// In fr, this message translates to:
  /// **'Réessayer'**
  String get tryAgain;

  /// No description provided for @contactSupport.
  ///
  /// In fr, this message translates to:
  /// **'Contacter le support'**
  String get contactSupport;

  /// No description provided for @success.
  ///
  /// In fr, this message translates to:
  /// **'Succès'**
  String get success;

  /// No description provided for @info.
  ///
  /// In fr, this message translates to:
  /// **'Information'**
  String get info;

  /// No description provided for @warning.
  ///
  /// In fr, this message translates to:
  /// **'Avertissement'**
  String get warning;

  /// No description provided for @error.
  ///
  /// In fr, this message translates to:
  /// **'Erreur'**
  String get error;

  /// No description provided for @loading.
  ///
  /// In fr, this message translates to:
  /// **'Chargement...'**
  String get loading;

  /// No description provided for @noData.
  ///
  /// In fr, this message translates to:
  /// **'Aucune donnée'**
  String get noData;

  /// No description provided for @refresh.
  ///
  /// In fr, this message translates to:
  /// **'Actualiser'**
  String get refresh;

  /// No description provided for @offline.
  ///
  /// In fr, this message translates to:
  /// **'Hors ligne'**
  String get offline;

  /// No description provided for @online.
  ///
  /// In fr, this message translates to:
  /// **'En ligne'**
  String get online;

  /// No description provided for @syncPending.
  ///
  /// In fr, this message translates to:
  /// **'Synchronisation en attente'**
  String get syncPending;

  /// No description provided for @syncComplete.
  ///
  /// In fr, this message translates to:
  /// **'Synchronisation terminée'**
  String get syncComplete;

  /// No description provided for @dateFormat.
  ///
  /// In fr, this message translates to:
  /// **'dd/MM/yyyy'**
  String get dateFormat;

  /// No description provided for @timeFormat.
  ///
  /// In fr, this message translates to:
  /// **'HH:mm'**
  String get timeFormat;

  /// No description provided for @dateTimeFormat.
  ///
  /// In fr, this message translates to:
  /// **'dd/MM/yyyy HH:mm'**
  String get dateTimeFormat;

  /// Format for displaying currency amounts
  ///
  /// In fr, this message translates to:
  /// **'{amount} FCFA'**
  String currencyFormat(String amount);

  /// Format for displaying distances
  ///
  /// In fr, this message translates to:
  /// **'{distance} km'**
  String distanceFormat(String distance);

  /// Format for displaying ratings
  ///
  /// In fr, this message translates to:
  /// **'{rating}/5'**
  String ratingFormat(String rating);

  /// Format for displaying relative time
  ///
  /// In fr, this message translates to:
  /// **'il y a {time}'**
  String timeAgo(String time);
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['en', 'fr'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'en':
      return AppLocalizationsEn();
    case 'fr':
      return AppLocalizationsFr();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
