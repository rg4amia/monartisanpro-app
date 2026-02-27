import React from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity } from 'react-native';
import { useLocalSearchParams } from 'expo-router';
import { FileText, CheckCircle, Calendar } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import { missions, formatFCFA } from '@/mocks/data';

export default function QuoteScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const mission = missions.find(m => m.id === id) ?? missions[0];

  const laborLines = [
    { desc: 'Diagnostic et repérage fuite', qty: 1, price: 15000 },
    { desc: 'Démontage et remplacement siphon', qty: 1, price: 35000 },
    { desc: 'Vérification étanchéité', qty: 1, price: 10000 },
    { desc: 'Nettoyage et remise en état', qty: 1, price: 15000 },
  ];

  const materialLines = [
    { desc: 'Siphon PVC Ø40mm', qty: 1, price: 12000 },
    { desc: 'Joints silicone', qty: 2, price: 5000 },
    { desc: 'Raccord flexible', qty: 1, price: 18000 },
    { desc: 'Teflon + colle PVC', qty: 1, price: 8000 },
    { desc: 'Tuyau PVC 1m', qty: 2, price: 7500 },
  ];

  const totalLabor = laborLines.reduce((s, l) => s + l.qty * l.price, 0);
  const totalMaterials = materialLines.reduce((s, l) => s + l.qty * l.price, 0);

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.header}>
        <View style={styles.headerIcon}>
          <FileText size={24} color={Colors.primary} />
        </View>
        <Text style={styles.headerTitle}>Devis #{mission.id.padStart(4, '0')}</Text>
        <Text style={styles.headerSubtitle}>{mission.title}</Text>
      </View>

      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <View style={[styles.sectionDot, { backgroundColor: Colors.accent }]} />
          <Text style={styles.sectionTitle}>Main d'œuvre</Text>
        </View>
        {laborLines.map((line, i) => (
          <View key={i} style={styles.lineItem}>
            <Text style={styles.lineDesc}>{line.desc}</Text>
            <Text style={styles.lineQty}>x{line.qty}</Text>
            <Text style={styles.linePrice}>{formatFCFA(line.price * line.qty)}</Text>
          </View>
        ))}
        <View style={styles.subtotalRow}>
          <Text style={styles.subtotalLabel}>Sous-total main d'œuvre</Text>
          <Text style={[styles.subtotalValue, { color: Colors.accent }]}>{formatFCFA(totalLabor)}</Text>
        </View>
      </View>

      <View style={styles.section}>
        <View style={styles.sectionHeader}>
          <View style={[styles.sectionDot, { backgroundColor: Colors.primary }]} />
          <Text style={styles.sectionTitle}>Matériaux</Text>
        </View>
        {materialLines.map((line, i) => (
          <View key={i} style={styles.lineItem}>
            <Text style={styles.lineDesc}>{line.desc}</Text>
            <Text style={styles.lineQty}>x{line.qty}</Text>
            <Text style={styles.linePrice}>{formatFCFA(line.price * line.qty)}</Text>
          </View>
        ))}
        <View style={styles.subtotalRow}>
          <Text style={styles.subtotalLabel}>Sous-total matériaux</Text>
          <Text style={[styles.subtotalValue, { color: Colors.primary }]}>{formatFCFA(totalMaterials)}</Text>
        </View>
      </View>

      <View style={styles.totalCard}>
        <View style={styles.totalRow}>
          <Text style={styles.totalLabel}>Total TTC</Text>
          <Text style={styles.totalValue}>{formatFCFA(totalLabor + totalMaterials)}</Text>
        </View>
      </View>

      <View style={styles.milestonesSection}>
        <Text style={styles.milestonesSectionTitle}>Calendrier des jalons</Text>
        {mission.milestones.map((ms, i) => (
          <View key={ms.id} style={styles.milestoneItem}>
            <View style={styles.milestoneLeft}>
              <Calendar size={14} color={Colors.textMuted} />
              <Text style={styles.milestoneDate}>{ms.targetDate}</Text>
            </View>
            <Text style={styles.milestoneName}>{ms.title}</Text>
            <Text style={styles.milestoneAmount}>{formatFCFA(ms.amount)}</Text>
          </View>
        ))}
      </View>

      <View style={styles.paymentSection}>
        <Text style={styles.paymentTitle}>Moyens de paiement</Text>
        <View style={styles.paymentMethods}>
          <TouchableOpacity style={[styles.paymentBtn, { borderColor: '#1DC5C8' }]} activeOpacity={0.7}>
            <Text style={[styles.paymentBtnText, { color: '#1DC5C8' }]}>🌊 Wave CI</Text>
          </TouchableOpacity>
          <TouchableOpacity style={[styles.paymentBtn, { borderColor: '#FF6600' }]} activeOpacity={0.7}>
            <Text style={[styles.paymentBtnText, { color: '#FF6600' }]}>📱 Orange Money</Text>
          </TouchableOpacity>
        </View>
      </View>

      <TouchableOpacity style={styles.acceptBtn} activeOpacity={0.8}>
        <CheckCircle size={18} color={Colors.white} />
        <Text style={styles.acceptBtnText}>Accepter le devis</Text>
      </TouchableOpacity>
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
  header: {
    alignItems: 'center',
    marginBottom: 24,
  },
  headerIcon: {
    width: 52,
    height: 52,
    borderRadius: 26,
    backgroundColor: Colors.primary + '12',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  headerTitle: {
    fontSize: 20,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  headerSubtitle: {
    fontSize: 14,
    color: Colors.textSecondary,
    marginTop: 4,
  },
  section: {
    backgroundColor: Colors.white,
    borderRadius: 16,
    padding: 16,
    marginBottom: 12,
  },
  sectionHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 12,
  },
  sectionDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  sectionTitle: {
    fontSize: 15,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  lineItem: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
  },
  lineDesc: {
    flex: 1,
    fontSize: 13,
    color: Colors.textSecondary,
  },
  lineQty: {
    fontSize: 12,
    color: Colors.textMuted,
    marginRight: 12,
  },
  linePrice: {
    fontSize: 13,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
    width: 80,
    textAlign: 'right',
  },
  subtotalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 12,
    paddingTop: 8,
  },
  subtotalLabel: {
    fontSize: 13,
    fontWeight: '600' as const,
    color: Colors.textSecondary,
  },
  subtotalValue: {
    fontSize: 15,
    fontWeight: '700' as const,
  },
  totalCard: {
    backgroundColor: Colors.primary,
    borderRadius: 16,
    padding: 20,
    marginBottom: 20,
  },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  totalLabel: {
    fontSize: 16,
    fontWeight: '600' as const,
    color: 'rgba(255,255,255,0.8)',
  },
  totalValue: {
    fontSize: 22,
    fontWeight: '800' as const,
    color: Colors.white,
  },
  milestonesSection: {
    marginBottom: 20,
  },
  milestonesSectionTitle: {
    fontSize: 15,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
    marginBottom: 12,
  },
  milestoneItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.white,
    borderRadius: 12,
    padding: 14,
    marginBottom: 8,
    gap: 10,
  },
  milestoneLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  milestoneDate: {
    fontSize: 11,
    color: Colors.textMuted,
    fontWeight: '500' as const,
  },
  milestoneName: {
    flex: 1,
    fontSize: 13,
    color: Colors.textPrimary,
    fontWeight: '500' as const,
  },
  milestoneAmount: {
    fontSize: 13,
    fontWeight: '700' as const,
    color: Colors.primary,
  },
  paymentSection: {
    marginBottom: 20,
  },
  paymentTitle: {
    fontSize: 15,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
    marginBottom: 12,
  },
  paymentMethods: {
    flexDirection: 'row',
    gap: 10,
  },
  paymentBtn: {
    flex: 1,
    borderWidth: 2,
    borderRadius: 14,
    paddingVertical: 14,
    alignItems: 'center',
    backgroundColor: Colors.white,
  },
  paymentBtnText: {
    fontSize: 14,
    fontWeight: '700' as const,
  },
  acceptBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.success,
    borderRadius: 14,
    paddingVertical: 16,
    gap: 8,
  },
  acceptBtnText: {
    fontSize: 16,
    fontWeight: '700' as const,
    color: Colors.white,
  },
});
