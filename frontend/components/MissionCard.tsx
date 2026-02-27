import React from 'react';
import { View, Text, StyleSheet, TouchableOpacity } from 'react-native';
import { Clock, CheckCircle, AlertCircle, FileText, Banknote } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import { formatFCFA } from '@/mocks/data';
import type { Mission } from '@/mocks/data';

interface MissionCardProps {
  mission: Mission;
  onPress?: () => void;
}

const statusConfig = {
  devis: { label: 'Devis', color: Colors.accent, icon: FileText },
  financee: { label: 'Financée', color: Colors.primary, icon: Banknote },
  en_cours: { label: 'En cours', color: '#3498DB', icon: Clock },
  jalons: { label: 'Jalons', color: Colors.warning, icon: AlertCircle },
  terminee: { label: 'Terminée', color: Colors.success, icon: CheckCircle },
};

const urgencyConfig = {
  faible: { label: 'Faible', color: Colors.success },
  moyen: { label: 'Moyen', color: Colors.warning },
  urgent: { label: 'Urgent', color: Colors.danger },
};

export default function MissionCard({ mission, onPress }: MissionCardProps) {
  const status = statusConfig[mission.status];
  const urgency = urgencyConfig[mission.urgency];
  const StatusIcon = status.icon;

  return (
    <TouchableOpacity
      style={styles.card}
      onPress={onPress}
      activeOpacity={0.7}
      testID={`mission-card-${mission.id}`}
    >
      <View style={styles.header}>
        <View style={[styles.statusBadge, { backgroundColor: status.color + '18' }]}>
          <StatusIcon size={13} color={status.color} />
          <Text style={[styles.statusText, { color: status.color }]}>{status.label}</Text>
        </View>
        <View style={[styles.urgencyDot, { backgroundColor: urgency.color }]} />
      </View>
      <Text style={styles.title} numberOfLines={1}>{mission.title}</Text>
      <Text style={styles.location}>{mission.location}</Text>
      <View style={styles.footer}>
        <Text style={styles.amount}>{formatFCFA(mission.totalAmount)}</Text>
        {mission.totalAmount > 2000000 && (
          <View style={styles.referentBadge}>
            <Text style={styles.referentText}>Réf. requis</Text>
          </View>
        )}
      </View>
      <View style={styles.progressRow}>
        <View style={styles.progressBar}>
          <View style={[styles.progressFill, { flex: mission.materialsAmount, backgroundColor: Colors.primary }]} />
          <View style={[styles.progressFill, { flex: mission.laborAmount, backgroundColor: Colors.accent }]} />
        </View>
      </View>
      <View style={styles.legendRow}>
        <View style={styles.legendItem}>
          <View style={[styles.legendDot, { backgroundColor: Colors.primary }]} />
          <Text style={styles.legendText}>Matériaux</Text>
        </View>
        <View style={styles.legendItem}>
          <View style={[styles.legendDot, { backgroundColor: Colors.accent }]} />
          <Text style={styles.legendText}>Main d'œuvre</Text>
        </View>
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: Colors.white,
    borderRadius: 16,
    padding: 16,
    marginBottom: 12,
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.06,
    shadowRadius: 8,
    elevation: 2,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 8,
  },
  statusBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 20,
    gap: 5,
  },
  statusText: {
    fontSize: 12,
    fontWeight: '600' as const,
  },
  urgencyDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  title: {
    fontSize: 16,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
    marginBottom: 3,
  },
  location: {
    fontSize: 13,
    color: Colors.textSecondary,
    marginBottom: 10,
  },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 10,
  },
  amount: {
    fontSize: 16,
    fontWeight: '700' as const,
    color: Colors.primary,
  },
  referentBadge: {
    backgroundColor: Colors.danger + '15',
    paddingHorizontal: 8,
    paddingVertical: 3,
    borderRadius: 6,
  },
  referentText: {
    fontSize: 10,
    color: Colors.danger,
    fontWeight: '600' as const,
  },
  progressRow: {
    marginBottom: 6,
  },
  progressBar: {
    flexDirection: 'row',
    height: 6,
    borderRadius: 3,
    overflow: 'hidden',
    backgroundColor: Colors.border,
  },
  progressFill: {
    height: 6,
  },
  legendRow: {
    flexDirection: 'row',
    gap: 16,
  },
  legendItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 4,
  },
  legendDot: {
    width: 6,
    height: 6,
    borderRadius: 3,
  },
  legendText: {
    fontSize: 10,
    color: Colors.textMuted,
  },
});
