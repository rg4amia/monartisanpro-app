import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, TextInput, TouchableOpacity, Alert } from 'react-native';
import { Plus, Trash2, Send, Calendar } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import { formatFCFA } from '@/mocks/data';
import * as Haptics from 'expo-haptics';

interface Line {
  id: string;
  type: 'labor' | 'material';
  description: string;
  quantity: string;
  unitPrice: string;
}

interface MilestoneInput {
  id: string;
  title: string;
  amount: string;
  targetDate: string;
}

export default function QuoteBuilderScreen() {
  const [lines, setLines] = useState<Line[]>([
    { id: '1', type: 'labor', description: '', quantity: '1', unitPrice: '' },
  ]);
  const [milestones, setMilestones] = useState<MilestoneInput[]>([
    { id: '1', title: '', amount: '', targetDate: '' },
  ]);

  const addLine = (type: 'labor' | 'material') => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setLines([...lines, { id: Date.now().toString(), type, description: '', quantity: '1', unitPrice: '' }]);
  };

  const removeLine = (id: string) => {
    setLines(lines.filter(l => l.id !== id));
  };

  const updateLine = (id: string, field: keyof Line, value: string) => {
    setLines(lines.map(l => l.id === id ? { ...l, [field]: value } : l));
  };

  const addMilestone = () => {
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
    setMilestones([...milestones, { id: Date.now().toString(), title: '', amount: '', targetDate: '' }]);
  };

  const updateMilestone = (id: string, field: keyof MilestoneInput, value: string) => {
    setMilestones(milestones.map(m => m.id === id ? { ...m, [field]: value } : m));
  };

  const totalLabor = lines.filter(l => l.type === 'labor').reduce((s, l) => s + (parseInt(l.quantity) || 0) * (parseInt(l.unitPrice) || 0), 0);
  const totalMaterials = lines.filter(l => l.type === 'material').reduce((s, l) => s + (parseInt(l.quantity) || 0) * (parseInt(l.unitPrice) || 0), 0);

  const handleSend = () => {
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    Alert.alert('Devis envoyé', 'Votre devis a été envoyé au client avec succès.');
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.sectionHeader}>
        <View style={[styles.sectionDot, { backgroundColor: Colors.accent }]} />
        <Text style={styles.sectionTitle}>Main d'œuvre</Text>
      </View>
      {lines.filter(l => l.type === 'labor').map((line) => (
        <View key={line.id} style={styles.lineCard}>
          <TextInput
            style={styles.lineInput}
            placeholder="Description du travail"
            placeholderTextColor={Colors.textMuted}
            value={line.description}
            onChangeText={(v) => updateLine(line.id, 'description', v)}
          />
          <View style={styles.lineRow}>
            <TextInput
              style={[styles.lineNumInput, styles.qtyInput]}
              placeholder="Qté"
              placeholderTextColor={Colors.textMuted}
              keyboardType="numeric"
              value={line.quantity}
              onChangeText={(v) => updateLine(line.id, 'quantity', v)}
            />
            <TextInput
              style={[styles.lineNumInput, styles.priceInput]}
              placeholder="Prix unitaire (FCFA)"
              placeholderTextColor={Colors.textMuted}
              keyboardType="numeric"
              value={line.unitPrice}
              onChangeText={(v) => updateLine(line.id, 'unitPrice', v)}
            />
            <TouchableOpacity onPress={() => removeLine(line.id)} style={styles.deleteBtn}>
              <Trash2 size={16} color={Colors.danger} />
            </TouchableOpacity>
          </View>
        </View>
      ))}
      <TouchableOpacity style={styles.addBtn} onPress={() => addLine('labor')}>
        <Plus size={16} color={Colors.accent} />
        <Text style={styles.addBtnText}>Ajouter main d'œuvre</Text>
      </TouchableOpacity>

      <View style={[styles.sectionHeader, { marginTop: 24 }]}>
        <View style={[styles.sectionDot, { backgroundColor: Colors.primary }]} />
        <Text style={styles.sectionTitle}>Matériaux</Text>
      </View>
      {lines.filter(l => l.type === 'material').map((line) => (
        <View key={line.id} style={styles.lineCard}>
          <TextInput
            style={styles.lineInput}
            placeholder="Nom du matériau"
            placeholderTextColor={Colors.textMuted}
            value={line.description}
            onChangeText={(v) => updateLine(line.id, 'description', v)}
          />
          <View style={styles.lineRow}>
            <TextInput
              style={[styles.lineNumInput, styles.qtyInput]}
              placeholder="Qté"
              placeholderTextColor={Colors.textMuted}
              keyboardType="numeric"
              value={line.quantity}
              onChangeText={(v) => updateLine(line.id, 'quantity', v)}
            />
            <TextInput
              style={[styles.lineNumInput, styles.priceInput]}
              placeholder="Prix unitaire (FCFA)"
              placeholderTextColor={Colors.textMuted}
              keyboardType="numeric"
              value={line.unitPrice}
              onChangeText={(v) => updateLine(line.id, 'unitPrice', v)}
            />
            <TouchableOpacity onPress={() => removeLine(line.id)} style={styles.deleteBtn}>
              <Trash2 size={16} color={Colors.danger} />
            </TouchableOpacity>
          </View>
        </View>
      ))}
      <TouchableOpacity style={styles.addBtn} onPress={() => addLine('material')}>
        <Plus size={16} color={Colors.primary} />
        <Text style={[styles.addBtnText, { color: Colors.primary }]}>Ajouter matériau</Text>
      </TouchableOpacity>

      <View style={styles.totalCard}>
        <View style={styles.totalRow}>
          <Text style={styles.totalLabel}>Main d'œuvre</Text>
          <Text style={[styles.totalValue, { color: Colors.accent }]}>{formatFCFA(totalLabor)}</Text>
        </View>
        <View style={styles.totalRow}>
          <Text style={styles.totalLabel}>Matériaux</Text>
          <Text style={[styles.totalValue, { color: Colors.primary }]}>{formatFCFA(totalMaterials)}</Text>
        </View>
        <View style={styles.divider} />
        <View style={styles.totalRow}>
          <Text style={styles.grandTotalLabel}>Total</Text>
          <Text style={styles.grandTotalValue}>{formatFCFA(totalLabor + totalMaterials)}</Text>
        </View>
      </View>

      <View style={[styles.sectionHeader, { marginTop: 24 }]}>
        <Calendar size={16} color={Colors.textPrimary} />
        <Text style={styles.sectionTitle}>Jalons</Text>
      </View>
      {milestones.map((ms, i) => (
        <View key={ms.id} style={styles.milestoneCard}>
          <Text style={styles.milestoneNum}>Jalon {i + 1}</Text>
          <TextInput
            style={styles.lineInput}
            placeholder="Titre du jalon"
            placeholderTextColor={Colors.textMuted}
            value={ms.title}
            onChangeText={(v) => updateMilestone(ms.id, 'title', v)}
          />
          <View style={styles.lineRow}>
            <TextInput
              style={[styles.lineNumInput, { flex: 1 }]}
              placeholder="Montant (FCFA)"
              placeholderTextColor={Colors.textMuted}
              keyboardType="numeric"
              value={ms.amount}
              onChangeText={(v) => updateMilestone(ms.id, 'amount', v)}
            />
            <TextInput
              style={[styles.lineNumInput, { flex: 1 }]}
              placeholder="Date (JJ/MM)"
              placeholderTextColor={Colors.textMuted}
              value={ms.targetDate}
              onChangeText={(v) => updateMilestone(ms.id, 'targetDate', v)}
            />
          </View>
        </View>
      ))}
      <TouchableOpacity style={styles.addBtn} onPress={addMilestone}>
        <Plus size={16} color={Colors.success} />
        <Text style={[styles.addBtnText, { color: Colors.success }]}>Ajouter un jalon</Text>
      </TouchableOpacity>

      <TouchableOpacity style={styles.sendBtn} onPress={handleSend} activeOpacity={0.8}>
        <Send size={18} color={Colors.white} />
        <Text style={styles.sendBtnText}>Envoyer le devis</Text>
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
    fontSize: 16,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  lineCard: {
    backgroundColor: Colors.white,
    borderRadius: 14,
    padding: 14,
    marginBottom: 8,
  },
  lineInput: {
    fontSize: 14,
    color: Colors.textPrimary,
    paddingVertical: 8,
    borderBottomWidth: 1,
    borderBottomColor: Colors.border,
    marginBottom: 8,
  },
  lineRow: {
    flexDirection: 'row',
    gap: 8,
    alignItems: 'center',
  },
  lineNumInput: {
    backgroundColor: Colors.background,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    fontSize: 14,
    color: Colors.textPrimary,
    fontWeight: '500' as const,
  },
  qtyInput: {
    width: 60,
    textAlign: 'center',
  },
  priceInput: {
    flex: 1,
  },
  deleteBtn: {
    padding: 8,
  },
  addBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 12,
    gap: 6,
    borderWidth: 1.5,
    borderColor: Colors.border,
    borderRadius: 12,
    borderStyle: 'dashed',
    marginBottom: 8,
  },
  addBtnText: {
    fontSize: 13,
    fontWeight: '600' as const,
    color: Colors.accent,
  },
  totalCard: {
    backgroundColor: Colors.white,
    borderRadius: 16,
    padding: 18,
    marginTop: 16,
  },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 8,
  },
  totalLabel: {
    fontSize: 14,
    color: Colors.textSecondary,
  },
  totalValue: {
    fontSize: 15,
    fontWeight: '700' as const,
  },
  divider: {
    height: 1,
    backgroundColor: Colors.border,
    marginVertical: 8,
  },
  grandTotalLabel: {
    fontSize: 16,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  grandTotalValue: {
    fontSize: 18,
    fontWeight: '800' as const,
    color: Colors.primary,
  },
  milestoneCard: {
    backgroundColor: Colors.white,
    borderRadius: 14,
    padding: 14,
    marginBottom: 8,
  },
  milestoneNum: {
    fontSize: 12,
    fontWeight: '600' as const,
    color: Colors.textMuted,
    marginBottom: 6,
  },
  sendBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.success,
    borderRadius: 14,
    paddingVertical: 16,
    marginTop: 24,
    gap: 8,
  },
  sendBtnText: {
    fontSize: 16,
    fontWeight: '700' as const,
    color: Colors.white,
  },
});
