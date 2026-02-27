import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, TextInput, TouchableOpacity } from 'react-native';
import { Camera, Sparkles, ArrowRight, AlertCircle } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import { formatFCFA, categories } from '@/mocks/data';
import * as Haptics from 'expo-haptics';

export default function MissionRequestScreen() {
  const [description, setDescription] = useState('');
  const [showEstimate, setShowEstimate] = useState(false);
  const [photosCount, setPhotosCount] = useState(0);

  const handleEstimate = () => {
    if (description.length < 10) return;
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    setShowEstimate(true);
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <Text style={styles.heading}>Décrivez votre besoin</Text>
      <Text style={styles.subheading}>Soyez le plus précis possible pour recevoir des devis adaptés</Text>

      <View style={styles.inputCard}>
        <TextInput
          style={styles.textArea}
          placeholder="Ex: J'ai une fuite d'eau sous l'évier de ma cuisine depuis 2 jours. Le joint semble usé..."
          placeholderTextColor={Colors.textMuted}
          multiline
          numberOfLines={6}
          textAlignVertical="top"
          value={description}
          onChangeText={setDescription}
          testID="mission-description"
        />
        <View style={styles.inputFooter}>
          <TouchableOpacity
            style={styles.photoBtn}
            onPress={() => {
              Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              setPhotosCount(p => p + 1);
            }}
          >
            <Camera size={18} color={Colors.accent} />
            <Text style={styles.photoBtnText}>
              {photosCount > 0 ? `${photosCount} photo(s)` : 'Ajouter photos'}
            </Text>
          </TouchableOpacity>
          <Text style={styles.charCount}>{description.length}/500</Text>
        </View>
      </View>

      <TouchableOpacity
        style={[styles.estimateBtn, description.length < 10 && styles.estimateBtnDisabled]}
        onPress={handleEstimate}
        disabled={description.length < 10}
        activeOpacity={0.8}
      >
        <Sparkles size={18} color={Colors.white} />
        <Text style={styles.estimateBtnText}>Estimation IA</Text>
      </TouchableOpacity>

      {showEstimate && (
        <View style={styles.estimateCard}>
          <View style={styles.estimateHeader}>
            <Sparkles size={16} color={Colors.accent} />
            <Text style={styles.estimateTitle}>Estimation IA</Text>
          </View>

          <View style={styles.estimateRow}>
            <Text style={styles.estimateLabel}>Catégorie détectée</Text>
            <View style={styles.categoryBadge}>
              <Text style={styles.categoryBadgeText}>Plomberie</Text>
            </View>
          </View>

          <View style={styles.estimateRow}>
            <Text style={styles.estimateLabel}>Niveau d'urgence</Text>
            <View style={[styles.urgencyBadge, { backgroundColor: Colors.danger + '15' }]}>
              <AlertCircle size={13} color={Colors.danger} />
              <Text style={[styles.urgencyText, { color: Colors.danger }]}>Urgent</Text>
            </View>
          </View>

          <View style={styles.priceRange}>
            <Text style={styles.priceLabel}>Fourchette de prix estimée</Text>
            <Text style={styles.priceValue}>{formatFCFA(120000)} - {formatFCFA(250000)}</Text>
          </View>

          <View style={styles.priceBreakdown}>
            <View style={styles.breakdownItem}>
              <View style={[styles.breakdownDot, { backgroundColor: Colors.primary }]} />
              <Text style={styles.breakdownLabel}>Matériaux</Text>
              <Text style={styles.breakdownValue}>{formatFCFA(80000)} - {formatFCFA(160000)}</Text>
            </View>
            <View style={styles.breakdownItem}>
              <View style={[styles.breakdownDot, { backgroundColor: Colors.accent }]} />
              <Text style={styles.breakdownLabel}>Main d'œuvre</Text>
              <Text style={styles.breakdownValue}>{formatFCFA(40000)} - {formatFCFA(90000)}</Text>
            </View>
          </View>

          <TouchableOpacity style={styles.submitBtn} activeOpacity={0.8}>
            <Text style={styles.submitBtnText}>Envoyer la demande</Text>
            <ArrowRight size={18} color={Colors.white} />
          </TouchableOpacity>
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
  heading: {
    fontSize: 22,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
    marginBottom: 6,
  },
  subheading: {
    fontSize: 14,
    color: Colors.textSecondary,
    lineHeight: 20,
    marginBottom: 20,
  },
  inputCard: {
    backgroundColor: Colors.white,
    borderRadius: 16,
    overflow: 'hidden',
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 2 },
    shadowOpacity: 0.04,
    shadowRadius: 8,
    elevation: 1,
  },
  textArea: {
    padding: 16,
    fontSize: 15,
    color: Colors.textPrimary,
    minHeight: 140,
    lineHeight: 22,
  },
  inputFooter: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderTopWidth: 1,
    borderTopColor: Colors.border,
  },
  photoBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
  },
  photoBtnText: {
    fontSize: 13,
    color: Colors.accent,
    fontWeight: '500' as const,
  },
  charCount: {
    fontSize: 12,
    color: Colors.textMuted,
  },
  estimateBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.accent,
    borderRadius: 14,
    paddingVertical: 14,
    marginTop: 16,
    gap: 8,
  },
  estimateBtnDisabled: {
    opacity: 0.5,
  },
  estimateBtnText: {
    fontSize: 15,
    fontWeight: '700' as const,
    color: Colors.white,
  },
  estimateCard: {
    backgroundColor: Colors.white,
    borderRadius: 18,
    padding: 20,
    marginTop: 20,
    borderWidth: 1,
    borderColor: Colors.accent + '25',
  },
  estimateHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    marginBottom: 16,
  },
  estimateTitle: {
    fontSize: 16,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  estimateRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 12,
  },
  estimateLabel: {
    fontSize: 13,
    color: Colors.textSecondary,
  },
  categoryBadge: {
    backgroundColor: '#3498DB15',
    paddingHorizontal: 12,
    paddingVertical: 4,
    borderRadius: 8,
  },
  categoryBadgeText: {
    fontSize: 13,
    fontWeight: '600' as const,
    color: '#3498DB',
  },
  urgencyBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
    gap: 4,
  },
  urgencyText: {
    fontSize: 13,
    fontWeight: '600' as const,
  },
  priceRange: {
    backgroundColor: Colors.primary + '08',
    borderRadius: 12,
    padding: 16,
    marginTop: 4,
    marginBottom: 12,
  },
  priceLabel: {
    fontSize: 12,
    color: Colors.textSecondary,
    marginBottom: 6,
  },
  priceValue: {
    fontSize: 18,
    fontWeight: '700' as const,
    color: Colors.primary,
  },
  priceBreakdown: {
    gap: 8,
    marginBottom: 16,
  },
  breakdownItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  breakdownDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
  },
  breakdownLabel: {
    flex: 1,
    fontSize: 13,
    color: Colors.textSecondary,
  },
  breakdownValue: {
    fontSize: 13,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
  },
  submitBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.primary,
    borderRadius: 14,
    paddingVertical: 14,
    gap: 8,
  },
  submitBtnText: {
    fontSize: 15,
    fontWeight: '700' as const,
    color: Colors.white,
  },
});
