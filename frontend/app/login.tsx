import React, { useState, useRef } from 'react';
import { View, Text, StyleSheet, TextInput, TouchableOpacity, Animated, KeyboardAvoidingView, Platform } from 'react-native';
import { useRouter, Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { Phone, ArrowRight, Shield } from 'lucide-react-native';
import { Colors } from '@/constants/colors';
import { useUser } from '@/contexts/UserContext';
import * as Haptics from 'expo-haptics';

export default function LoginScreen() {
  const router = useRouter();
  const { setPhone } = useUser();
  const [phoneNumber, setPhoneNumber] = useState('');
  const [otp, setOtp] = useState('');
  const [step, setStep] = useState<'phone' | 'otp'>('phone');
  const [isLoading, setIsLoading] = useState(false);
  const shakeAnim = useRef(new Animated.Value(0)).current;

  const handleSendOtp = () => {
    if (phoneNumber.length < 8) {
      Animated.sequence([
        Animated.timing(shakeAnim, { toValue: 10, duration: 50, useNativeDriver: true }),
        Animated.timing(shakeAnim, { toValue: -10, duration: 50, useNativeDriver: true }),
        Animated.timing(shakeAnim, { toValue: 10, duration: 50, useNativeDriver: true }),
        Animated.timing(shakeAnim, { toValue: 0, duration: 50, useNativeDriver: true }),
      ]).start();
      Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      return;
    }
    setIsLoading(true);
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    setTimeout(() => {
      setIsLoading(false);
      setStep('otp');
    }, 1000);
  };

  const handleVerifyOtp = () => {
    if (otp.length < 4) return;
    setIsLoading(true);
    Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Medium);
    setTimeout(() => {
      setIsLoading(false);
      setPhone('+225 ' + phoneNumber);
      router.replace('/role-select');
    }, 800);
  };

  return (
    <View style={styles.container}>
      <StatusBar style="light" />
      <Stack.Screen options={{ headerShown: false }} />

      <View style={styles.topSection}>
        <View style={styles.iconCircle}>
          <Phone size={28} color={Colors.white} />
        </View>
        <Text style={styles.title}>
          {step === 'phone' ? 'Connexion' : 'Vérification'}
        </Text>
        <Text style={styles.subtitle}>
          {step === 'phone'
            ? 'Entrez votre numéro de téléphone pour continuer'
            : `Code envoyé au +225 ${phoneNumber}`
          }
        </Text>
      </View>

      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        style={styles.formSection}
      >
        <View style={styles.card}>
          {step === 'phone' ? (
            <Animated.View style={{ transform: [{ translateX: shakeAnim }] }}>
              <Text style={styles.label}>Numéro de téléphone</Text>
              <View style={styles.inputRow}>
                <View style={styles.countryCode}>
                  <Text style={styles.countryFlag}>🇨🇮</Text>
                  <Text style={styles.countryText}>+225</Text>
                </View>
                <TextInput
                  style={styles.phoneInput}
                  placeholder="07 XX XX XX XX"
                  placeholderTextColor={Colors.textMuted}
                  keyboardType="phone-pad"
                  value={phoneNumber}
                  onChangeText={setPhoneNumber}
                  maxLength={10}
                  testID="phone-input"
                />
              </View>
            </Animated.View>
          ) : (
            <View>
              <Text style={styles.label}>Code de vérification</Text>
              <View style={styles.otpRow}>
                {[0, 1, 2, 3].map((i) => (
                  <View
                    key={i}
                    style={[
                      styles.otpBox,
                      otp.length > i && styles.otpBoxFilled,
                    ]}
                  >
                    <Text style={styles.otpDigit}>{otp[i] || ''}</Text>
                  </View>
                ))}
              </View>
              <TextInput
                style={styles.hiddenInput}
                keyboardType="number-pad"
                value={otp}
                onChangeText={(t) => setOtp(t.slice(0, 4))}
                maxLength={4}
                autoFocus
                testID="otp-input"
              />
              <TouchableOpacity style={styles.resendBtn}>
                <Text style={styles.resendText}>Renvoyer le code</Text>
              </TouchableOpacity>
            </View>
          )}

          <TouchableOpacity
            style={[styles.button, isLoading && styles.buttonDisabled]}
            onPress={step === 'phone' ? handleSendOtp : handleVerifyOtp}
            disabled={isLoading}
            activeOpacity={0.8}
            testID="login-button"
          >
            <Text style={styles.buttonText}>
              {isLoading ? 'Chargement...' : step === 'phone' ? 'Recevoir le code' : 'Vérifier'}
            </Text>
            {!isLoading && <ArrowRight size={18} color={Colors.white} />}
          </TouchableOpacity>
        </View>

        <View style={styles.securityRow}>
          <Shield size={14} color={Colors.textMuted} />
          <Text style={styles.securityText}>Connexion sécurisée par OTP</Text>
        </View>
      </KeyboardAvoidingView>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: Colors.primary,
  },
  topSection: {
    paddingTop: 100,
    paddingHorizontal: 28,
    paddingBottom: 40,
    alignItems: 'center',
  },
  iconCircle: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: 'rgba(255,255,255,0.12)',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 20,
  },
  title: {
    fontSize: 28,
    fontWeight: '700' as const,
    color: Colors.white,
    marginBottom: 8,
  },
  subtitle: {
    fontSize: 15,
    color: 'rgba(255,255,255,0.6)',
    textAlign: 'center',
    lineHeight: 22,
  },
  formSection: {
    flex: 1,
    backgroundColor: Colors.background,
    borderTopLeftRadius: 28,
    borderTopRightRadius: 28,
    paddingHorizontal: 24,
    paddingTop: 32,
  },
  card: {
    backgroundColor: Colors.white,
    borderRadius: 20,
    padding: 24,
    shadowColor: Colors.primary,
    shadowOffset: { width: 0, height: 4 },
    shadowOpacity: 0.08,
    shadowRadius: 16,
    elevation: 4,
  },
  label: {
    fontSize: 13,
    fontWeight: '600' as const,
    color: Colors.textSecondary,
    marginBottom: 10,
    textTransform: 'uppercase' as const,
    letterSpacing: 0.5,
  },
  inputRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 24,
    gap: 10,
  },
  countryCode: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: Colors.background,
    paddingHorizontal: 12,
    paddingVertical: 14,
    borderRadius: 12,
    gap: 6,
  },
  countryFlag: {
    fontSize: 18,
  },
  countryText: {
    fontSize: 15,
    fontWeight: '600' as const,
    color: Colors.textPrimary,
  },
  phoneInput: {
    flex: 1,
    backgroundColor: Colors.background,
    paddingHorizontal: 16,
    paddingVertical: 14,
    borderRadius: 12,
    fontSize: 17,
    fontWeight: '500' as const,
    color: Colors.textPrimary,
    letterSpacing: 1,
  },
  otpRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 12,
    marginBottom: 16,
  },
  otpBox: {
    width: 56,
    height: 60,
    borderRadius: 14,
    backgroundColor: Colors.background,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 2,
    borderColor: 'transparent',
  },
  otpBoxFilled: {
    borderColor: Colors.accent,
    backgroundColor: Colors.accent + '08',
  },
  otpDigit: {
    fontSize: 24,
    fontWeight: '700' as const,
    color: Colors.textPrimary,
  },
  hiddenInput: {
    position: 'absolute',
    opacity: 0,
    height: 0,
    width: 0,
  },
  resendBtn: {
    alignSelf: 'center',
    marginBottom: 20,
  },
  resendText: {
    fontSize: 13,
    color: Colors.accent,
    fontWeight: '600' as const,
  },
  button: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: Colors.accent,
    paddingVertical: 16,
    borderRadius: 14,
    gap: 8,
  },
  buttonDisabled: {
    opacity: 0.7,
  },
  buttonText: {
    fontSize: 16,
    fontWeight: '700' as const,
    color: Colors.white,
  },
  securityRow: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 20,
    gap: 6,
  },
  securityText: {
    fontSize: 12,
    color: Colors.textMuted,
  },
});
