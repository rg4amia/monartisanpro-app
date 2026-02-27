import React from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity } from 'react-native';
import { useLocalSearchParams, useRouter } from 'expo-router';
import { CheckCircle, Clock, Circle, Camera, MapPin, Shield } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import { missions, formatFCFA } from '@/mocks/data';

const phaseLabels = ['Devis', 'Financée', 'En cours', 'Jalons', 'Terminée'];
const phaseKeys = ['devis', 'financee', 'en_cours', 'jalons', 'terminee'];

export default function MissionTrackingScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const mission = missions.find(m => m.id === id) ?? missions[0];
  const currentPhaseIndex = phaseKeys.indexOf(mission.status);

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <Text style={styles.title}>{mission.title}</Text>
      <View style={styles.metaRow}>
        <MapPin size={14} color={Colors.textMuted} />
        <Text style={styles.metaText}>{mission.location}</Text>
      </View>

      {mission.totalAmount > 2000000 && (
        <View style={styles.referentBanner}>
          <Shield size={16} color={Colors.danger} />
          <Text style={styles.referentText}>Validation Référent requise</Text>
        </View>
      )}

      <View style={styles.phaseSection}>
        <Text style={styles.sectionTitle}>Progression</Text>
        <View style={styles.stepper}>
          {phaseLabels.map((phase, index) => {
            const isCompleted = index < currentPhaseIndex;
            const isCurrent = index === currentPhaseIndex;
            return (
              <View key={phase} style={styles.stepItem}>
                <View style={styles.stepIconCol}>
                  {isCompleted ? (
                    <CheckCircle size={22} color={Colors.success} fill={Colors.success + '20'} />
                  ) : isCurrent ? (
                    <Clock size={22} color={Colors.accent} />
                  ) : (
                    <Circle size={22} color={Colors.border} />
                  )}
                  {index < phaseLabels.length - 1 && (
                    <View style={[styles.stepLine, isCompleted && styles.stepLineCompleted]} />
                  )}
                </View>
                <View style={styles.stepContent}>
                  <Text style={[
                    styles.stepLabel,
                    isCompleted && styles.stepLabelCompleted,
                    isCurrent && styles.stepLabelCurrent,
                  ]}>{phase}</Text>
                </View>
              </View>
            );
          })}
        </View>
      </View>

      <View style={styles.escrowSection}>
        <Text style={styles.sectionTitle}>Escrow</Text>
        <View style={styles.escrowCard}>
          <View style={styles.escrowRow}>
            <Text style={styles.escrowLabel}>Wallet Matériaux</Text>
            <Text style={styles.escrowValue}>{formatFCFA(mission.materialsAmount)}</Text>
          </View>
          <View style={styles.barContainer}>
            <View style={[styles.barFill, { width: '60%', backgroundColor: Colors.primary }]} />
          </View>
          <View style={[styles.escrowRow, { marginTop: 14 }]}>
            <Text style={styles.escrowLabel}>Wallet Main d'œuvre</Text>
            <Text style={styles.escrowValue}>{formatFCFA(mission.laborAmount)}</Text>
          </View>
          <View style={styles.barContainer}>
            <View style={[styles.barFill, { width: '40%', backgroundColor: Colors.accent }]} />
          </View>
        </View>
      </View>

      <View style={styles.milestonesSection}>
        <Text style={styles.sectionTitle}>Jalons</Text>
        {mission.milestones.map((milestone, index) => (
          <View key={milestone.id} style={styles.milestoneCard}>
            <View style={styles.milestoneHeader}>
              <View style={styles.milestoneNumContainer}>
                <Text style={styles.milestoneNum}>{index + 1}</Text>
              </View>
              <View style={styles.milestoneInfo}>
                <Text style={styles.milestoneTitle}>{milestone.title}</Text>
                <Text style={styles.milestoneDate}>{milestone.targetDate}</Text>
              </View>
              <View>
                {milestone.status === 'validated' ? (
                  <View style={[styles.milestoneBadge, { backgroundColor: Colors.success + '15' }]}>
                    <Text style={[styles.milestoneBadgeText, { color: Colors.success }]}>Validé</Text>
                  </View>
                ) : milestone.status === 'completed' ? (
                  <View style={[styles.milestoneBadge, { backgroundColor: Colors.primary + '15' }]}>
                    <Text style={[styles.milestoneBadgeText, { color: Colors.primary }]}>Terminé</Text>
                  </View>
                ) : milestone.status === 'in_progress' ? (
                  <View style={[styles.milestoneBadge, { backgroundColor: Colors.accent + '15' }]}>
                    <Text style={[styles.milestoneBadgeText, { color: Colors.accent }]}>En cours</Text>
                  </View>
                ) : (
                  <View style={[styles.milestoneBadge, { backgroundColor: Colors.border }]}>
                    <Text style={[styles.milestoneBadgeText, { color: Colors.textMuted }]}>En attente</Text>
                  </View>
                )}
              </View>
            </View>
            <View style={styles.milestoneFooter}>
              <Text style={styles.milestoneAmount}>{formatFCFA(milestone.amount)}</Text>
              {milestone.status === 'completed' && (
                <TouchableOpacity style={styles.validateOtpBtn}>
                  <Text style={styles.validateOtpText}>Valider (OTP)</Text>
                </TouchableOpacity>
              )}
            </View>
          </View>
        ))}
      </View>
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
  title: {
    fontSize: 22,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
    marginBottom: 6,
  },
  metaRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
    marginBottom: 16,
  },
  metaText: {
    fontSize: 13,
    color: Colors.textMuted,
  },
  referentBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.danger + '10',
    borderRadius: 12,
    padding: 14,
    gap: 10,
    marginBottom: 16,
    borderWidth: 1,
    borderColor: Colors.danger + '25',
  },
  referentText: {
    fontSize: 14,
    fontWeight: '600' as const,
    color: Colors.danger,
  },
  phaseSection: {
    marginBottom: 24,
  },
  sectionTitle: {
    fontSize: 17,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
    marginBottom: 14,
  },
  stepper: {
    backgroundColor: Colors.white,
    borderRadius: 16,
    padding: 18,
  },
  stepItem: {
    flexDirection: 'row',
    minHeight: 44,
  },
  stepIconCol: {
    alignItems: 'center',
    width: 28,
  },
  stepLine: {
    width: 2,
    flex: 1,
    backgroundColor: Colors.border,
    marginVertical: 2,
  },
  stepLineCompleted: {
    backgroundColor: Colors.success,
  },
  stepContent: {
    flex: 1,
    marginLeft: 12,
    paddingBottom: 10,
  },
  stepLabel: {
    fontSize: 14,
    color: Colors.textMuted,
    fontWeight: '500' as const,
  },
  stepLabelCompleted: {
    color: Colors.success,
    fontWeight: '600' as const,
  },
  stepLabelCurrent: {
    color: Colors.accent,
    fontWeight: '700' as const,
  },
  escrowSection: {
    marginBottom: 24,
  },
  escrowCard: {
    backgroundColor: Colors.white,
    borderRadius: 16,
    padding: 18,
  },
  escrowRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  escrowLabel: {
    fontSize: 13,
    color: Colors.textSecondary,
  },
  escrowValue: {
    fontSize: 14,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  barContainer: {
    height: 8,
    borderRadius: 4,
    backgroundColor: Colors.border,
    overflow: 'hidden',
  },
  barFill: {
    height: 8,
    borderRadius: 4,
  },
  milestonesSection: {
    paddingBottom: 20,
  },
  milestoneCard: {
    backgroundColor: Colors.white,
    borderRadius: 14,
    padding: 16,
    marginBottom: 10,
  },
  milestoneHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
  },
  milestoneNumContainer: {
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: Colors.primary + '12',
    justifyContent: 'center',
    alignItems: 'center',
  },
  milestoneNum: {
    fontSize: 13,
    fontWeight: '700' as const,
    color: Colors.primary,
  },
  milestoneInfo: {
    flex: 1,
  },
  milestoneTitle: {
    fontSize: 14,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
  },
  milestoneDate: {
    fontSize: 12,
    color: Colors.textMuted,
    marginTop: 2,
  },
  milestoneBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
  },
  milestoneBadgeText: {
    fontSize: 11,
    fontWeight: '600' as const,
  },
  milestoneFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginTop: 12,
    paddingTop: 10,
    borderTopWidth: 1,
    borderTopColor: Colors.border,
  },
  milestoneAmount: {
    fontSize: 15,
    fontWeight: '700' as const,
    color: Colors.primary,
  },
  validateOtpBtn: {
    backgroundColor: Colors.accent,
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 10,
  },
  validateOtpText: {
    fontSize: 13,
    fontWeight: '600' as const,
    color: Colors.white,
  },
});
