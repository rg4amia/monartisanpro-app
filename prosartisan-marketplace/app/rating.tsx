import React, { useState } from 'react';
import { View, Text, StyleSheet, TouchableOpacity, TextInput, Alert } from 'react-native';
import { Stack } from 'expo-router';
import { Star, Send } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import * as Haptics from 'expo-haptics';

export default function RatingScreen() {
  const [rating, setRating] = useState(0);
  const [comment, setComment] = useState('');

  const handleSubmit = () => {
    if (rating === 0) return;
    Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    Alert.alert('Merci !', 'Votre évaluation a été enregistrée.');
  };

  return (
    <View style={styles.container}>
      <Stack.Screen options={{ title: 'Évaluation', headerBackTitle: 'Retour' }} />

      <View style={styles.content}>
        <Text style={styles.title}>Comment s'est passée la mission ?</Text>
        <Text style={styles.subtitle}>Réparation fuite d'eau cuisine</Text>
        <Text style={styles.artisanName}>Kouamé Yao Jean</Text>

        <View style={styles.starsRow}>
          {[1, 2, 3, 4, 5].map((i) => (
            <TouchableOpacity
              key={i}
              onPress={() => {
                Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                setRating(i);
              }}
              activeOpacity={0.7}
            >
              <Star
                size={44}
                color={i <= rating ? '#F39C12' : Colors.border}
                fill={i <= rating ? '#F39C12' : 'transparent'}
              />
            </TouchableOpacity>
          ))}
        </View>

        <Text style={styles.ratingLabel}>
          {rating === 0 ? 'Touchez pour évaluer' :
           rating <= 2 ? 'Décevant' :
           rating <= 3 ? 'Correct' :
           rating <= 4 ? 'Très bien' : 'Excellent !'}
        </Text>

        <TextInput
          style={styles.commentInput}
          placeholder="Ajoutez un commentaire (optionnel)..."
          placeholderTextColor={Colors.textMuted}
          multiline
          numberOfLines={4}
          textAlignVertical="top"
          value={comment}
          onChangeText={setComment}
        />
      </View>

      <View style={styles.footer}>
        <TouchableOpacity
          style={[styles.submitBtn, rating === 0 && styles.submitBtnDisabled]}
          onPress={handleSubmit}
          disabled={rating === 0}
          activeOpacity={0.8}
        >
          <Send size={18} color={Colors.white} />
          <Text style={styles.submitBtnText}>Envoyer l'évaluation</Text>
        </TouchableOpacity>
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
    alignItems: 'center',
    paddingHorizontal: 24,
    paddingTop: 40,
  },
  title: {
    fontSize: 22,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
    textAlign: 'center',
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 15,
    color: Colors.textSecondary,
    marginBottom: 4,
  },
  artisanName: {
    fontSize: 16,
    fontWeight: '600' as const,
    color: Colors.accent,
    marginBottom: 32,
  },
  starsRow: {
    flexDirection: 'row',
    gap: 12,
    marginBottom: 12,
  },
  ratingLabel: {
    fontSize: 16,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
    marginBottom: 32,
  },
  commentInput: {
    width: '100%',
    backgroundColor: Colors.white,
    borderRadius: 16,
    padding: 16,
    fontSize: 15,
    color: Colors.textPrimary,
    minHeight: 100,
    lineHeight: 22,
    textAlignVertical: 'top',
  },
  footer: {
    padding: 20,
    paddingBottom: 36,
  },
  submitBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.accent,
    borderRadius: 14,
    paddingVertical: 16,
    gap: 8,
  },
  submitBtnDisabled: {
    opacity: 0.5,
  },
  submitBtnText: {
    fontSize: 16,
    fontWeight: '700' as const,
    color: Colors.white,
  },
});
