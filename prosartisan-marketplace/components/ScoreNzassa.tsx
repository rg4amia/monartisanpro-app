import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { getScoreColor } from '@/mocks/data';
import { Colors } from '@/constants/colors';

interface ScoreNzassaProps {
  score: number;
  size?: 'small' | 'medium' | 'large';
  showLabel?: boolean;
}

export default function ScoreNzassa({ score, size = 'medium', showLabel = false }: ScoreNzassaProps) {
  const color = getScoreColor(score);
  const dimensions = size === 'small' ? 32 : size === 'medium' ? 48 : 80;
  const fontSize = size === 'small' ? 11 : size === 'medium' ? 15 : 24;
  const borderWidth = size === 'small' ? 2 : size === 'medium' ? 3 : 4;

  return (
    <View style={styles.container}>
      <View
        style={[
          styles.circle,
          {
            width: dimensions,
            height: dimensions,
            borderRadius: dimensions / 2,
            borderColor: color,
            borderWidth,
            backgroundColor: color + '15',
          },
        ]}
      >
        <Text style={[styles.scoreText, { fontSize, color }]}>{score}</Text>
      </View>
      {showLabel && (
        <Text style={styles.label}>Score N'Zassa</Text>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
  },
  circle: {
    justifyContent: 'center',
    alignItems: 'center',
  },
  scoreText: {
    fontWeight: '700' as const,
  },
  label: {
    fontSize: 10,
    color: Colors.textSecondary,
    marginTop: 4,
  },
});
