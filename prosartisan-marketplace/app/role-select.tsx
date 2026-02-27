import React, { useRef } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Animated } from 'react-native';
import { useRouter, Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { User, Wrench, Store, ArrowRight } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import { useUser } from '@/contexts/UserContext';
import type { UserRole } from '@/mocks/data';
import * as Haptics from 'expo-haptics';

const roles: { id: UserRole; title: string; subtitle: string; icon: typeof User; color: string; bgColor: string }[] = [
  {
    id: 'client',
    title: 'Client',
    subtitle: 'Je cherche un artisan pour mes travaux',
    icon: User,
    color: Colors.primary,
    bgColor: Colors.primary + '10',
  },
  {
    id: 'artisan',
    title: 'Artisan',
    subtitle: 'Je propose mes services de réparation et construction',
    icon: Wrench,
    color: Colors.accent,
    bgColor: Colors.accent + '10',
  },
  {
    id: 'fournisseur',
    title: 'Fournisseur',
    subtitle: 'Je vends des matériaux de construction',
    icon: Store,
    color: Colors.success,
    bgColor: Colors.success + '10',
  },
];

export default function RoleSelectScreen() {
  const router = useRouter();
  const { setRole } = useUser();
  const scaleAnims = useRef(roles.map(() => new Animated.Value(1))).current;

  const handleSelect = (role: UserRole, index: number) => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    Animated.sequence([
      Animated.timing(scaleAnims[index], { toValue: 0.95, duration: 100, useNativeDriver: true }),
      Animated.timing(scaleAnims[index], { toValue: 1, duration: 100, useNativeDriver: true }),
    ]).start(() => {
      setRole(role);
      router.replace('/kyc');
    });
  };

  return (
    <View style={styles.container}>
      <StatusBar style="dark" />
      <Stack.Screen options={{ headerShown: false }} />

      <View style={styles.header}>
        <Text style={styles.title}>Qui êtes-vous ?</Text>
        <Text style={styles.subtitle}>Choisissez votre rôle pour personnaliser votre expérience</Text>
      </View>

      <View style={styles.cardsContainer}>
        {roles.map((role, index) => {
          const Icon = role.icon;
          return (
            <Animated.View
              key={role.id}
              style={{ transform: [{ scale: scaleAnims[index] }] }}
            >
              <TouchableOpacity
                style={[styles.roleCard, { borderLeftColor: role.color }]}
                onPress={() => handleSelect(role.id, index)}
                activeOpacity={0.85}
                testID={`role-${role.id}`}
              >
                <View style={[styles.iconCircle, { backgroundColor: role.bgColor }]}>
                  <Icon size={28} color={role.color} />
                </View>
                <View style={styles.roleInfo}>
                  <Text style={styles.roleTitle}>{role.title}</Text>
                  <Text style={styles.roleSubtitle}>{role.subtitle}</Text>
                </View>
                <ArrowRight size={20} color={Colors.textMuted} />
              </TouchableOpacity>
            </Animated.View>
          );
        })}
      </View>

      <View style={styles.footer}>
        <Text style={styles.footerText}>Vous pourrez changer de rôle plus tard dans les paramètres</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  header: {
    paddingTop: 80,
    paddingHorizontal: 28,
    paddingBottom: 32,
  },
  title: {
    fontSize: 30,
    fontWeight: '800' as const,
    color: Colors.textPrimary,
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 15,
    color: Colors.textSecondary,
    lineHeight: 22,
  },
  cardsContainer: {
    paddingHorizontal: 20,
    gap: 16,
  },
  roleCard: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.white,
    borderRadius: 18,
    padding: 20,
    borderLeftWidth: 4,
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.06,
    shadowRadius: 12,
    elevation: 3,
  },
  iconCircle: {
    width: 56,
    height: 56,
    borderRadius: 28,
    justifyContent: 'center',
    alignItems: 'center',
  },
  roleInfo: {
    flex: 1,
    marginLeft: 16,
    marginRight: 8,
  },
  roleTitle: {
    fontSize: 18,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
    marginBottom: 4,
  },
  roleSubtitle: {
    fontSize: 13,
    color: Colors.textSecondary,
    lineHeight: 18,
  },
  footer: {
    position: 'absolute',
    bottom: 50,
    left: 0,
    right: 0,
    alignItems: 'center',
    paddingHorizontal: 40,
  },
  footerText: {
    fontSize: 12,
    color: Colors.textMuted,
    textAlign: 'center',
  },
});
