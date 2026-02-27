import React from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity, Alert } from 'react-native';
import { useRouter } from 'expo-router';
import { User, Bell, Globe, Shield, HelpCircle, LogOut, ChevronRight, AlertTriangle } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import { useUser } from '@/contexts/UserContext';
import * as Haptics from 'expo-haptics';

export default function SettingsScreen() {
  const { name, phone, role, logout } = useUser();
  const router = useRouter();

  const roleLabel = role === 'client' ? 'Client' : role === 'artisan' ? 'Artisan' : 'Fournisseur';
  const roleColor = role === 'client' ? Colors.primary : role === 'artisan' ? Colors.accent : Colors.success;

  const handleLogout = () => {
    Alert.alert(
      'Déconnexion',
      'Êtes-vous sûr de vouloir vous déconnecter ?',
      [
        { text: 'Annuler', style: 'cancel' },
        {
          text: 'Déconnecter',
          style: 'destructive',
          onPress: () => {
            Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
            logout();
            router.replace('/');
          },
        },
      ]
    );
  };

  const handleDispute = () => {
    router.push('/dispute');
  };

  const menuItems = [
    { icon: User, label: 'Informations du compte', color: Colors.primary },
    { icon: Bell, label: 'Notifications', color: Colors.accent },
    { icon: Globe, label: 'Langue', subtitle: 'Français', color: '#3498DB' },
    { icon: Shield, label: 'Confidentialité', color: Colors.success },
    { icon: AlertTriangle, label: 'Signaler un problème', color: Colors.danger, onPress: handleDispute },
    { icon: HelpCircle, label: 'Aide & Support', color: '#9B59B6' },
  ];

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.profileCard}>
        <View style={[styles.avatarCircle, { backgroundColor: roleColor + '15' }]}>
          <Text style={[styles.avatarText, { color: roleColor }]}>
            {(name ?? 'U').charAt(0).toUpperCase()}
          </Text>
        </View>
        <View style={styles.profileInfo}>
          <Text style={styles.profileName}>{name ?? 'Utilisateur'}</Text>
          <Text style={styles.profilePhone}>{phone ?? '+225 XX XX XX XX'}</Text>
        </View>
        <View style={[styles.roleBadge, { backgroundColor: roleColor + '15' }]}>
          <Text style={[styles.roleText, { color: roleColor }]}>{roleLabel}</Text>
        </View>
      </View>

      <View style={styles.menuSection}>
        {menuItems.map((item, i) => {
          const Icon = item.icon;
          return (
            <TouchableOpacity
              key={i}
              style={styles.menuItem}
              activeOpacity={0.7}
              onPress={item.onPress}
            >
              <View style={[styles.menuIcon, { backgroundColor: item.color + '12' }]}>
                <Icon size={20} color={item.color} />
              </View>
              <View style={styles.menuContent}>
                <Text style={styles.menuLabel}>{item.label}</Text>
                {item.subtitle && (
                  <Text style={styles.menuSubtitle}>{item.subtitle}</Text>
                )}
              </View>
              <ChevronRight size={18} color={Colors.textMuted} />
            </TouchableOpacity>
          );
        })}
      </View>

      <TouchableOpacity style={styles.logoutBtn} onPress={handleLogout} activeOpacity={0.7}>
        <LogOut size={20} color={Colors.danger} />
        <Text style={styles.logoutText}>Se déconnecter</Text>
      </TouchableOpacity>

      <Text style={styles.version}>ProsArtisan v1.0.0</Text>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  content: {
    padding: 20,
    paddingBottom: 40,
  },
  profileCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.white,
    borderRadius: 18,
    padding: 18,
    marginBottom: 20,
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.06,
    shadowRadius: 12,
    elevation: 3,
  },
  avatarCircle: {
    width: 52,
    height: 52,
    borderRadius: 26,
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarText: {
    fontSize: 22,
    fontWeight: '700' as const,
  },
  profileInfo: {
    flex: 1,
    marginLeft: 14,
  },
  profileName: {
    fontSize: 17,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  profilePhone: {
    fontSize: 13,
    color: Colors.textSecondary,
    marginTop: 2,
  },
  roleBadge: {
    paddingHorizontal: 12,
    paddingVertical: 5,
    borderRadius: 10,
  },
  roleText: {
    fontSize: 12,
    fontWeight: '700' as const,
  },
  menuSection: {
    backgroundColor: Colors.white,
    borderRadius: 18,
    overflow: 'hidden',
    marginBottom: 20,
  },
  menuItem: {
    flexDirection: 'row',
    alignItems: 'center',
    padding: 16,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
    gap: 12,
  },
  menuIcon: {
    width: 40,
    height: 40,
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
  },
  menuContent: {
    flex: 1,
  },
  menuLabel: {
    fontSize: 15,
    fontWeight: '500' as const,
    color: Colors.textPrimary,
  },
  menuSubtitle: {
    fontSize: 12,
    color: Colors.textMuted,
    marginTop: 1,
  },
  logoutBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.danger + '08',
    borderRadius: 14,
    paddingVertical: 16,
    gap: 8,
    borderWidth: 1,
    borderColor: Colors.danger + '20',
  },
  logoutText: {
    fontSize: 15,
    fontWeight: '600' as const,
    color: Colors.danger,
  },
  version: {
    fontSize: 12,
    color: Colors.textMuted,
    textAlign: 'center',
    marginTop: 20,
  },
});
