# Fonctionnalité de Messagerie

## Vue d'ensemble

La fonctionnalité de messagerie permet aux clients et artisans de communiquer au sujet des projets.

## Composants

### 1. Models (`lib/shared/models/message_model.dart`)
- `ProjectMessage`: Représente un message individuel
- `Conversation`: Représente une conversation (projet)
- `MessageSender`: Informations sur l'expéditeur
- `ConversationUser`: Utilisateur dans une conversation
- `LastMessage`: Dernier message d'une conversation

### 2. Service (`lib/core/network/message_service.dart`)
- `getConversations()`: Récupère toutes les conversations
- `getProjectMessages(projectId)`: Récupère les messages d'un projet
- `sendMessage()`: Envoie un message
- `markAsRead(projectId)`: Marque les messages comme lus

### 3. Controller (`lib/shared/controllers/message_controller.dart`)
- Gère l'état de la messagerie
- Méthodes:
  - `fetchConversations()`: Charge les conversations
  - `fetchProjectMessages(projectId)`: Charge les messages d'un projet
  - `sendMessage()`: Envoie un message
  - `markAsRead(projectId)`: Marque comme lu
  - `totalUnreadCount`: Nombre total de messages non lus

### 4. Screens

#### ConversationsScreen (`lib/features/messages/presentation/screens/conversations_screen.dart`)
- Liste toutes les conversations
- Affiche le nombre de messages non lus
- Navigation vers ChatScreen

#### ChatScreen (`lib/features/messages/presentation/screens/chat_screen.dart`)
- Interface de chat en temps réel
- Envoi de messages
- Affichage des messages avec bulles
- Séparateurs de date
- Support des pièces jointes (à venir)

## Utilisation

### 1. Initialiser le contrôleur

```dart
final _messageController = Get.put(MessageController());
```

### 2. Naviguer vers les conversations

```dart
Get.to(() => const ConversationsScreen());
```

### 3. Ouvrir un chat spécifique

```dart
Get.to(() => ChatScreen(
  projectId: projectId,
  projectTitle: 'Titre du projet',
  otherUser: ConversationUser(...),
));
```

### 4. Afficher le badge de messages non lus

```dart
Obx(() {
  final unreadCount = _messageController.totalUnreadCount;
  if (unreadCount > 0) {
    return Badge(
      label: Text('$unreadCount'),
      child: Icon(Icons.message),
    );
  }
  return Icon(Icons.message);
})
```

## Intégration dans la navigation

Pour ajouter la messagerie à la navigation principale:

```dart
BottomNavigationBarItem(
  icon: Obx(() {
    final controller = Get.find<MessageController>();
    final unreadCount = controller.totalUnreadCount;
    return Badge(
      label: Text('$unreadCount'),
      isLabelVisible: unreadCount > 0,
      child: const Icon(Icons.message_outlined),
    );
  }),
  label: 'Messages',
)
```

## API Endpoints

- `GET /projects/messages` - Liste des conversations
- `GET /projects/{id}/messages` - Messages d'un projet
- `POST /projects/{id}/messages` - Envoyer un message
- `POST /projects/{id}/messages/read` - Marquer comme lu

## Fonctionnalités à venir

- [ ] Pièces jointes (images, documents)
- [ ] Notifications push
- [ ] Indicateur de saisie en cours
- [ ] Messages vocaux
- [ ] Recherche dans les messages
- [ ] Archivage de conversations
