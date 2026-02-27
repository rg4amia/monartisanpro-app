import React, { useRef, useEffect } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Animated } from 'react-native';
import { Copy, Phone } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import { formatFCFA } from '@/mocks/data';
import * as Haptics from 'expo-haptics';

export default function JCodeScreen() {
  const pulseAnim = useRef(new Animated.Value(1)).current;
  const fadeAnim = useRef(new Animated.Value(0)).current;

  useEffect(() => {
    Animated.timing(fadeAnim, {
      toValue: 1,
      duration: 600,
      useNativeDriver: true,
    }).start();

    Animated.loop(
      Animated.sequence([
        Animated.timing(pulseAnim, { toValue: 1.05, duration: 1200, useNativeDriver: true }),
        Animated.timing(pulseAnim, { toValue: 1, duration: 1200, useNativeDriver: true }),
      ])
    ).start();
  }, []);

  const handleCopy = () => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
  };

  return (
    <Animated.ScrollView
      style={[styles.container, { opacity: fadeAnim }]}
      contentContainerStyle={styles.content}
      showsVerticalScrollIndicator={false}
    >
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Votre J-Code</Text>
        <Text style={styles.headerSubtitle}>Présentez ce code chez un fournisseur agréé pour acheter vos matériaux</Text>
      </View>

      <Animated.View style={[styles.codeCard, { transform: [{ scale: pulseAnim }] }]}>
        <Text style={styles.codeLabel}>Code J-Code</Text>
        <Text style={styles.codeValue}>PA-4827</Text>
        <TouchableOpacity style={styles.copyBtn} onPress={handleCopy} activeOpacity={0.7}>
          <Copy size={16} color={Colors.primary} />
          <Text style={styles.copyText}>Copier le code</Text>
        </TouchableOpacity>
      </Animated.View>

      <View style={styles.qrSection}>
        <Text style={styles.qrTitle}>QR Code</Text>
        <View style={styles.qrPlaceholder}>
          <View style={styles.qrGrid}>
            {Array.from({ length: 64 }).map((_, i) => (
              <View
                key={i}
                style={[
                  styles.qrBlock,
                  { backgroundColor: Math.random() > 0.4 ? Colors.primary : Colors.white },
                ]}
              />
            ))}
          </View>
        </View>
        <Text style={styles.qrHint}>Le fournisseur scannera ce code</Text>
      </View>

      <View style={styles.detailsCard}>
        <Text style={styles.detailsTitle}>Détails de l'achat</Text>
        <View style={styles.detailRow}>
          <Text style={styles.detailLabel}>Mission</Text>
          <Text style={styles.detailValue}>Réparation fuite d'eau</Text>
        </View>
        <View style={styles.detailRow}>
          <Text style={styles.detailLabel}>Montant autorisé</Text>
          <Text style={[styles.detailValue, styles.detailAmount]}>{formatFCFA(110000)}</Text>
        </View>
        <View style={styles.detailRow}>
          <Text style={styles.detailLabel}>Validité</Text>
          <Text style={styles.detailValue}>48 heures</Text>
        </View>
        <View style={styles.detailRow}>
          <Text style={styles.detailLabel}>Zone autorisée</Text>
          <Text style={styles.detailValue}>Cocody, Abidjan (100m)</Text>
        </View>
      </View>

      <View style={styles.ussdSection}>
        <View style={styles.ussdHeader}>
          <Phone size={18} color={Colors.accent} />
          <Text style={styles.ussdTitle}>Alternative USSD</Text>
        </View>
        <Text style={styles.ussdDesc}>Si le scanner ne fonctionne pas, le fournisseur peut composer :</Text>
        <View style={styles.ussdCode}>
          <Text style={styles.ussdCodeText}>*123*PA4827#</Text>
        </View>
      </View>

      <View style={styles.gpsWarning}>
        <Text style={styles.gpsIcon}>📍</Text>
        <Text style={styles.gpsText}>
          Ce code ne peut être validé que dans un rayon de 100m d'un fournisseur agréé
        </Text>
      </View>
    </Animated.ScrollView>
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
  header: {
    marginBottom: 24,
  },
  headerTitle: {
    fontSize: 22,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
    marginBottom: 6,
  },
  headerSubtitle: {
    fontSize: 14,
    color: Colors.textSecondary,
    lineHeight: 20,
  },
  codeCard: {
    backgroundColor: Colors.primary,
    borderRadius: 20,
    padding: 28,
    alignItems: 'center',
    marginBottom: 24,
  },
  codeLabel: {
    fontSize: 13,
    color: 'rgba(255,255,255,0.6)',
    fontWeight: '500' as const,
    marginBottom: 8,
  },
  codeValue: {
    fontSize: 42,
    fontWeight: '800' as const,
    color: Colors.white,
    letterSpacing: 4,
    marginBottom: 16,
  },
  copyBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.white,
    paddingHorizontal: 20,
    paddingVertical: 10,
    borderRadius: 12,
    gap: 6,
  },
  copyText: {
    fontSize: 13,
    fontWeight: '600' as const,
    color: Colors.primary,
  },
  qrSection: {
    alignItems: 'center',
    marginBottom: 24,
  },
  qrTitle: {
    fontSize: 15,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
    marginBottom: 12,
  },
  qrPlaceholder: {
    width: 180,
    height: 180,
    backgroundColor: Colors.white,
    borderRadius: 16,
    padding: 16,
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.08,
    shadowRadius: 12,
    elevation: 3,
  },
  qrGrid: {
    width: 148,
    height: 148,
    flexDirection: 'row',
    flexWrap: 'wrap',
  },
  qrBlock: {
    width: '12.5%',
    aspectRatio: 1,
  },
  qrHint: {
    fontSize: 12,
    color: Colors.textMuted,
    marginTop: 10,
  },
  detailsCard: {
    backgroundColor: Colors.white,
    borderRadius: 16,
    padding: 18,
    marginBottom: 16,
  },
  detailsTitle: {
    fontSize: 15,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
    marginBottom: 14,
  },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  detailLabel: {
    fontSize: 13,
    color: Colors.textSecondary,
  },
  detailValue: {
    fontSize: 13,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
  },
  detailAmount: {
    color: Colors.success,
    fontSize: 15,
    fontWeight: '700' as const,
  },
  ussdSection: {
    backgroundColor: Colors.accent + '08',
    borderRadius: 16,
    padding: 18,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: Colors.accent + '20',
  },
  ussdHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 8,
  },
  ussdTitle: {
    fontSize: 15,
    fontWeight: '700' as const,
    color: Colors.accent,
  },
  ussdDesc: {
    fontSize: 13,
    color: Colors.textSecondary,
    marginBottom: 12,
    lineHeight: 18,
  },
  ussdCode: {
    backgroundColor: Colors.white,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: 'center',
  },
  ussdCodeText: {
    fontSize: 20,
    fontWeight: '700' as const,
    color: Colors.primary,
    letterSpacing: 1,
  },
  gpsWarning: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    backgroundColor: Colors.warning + '12',
    borderRadius: 12,
    padding: 14,
    gap: 10,
  },
  gpsIcon: {
    fontSize: 18,
  },
  gpsText: {
    flex: 1,
    fontSize: 12,
    color: Colors.textSecondary,
    lineHeight: 18,
  },
});
