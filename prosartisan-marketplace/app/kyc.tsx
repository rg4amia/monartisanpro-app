import React, { useState, useRef } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, TextInput, Animated, ScrollView } from 'react-native';
import { useRouter, Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { Camera, UserCircle, CheckCircle, Shield, ArrowRight } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import { useUser } from '@/contexts/UserContext';
import * as Haptics from 'expo-haptics';

export default function KycScreen() {
  const router = useRouter();
  const { completeOnboarding, role } = useUser();
  const [name, setName] = useState('');
  const [cniUploaded, setCniUploaded] = useState(false);
  const [selfieUploaded, setSelfieUploaded] = useState(false);
  const pulseAnim = useRef(new Animated.Value(1)).current;

  const startPulse = () => {
    Animated.loop(
      Animated.sequence([
        Animated.timing(pulseAnim, { toValue: 1.1, duration: 800, useNativeDriver: true }),
        Animated.timing(pulseAnim, { toValue: 1, duration: 800, useNativeDriver: true }),
      ])
    ).start();
  };

  const handleUploadCNI = () => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setCniUploaded(true);
  };

  const handleUploadSelfie = () => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setSelfieUploaded(true);
    startPulse();
  };

  const handleContinue = () => {
    if (!name.trim()) return;
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    completeOnboarding(name.trim());
    router.replace('/(tabs)/(home)' as any);
  };

  const roleLabel = role === 'client' ? 'Client' : role === 'artisan' ? 'Artisan' : 'Fournisseur';

  return (
    <View style={styles.container}>
      <StatusBar style="dark" />
      <Stack.Screen options={{ headerShown: false }} />

      <ScrollView contentContainerStyle={styles.scrollContent} showsVerticalScrollIndicator={false}>
        <View style={styles.header}>
          <View style={styles.stepRow}>
            <View style={[styles.stepDot, styles.stepDotActive]} />
            <View style={[styles.stepLine, styles.stepLineActive]} />
            <View style={[styles.stepDot, styles.stepDotActive]} />
            <View style={[styles.stepLine, styles.stepLineActive]} />
            <View style={[styles.stepDot, styles.stepDotActive]} />
          </View>
          <Text style={styles.title}>Vérification d'identité</Text>
          <Text style={styles.subtitle}>Inscrivez-vous en tant que {roleLabel}</Text>
        </View>

        <View style={styles.form}>
          <Text style={styles.label}>Nom complet</Text>
          <TextInput
            style={styles.input}
            placeholder="Ex: Kouamé Yao Jean"
            placeholderTextColor={Colors.textMuted}
            value={name}
            onChangeText={setName}
            testID="name-input"
          />

          <Text style={[styles.label, { marginTop: 24 }]}>Pièce d'identité (CNI)</Text>
          <TouchableOpacity
            style={[styles.uploadCard, cniUploaded && styles.uploadCardDone]}
            onPress={handleUploadCNI}
            activeOpacity={0.7}
          >
            {cniUploaded ? (
              <CheckCircle size={32} color={Colors.success} />
            ) : (
              <Camera size={32} color={Colors.textMuted} />
            )}
            <Text style={[styles.uploadText, cniUploaded && styles.uploadTextDone]}>
              {cniUploaded ? 'CNI téléchargée ✓' : 'Prendre une photo de votre CNI'}
            </Text>
          </TouchableOpacity>

          <Text style={[styles.label, { marginTop: 24 }]}>Selfie de vérification</Text>
          <TouchableOpacity
            style={[styles.uploadCard, selfieUploaded && styles.uploadCardDone]}
            onPress={handleUploadSelfie}
            activeOpacity={0.7}
          >
            {selfieUploaded ? (
              <Animated.View style={{ transform: [{ scale: pulseAnim }] }}>
                <CheckCircle size={32} color={Colors.success} />
              </Animated.View>
            ) : (
              <UserCircle size={32} color={Colors.textMuted} />
            )}
            <Text style={[styles.uploadText, selfieUploaded && styles.uploadTextDone]}>
              {selfieUploaded ? 'Selfie vérifié ✓' : 'Prendre un selfie'}
            </Text>
            {selfieUploaded && (
              <View style={styles.livenessBadge}>
                <View style={styles.livenessGreenDot} />
                <Text style={styles.livenessText}>Liveness OK</Text>
              </View>
            )}
          </TouchableOpacity>
        </View>

        <View style={styles.securityInfo}>
          <Shield size={16} color={Colors.textMuted} />
          <Text style={styles.securityText}>
            Vos données sont chiffrées et protégées conformément aux normes de sécurité
          </Text>
        </View>

        <TouchableOpacity
          style={[styles.button, (!name.trim()) && styles.buttonDisabled]}
          onPress={handleContinue}
          disabled={!name.trim()}
          activeOpacity={0.8}
          testID="kyc-continue"
        >
          <Text style={styles.buttonText}>Continuer</Text>
          <ArrowRight size={18} color={Colors.white} />
        </TouchableOpacity>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  scrollContent: {
    paddingBottom: 40,
  },
  header: {
    paddingTop: 70,
    paddingHorizontal: 28,
    paddingBottom: 24,
  },
  stepRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 24,
    justifyContent: 'center',
  },
  stepDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: Colors.border,
  },
  stepDotActive: {
    backgroundColor: Colors.accent,
  },
  stepLine: {
    width: 40,
    height: 2,
    backgroundColor: Colors.border,
    marginHorizontal: 4,
  },
  stepLineActive: {
    backgroundColor: Colors.accent,
  },
  title: {
    fontSize: 26,
    fontWeight: '800' as const,
    color: Colors.textPrimary,
    marginBottom: 6,
  },
  subtitle: {
    fontSize: 15,
    color: Colors.textSecondary,
  },
  form: {
    paddingHorizontal: 24,
  },
  label: {
    fontSize: 13,
    fontWeight: '600' as const,
    color: Colors.textSecondary,
    marginBottom: 8,
    textTransform: 'uppercase' as const,
    letterSpacing: 0.5,
  },
  input: {
    backgroundColor: Colors.white,
    borderRadius: 14,
    paddingHorizontal: 16,
    paddingVertical: 16,
    fontSize: 16,
    color: Colors.textPrimary,
    fontWeight: '500' as const,
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.04,
    shadowRadius: 8,
    elevation: 1,
  },
  uploadCard: {
    backgroundColor: Colors.white,
    borderRadius: 16,
    padding: 24,
    alignItems: 'center',
    borderWidth: 2,
    borderColor: Colors.border,
    borderStyle: 'dashed',
    gap: 8,
  },
  uploadCardDone: {
    borderColor: Colors.success,
    borderStyle: 'solid',
    backgroundColor: Colors.success + '08',
  },
  uploadText: {
    fontSize: 14,
    color: Colors.textSecondary,
    fontWeight: '500' as const,
  },
  uploadTextDone: {
    color: Colors.success,
    fontWeight: '600' as const,
  },
  livenessBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.success + '18',
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 20,
    gap: 6,
    marginTop: 4,
  },
  livenessGreenDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: Colors.success,
  },
  livenessText: {
    fontSize: 11,
    color: Colors.success,
    fontWeight: '600' as const,
  },
  securityInfo: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 28,
    marginTop: 24,
    gap: 8,
  },
  securityText: {
    flex: 1,
    fontSize: 11,
    color: Colors.textMuted,
    lineHeight: 16,
  },
  button: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.accent,
    marginHorizontal: 24,
    marginTop: 24,
    paddingVertical: 16,
    borderRadius: 14,
    gap: 8,
  },
  buttonDisabled: {
    opacity: 0.5,
  },
  buttonText: {
    fontSize: 16,
    fontWeight: '700' as const,
    color: Colors.white,
  },
});
