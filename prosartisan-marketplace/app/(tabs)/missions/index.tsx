import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, TouchableOpacity } from 'react-native';
import { useRouter } from 'expo-router';
import { Colors } from '@/constants/colors';
import { useUser } from '@/contexts/UserContext';
import { missions, transactions, formatFCFA } from '@/mocks/data';
import MissionCard from '@/components/MissionCard';

const filters = ['Toutes', 'En cours', 'Devis', 'Terminées'];
const txFilters = ['Toutes', 'Validées', 'En attente', 'Payées'];

export default function MissionsScreen() {
  const { role } = useUser();
  const router = useRouter();
  const [activeFilter, setActiveFilter] = useState(0);

  if (role === 'fournisseur') {
    const statusMap: Record<string, string> = { 'Toutes': '', 'Validées': 'validee', 'En attente': 'en_attente', 'Payées': 'payee' };
    const filtered = activeFilter === 0
      ? transactions
      : transactions.filter(t => t.status === statusMap[txFilters[activeFilter]]);

    return (
      <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
        <View style={styles.filtersRow}>
          {txFilters.map((f, i) => (
            <TouchableOpacity
              key={f}
              style={[styles.filterChip, i === activeFilter && styles.filterChipActive]}
              onPress={() => setActiveFilter(i)}
            >
              <Text style={[styles.filterText, i === activeFilter && styles.filterTextActive]}>{f}</Text>
            </TouchableOpacity>
          ))}
        </View>

        {filtered.map((tx) => (
          <View key={tx.id} style={styles.txCard}>
            <View style={styles.txHeader}>
              <Text style={styles.txCode}>{tx.jCode}</Text>
              <View style={[
                styles.txStatusBadge,
                {
                  backgroundColor: tx.status === 'payee' ? Colors.success + '15'
                    : tx.status === 'validee' ? Colors.primary + '15'
                    : Colors.warning + '15'
                }
              ]}>
                <Text style={[
                  styles.txStatusText,
                  {
                    color: tx.status === 'payee' ? Colors.success
                      : tx.status === 'validee' ? Colors.primary
                      : Colors.warning
                  }
                ]}>
                  {tx.status === 'payee' ? 'Payée' : tx.status === 'validee' ? 'Validée' : 'En attente'}
                </Text>
              </View>
            </View>
            <Text style={styles.txArtisan}>{tx.artisanName}</Text>
            <View style={styles.txFooter}>
              <Text style={styles.txAmount}>{formatFCFA(tx.amount)}</Text>
              <Text style={styles.txDate}>{tx.date}</Text>
            </View>
            {!tx.gpsValid && (
              <View style={styles.gpsAlert}>
                <Text style={styles.gpsAlertText}>🚨 Alerte GPS - Hors zone</Text>
              </View>
            )}
            {tx.status === 'validee' && (
              <Text style={styles.payoutNote}>Paiement prévu : J+1</Text>
            )}
          </View>
        ))}
      </ScrollView>
    );
  }

  const statusMap: Record<string, string> = { 'Toutes': '', 'En cours': 'en_cours', 'Devis': 'devis', 'Terminées': 'terminee' };
  const filtered = activeFilter === 0
    ? missions
    : missions.filter(m => m.status === statusMap[filters[activeFilter]]);

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.filtersRow}>
        {filters.map((f, i) => (
          <TouchableOpacity
            key={f}
            style={[styles.filterChip, i === activeFilter && styles.filterChipActive]}
            onPress={() => setActiveFilter(i)}
          >
            <Text style={[styles.filterText, i === activeFilter && styles.filterTextActive]}>{f}</Text>
          </TouchableOpacity>
        ))}
      </View>

      {filtered.map((mission) => (
        <MissionCard
          key={mission.id}
          mission={mission}
          onPress={() => router.push({ pathname: '/(tabs)/(home)/mission-tracking', params: { id: mission.id } })}
        />
      ))}

      {filtered.length === 0 && (
        <View style={styles.empty}>
          <Text style={styles.emptyText}>Aucune mission trouvée</Text>
        </View>
      )}
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
  filtersRow: {
    flexDirection: 'row',
    gap: 8,
    marginBottom: 16,
  },
  filterChip: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 20,
    backgroundColor: Colors.white,
    borderWidth: 1,
    borderColor: Colors.border,
  },
  filterChipActive: {
    backgroundColor: Colors.primary,
    borderColor: Colors.primary,
  },
  filterText: {
    fontSize: 13,
    fontWeight: '500' as const,
    color: Colors.textSecondary,
  },
  filterTextActive: {
    color: Colors.white,
    fontWeight: '600' as const,
  },
  empty: {
    alignItems: 'center',
    paddingTop: 60,
  },
  emptyText: {
    fontSize: 15,
    color: Colors.textMuted,
  },
  txCard: {
    backgroundColor: Colors.white,
    borderRadius: 16,
    padding: 16,
    marginBottom: 10,
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.04,
    shadowRadius: 8,
    elevation: 1,
  },
  txHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  txCode: {
    fontSize: 17,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  txStatusBadge: {
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
  },
  txStatusText: {
    fontSize: 12,
    fontWeight: '600' as const,
  },
  txArtisan: {
    fontSize: 14,
    color: Colors.textSecondary,
    marginBottom: 8,
  },
  txFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  txAmount: {
    fontSize: 16,
    fontWeight: '700' as const,
    color: Colors.primary,
  },
  txDate: {
    fontSize: 12,
    color: Colors.textMuted,
  },
  gpsAlert: {
    backgroundColor: Colors.danger + '10',
    borderRadius: 8,
    padding: 8,
    marginTop: 10,
  },
  gpsAlertText: {
    fontSize: 12,
    color: Colors.danger,
    fontWeight: '600' as const,
  },
  payoutNote: {
    fontSize: 12,
    color: Colors.success,
    fontWeight: '500' as const,
    marginTop: 8,
  },
});
