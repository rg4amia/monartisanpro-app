import React, { useRef, useEffect } from 'react';
import { View, Text, StyleSheet, ScrollView, Animated } from 'react-native';
import { Colors } from '@/constants/colors';
import { artisans, scoreBreakdown, getScoreColor } from '@/mocks/data';

const breakdown = [
  { key: 'fiabilite', label: 'Fiabilité', value: scoreBreakdown.fiabilite, icon: '🎯' },
  { key: 'integrite', label: 'Intégrité', value: scoreBreakdown.integrite, icon: '🤝' },
  { key: 'qualite', label: 'Qualité', value: scoreBreakdown.qualite, icon: '⭐' },
  { key: 'reactivite', label: 'Réactivité', value: scoreBreakdown.reactivite, icon: '⚡' },
];

export default function ScoreScreen() {
  const artisan = artisans[0];
  const scoreColor = getScoreColor(artisan.scoreNzassa);
  const circleAnim = useRef(new Animated.Value(0)).current;
  const barAnims = useRef(breakdown.map(() => new Animated.Value(0))).current;

  useEffect(() => {
    Animated.timing(circleAnim, {
      toValue: 1,
      duration: 1000,
      useNativeDriver: false,
    }).start();

    breakdown.forEach((_, i) => {
      Animated.timing(barAnims[i], {
        toValue: 1,
        duration: 800,
        delay: 300 + i * 150,
        useNativeDriver: false,
      }).start();
    });
  }, []);

  const animatedScore = circleAnim.interpolate({
    inputRange: [0, 1],
    outputRange: [0, artisan.scoreNzassa],
  });

  return (
    <ScrollView style={styles.container} contentContainerStyle={styles.content} showsVerticalScrollIndicator={false}>
      <View style={styles.scoreCircleContainer}>
        <View style={[styles.outerCircle, { borderColor: scoreColor }]}>
          <View style={[styles.innerCircle, { backgroundColor: scoreColor + '12' }]}>
            <Animated.Text style={[styles.scoreNumber, { color: scoreColor }]}>
              {animatedScore.interpolate({
                inputRange: [0, 100],
                outputRange: ['0', '100'],
              })}
            </Animated.Text>
            <Text style={styles.scoreMax}>/100</Text>
          </View>
        </View>
        <Text style={styles.scoreTitle}>Score N'Zassa</Text>
        <Text style={styles.scoreSubtitle}>Votre score de confiance sur la plateforme</Text>
      </View>

      <View style={styles.breakdownSection}>
        <Text style={styles.sectionTitle}>Détail du score</Text>
        {breakdown.map((item, i) => (
          <View key={item.key} style={styles.breakdownCard}>
            <View style={styles.breakdownHeader}>
              <Text style={styles.breakdownIcon}>{item.icon}</Text>
              <Text style={styles.breakdownLabel}>{item.label}</Text>
              <Text style={[styles.breakdownValue, { color: getScoreColor(item.value) }]}>
                {item.value}
              </Text>
            </View>
            <View style={styles.breakdownBarBg}>
              <Animated.View
                style={[
                  styles.breakdownBarFill,
                  {
                    backgroundColor: getScoreColor(item.value),
                    width: barAnims[i].interpolate({
                      inputRange: [0, 1],
                      outputRange: ['0%', `${item.value}%`],
                    }),
                  },
                ]}
              />
            </View>
          </View>
        ))}
      </View>

      <View style={styles.tipsSection}>
        <Text style={styles.sectionTitle}>Conseils pour améliorer</Text>
        <View style={styles.tipCard}>
          <Text style={styles.tipEmoji}>💡</Text>
          <Text style={styles.tipText}>Répondez aux demandes dans les 30 minutes pour améliorer votre réactivité</Text>
        </View>
        <View style={styles.tipCard}>
          <Text style={styles.tipEmoji}>📸</Text>
          <Text style={styles.tipText}>Ajoutez des photos avant/après à chaque jalon pour augmenter votre qualité</Text>
        </View>
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
  scoreCircleContainer: {
    alignItems: 'center',
    paddingVertical: 20,
  },
  outerCircle: {
    width: 160,
    height: 160,
    borderRadius: 80,
    borderWidth: 6,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  innerCircle: {
    width: 136,
    height: 136,
    borderRadius: 68,
    justifyContent: 'center',
    alignItems: 'center',
  },
  scoreNumber: {
    fontSize: 48,
    fontWeight: '800' as const,
  },
  scoreMax: {
    fontSize: 16,
    color: Colors.textMuted,
    fontWeight: '500' as const,
    marginTop: -4,
  },
  scoreTitle: {
    fontSize: 22,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  scoreSubtitle: {
    fontSize: 14,
    color: Colors.textSecondary,
    marginTop: 4,
  },
  breakdownSection: {
    marginTop: 8,
  },
  sectionTitle: {
    fontSize: 17,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
    marginBottom: 14,
  },
  breakdownCard: {
    backgroundColor: Colors.white,
    borderRadius: 14,
    padding: 16,
    marginBottom: 10,
  },
  breakdownHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 10,
    gap: 8,
  },
  breakdownIcon: {
    fontSize: 18,
  },
  breakdownLabel: {
    flex: 1,
    fontSize: 15,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
  },
  breakdownValue: {
    fontSize: 18,
    fontWeight: '700' as const,
  },
  breakdownBarBg: {
    height: 8,
    borderRadius: 4,
    backgroundColor: Colors.border,
    overflow: 'hidden',
  },
  breakdownBarFill: {
    height: 8,
    borderRadius: 4,
  },
  tipsSection: {
    marginTop: 16,
  },
  tipCard: {
    flexDirection: 'row',
    backgroundColor: Colors.white,
    borderRadius: 14,
    padding: 16,
    marginBottom: 10,
    gap: 12,
    alignItems: 'flex-start',
  },
  tipEmoji: {
    fontSize: 20,
  },
  tipText: {
    flex: 1,
    fontSize: 13,
    color: Colors.textSecondary,
    lineHeight: 20,
  },
});
