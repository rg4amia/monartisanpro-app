import React, { useState } from 'react';
import { View, Text, StyleSheet, ScrollView, TextInput, TouchableOpacity, Alert } from 'react-native';
import { Stack } from 'expo-router';
import { AlertTriangle, Camera, Send, Clock, CheckCircle, Circle } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import * as Haptics from 'expo-haptics';

const existingDisputes = [
  { id: 'LIT-0042', title: 'Matériaux non conformes', status: 'resolu', date: '2026-02-20' },
  { id: 'LIT-0039', title: 'Retard de livraison', status: 'en_cours', date: '2026-02-15' },
];

export default function DisputeScreen() {
  const [description, setDescription] = useState('');
  const [photosCount, setPhotosCount] = useState(0);

  const handleSubmit = () => {
    if (!description.trim()) return;
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    Alert.alert('Litige envoyé', 'Votre signalement a été enregistré. Nous vous contacterons sous 24h.');
    setDescription('');
  };

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <Stack.Screen options={{ title: 'Litiges', headerBackTitle: 'Retour' }} />

      <View style={styles.header}>
        <View style={styles.headerIcon}>
          <AlertTriangle size={28} color={Colors.danger} />
        </View>
        <Text style={styles.headerTitle}>Signaler un problème</Text>
        <Text style={styles.headerSubtitle}>Décrivez votre problème et nous interviendrons rapidement</Text>
      </View>

      <View style={styles.formCard}>
        <TextInput
          style={styles.textArea}
          placeholder="Décrivez le problème rencontré..."
          placeholderTextColor={Colors.textMuted}
          multiline
          numberOfLines={5}
          textAlignVertical="top"
          value={description}
          onChangeText={setDescription}
        />
        <View style={styles.formFooter}>
          <TouchableOpacity
            style={styles.photoBtn}
            onPress={() => {
              Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              setPhotosCount(p => p + 1);
            }}
          >
            <Camera size={18} color={Colors.accent} />
            <Text style={styles.photoBtnText}>
              {photosCount > 0 ? `${photosCount} photo(s)` : 'Ajouter des preuves'}
            </Text>
          </TouchableOpacity>
        </View>
      </View>

      <TouchableOpacity
        style={[styles.submitBtn, !description.trim() && styles.submitBtnDisabled]}
        onPress={handleSubmit}
        disabled={!description.trim()}
        activeOpacity={0.8}
      >
        <Send size={18} color={Colors.white} />
        <Text style={styles.submitBtnText}>Envoyer le signalement</Text>
      </TouchableOpacity>

      <Text style={styles.sectionTitle}>Historique des litiges</Text>
      {existingDisputes.map((dispute) => (
        <View key={dispute.id} style={styles.disputeCard}>
          <View style={styles.disputeHeader}>
            <Text style={styles.disputeId}>{dispute.id}</Text>
            <View style={[
              styles.disputeStatus,
              { backgroundColor: dispute.status === 'resolu' ? Colors.success + '15' : Colors.warning + '15' }
            ]}>
              {dispute.status === 'resolu' ? (
                <CheckCircle size={13} color={Colors.success} />
              ) : (
                <Clock size={13} color={Colors.warning} />
              )}
              <Text style={[
                styles.disputeStatusText,
                { color: dispute.status === 'resolu' ? Colors.success : Colors.warning }
              ]}>
                {dispute.status === 'resolu' ? 'Résolu' : 'En cours'}
              </Text>
            </View>
          </View>
          <Text style={styles.disputeTitle}>{dispute.title}</Text>
          <Text style={styles.disputeDate}>{dispute.date}</Text>
        </View>
      ))}
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
    width: 60,
    height: 60,
    borderRadius: 30,
    backgroundColor: Colors.danger + '12',
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
    textAlign: 'center',
    marginTop: 4,
  },
  formCard: {
    backgroundColor: Colors.white,
    borderRadius: 16,
    overflow: 'hidden',
    marginBottom: 16,
  },
  textArea: {
    padding: 16,
    fontSize: 15,
    color: Colors.textPrimary,
    minHeight: 120,
    lineHeight: 22,
  },
  formFooter: {
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
  submitBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.danger,
    borderRadius: 14,
    paddingVertical: 16,
    gap: 8,
    marginBottom: 32,
  },
  submitBtnDisabled: {
    opacity: 0.5,
  },
  submitBtnText: {
    fontSize: 16,
    fontWeight: '700' as const,
    color: Colors.white,
  },
  sectionTitle: {
    fontSize: 17,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
    marginBottom: 14,
  },
  disputeCard: {
    backgroundColor: Colors.white,
    borderRadius: 14,
    padding: 16,
    marginBottom: 10,
  },
  disputeHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 6,
  },
  disputeId: {
    fontSize: 14,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  disputeStatus: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 8,
    gap: 4,
  },
  disputeStatusText: {
    fontSize: 12,
    fontWeight: '600' as const,
  },
  disputeTitle: {
    fontSize: 14,
    color: Colors.textSecondary,
  },
  disputeDate: {
    fontSize: 11,
    color: Colors.textMuted,
    marginTop: 6,
  },
});
