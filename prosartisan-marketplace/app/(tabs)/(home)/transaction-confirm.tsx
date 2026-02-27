import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity, Alert } from 'react-native';
import { CheckCircle, MapPin, User, Package, AlertTriangle } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import { formatFCFA } from '@/mocks/data';
import * as Haptics from 'expo-haptics';

export default function TransactionConfirmScreen() {
  const gpsValid = true;
  const distance = 45;

  const handleConfirm = () => {
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    Alert.alert('Transaction validée', 'Le paiement de 110 000 FCFA sera versé sous J+1.');
  };

  return (
    <View style={styles.container}>
      <View style={styles.content}>
        <View style={styles.statusCircle}>
          {gpsValid ? (
            <CheckCircle size={48} color={Colors.success} />
          ) : (
            <AlertTriangle size={48} color={Colors.danger} />
          )}
        </View>

        <Text style={styles.title}>
          {gpsValid ? 'J-Code Vérifié' : 'Alerte GPS'}
        </Text>
        <Text style={styles.subtitle}>
          {gpsValid
            ? 'Le code est valide et l\'artisan est à proximité'
            : 'L\'artisan est trop loin du point de vente'
          }
        </Text>

        <View style={styles.card}>
          <View style={styles.cardRow}>
            <User size={18} color={Colors.textMuted} />
            <View style={styles.cardRowInfo}>
              <Text style={styles.cardLabel}>Artisan</Text>
              <Text style={styles.cardValue}>Kouamé Yao Jean</Text>
            </View>
          </View>

          <View style={styles.cardDivider} />

          <View style={styles.cardRow}>
            <Package size={18} color={Colors.textMuted} />
            <View style={styles.cardRowInfo}>
              <Text style={styles.cardLabel}>Montant matériaux</Text>
              <Text style={[styles.cardValue, styles.amount]}>{formatFCFA(110000)}</Text>
            </View>
          </View>

          <View style={styles.cardDivider} />

          <View style={styles.cardRow}>
            <MapPin size={18} color={gpsValid ? Colors.success : Colors.danger} />
            <View style={styles.cardRowInfo}>
              <Text style={styles.cardLabel}>Distance GPS</Text>
              <View style={styles.gpsRow}>
                <Text style={[styles.cardValue, { color: gpsValid ? Colors.success : Colors.danger }]}>
                  {distance}m
                </Text>
                <View style={[styles.gpsBadge, { backgroundColor: gpsValid ? Colors.success + '15' : Colors.danger + '15' }]}>
                  <Text style={{ fontSize: 14 }}>{gpsValid ? '✅' : '🚨'}</Text>
                  <Text style={[styles.gpsBadgeText, { color: gpsValid ? Colors.success : Colors.danger }]}>
                    {gpsValid ? 'OK' : 'Hors zone'}
                  </Text>
                </View>
              </View>
            </View>
          </View>
        </View>

        <View style={styles.codeDisplay}>
          <Text style={styles.codeLabel}>J-Code</Text>
          <Text style={styles.codeValue}>PA-4827</Text>
        </View>
      </View>

      <View style={styles.footer}>
        <TouchableOpacity
          style={[styles.confirmBtn, !gpsValid && styles.confirmBtnDanger]}
          onPress={handleConfirm}
          activeOpacity={0.8}
        >
          <CheckCircle size={20} color={Colors.white} />
          <Text style={styles.confirmBtnText}>
            {gpsValid ? 'Confirmer la transaction' : 'Confirmer malgré l\'alerte'}
          </Text>
        </TouchableOpacity>
        {!gpsValid && (
          <Text style={styles.warningText}>
            ⚠️ Cette transaction sera signalée pour vérification
          </Text>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.background,
  },
  content: {
    flex: 1,
    padding: 20,
    alignItems: 'center',
  },
  statusCircle: {
    width: 96,
    height: 96,
    borderRadius: 48,
    backgroundColor: Colors.success + '12',
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 20,
    marginBottom: 16,
  },
  title: {
    fontSize: 22,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
    marginBottom: 6,
  },
  subtitle: {
    fontSize: 14,
    color: Colors.textSecondary,
    textAlign: 'center',
    marginBottom: 28,
  },
  card: {
    backgroundColor: Colors.white,
    borderRadius: 18,
    padding: 20,
    width: '100%',
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.06,
    shadowRadius: 12,
    elevation: 3,
  },
  cardRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    paddingVertical: 4,
  },
  cardRowInfo: {
    flex: 1,
  },
  cardLabel: {
    fontSize: 12,
    color: Colors.textMuted,
  },
  cardValue: {
    fontSize: 16,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
    marginTop: 2,
  },
  amount: {
    color: Colors.primary,
    fontWeight: '700' as const,
  },
  cardDivider: {
    height: 1,
    backgroundColor: Colors.border,
    marginVertical: 14,
  },
  gpsRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 10,
    marginTop: 2,
  },
  gpsBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
    gap: 4,
  },
  gpsBadgeText: {
    fontSize: 12,
    fontWeight: '600' as const,
  },
  codeDisplay: {
    backgroundColor: Colors.primary + '08',
    borderRadius: 14,
    padding: 18,
    alignItems: 'center',
    width: '100%',
    marginTop: 20,
  },
  codeLabel: {
    fontSize: 12,
    color: Colors.textMuted,
    marginBottom: 4,
  },
  codeValue: {
    fontSize: 28,
    fontWeight: '800' as const,
    color: Colors.primary,
    letterSpacing: 3,
  },
  footer: {
    padding: 20,
    paddingBottom: 36,
  },
  confirmBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.success,
    borderRadius: 14,
    paddingVertical: 16,
    gap: 8,
  },
  confirmBtnDanger: {
    backgroundColor: Colors.danger,
  },
  confirmBtnText: {
    fontSize: 16,
    fontWeight: '700' as const,
    color: Colors.white,
  },
  warningText: {
    fontSize: 12,
    color: Colors.danger,
    textAlign: 'center',
    marginTop: 10,
  },
});
