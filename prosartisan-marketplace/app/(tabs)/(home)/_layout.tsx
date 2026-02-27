import { Stack } from 'expo-router';
import { Colors } from '@/constants/colors';

export default function HomeLayout() {
  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: Colors.white },
        headerTintColor: Colors.textPrimary,
        headerTitleStyle: { fontWeight: '600' },
        headerShadowVisible: false,
      }}
    >
      <Stack.Screen name="index" options={{ headerShown: false }} />
      <Stack.Screen name="artisan-profile" options={{ title: 'Profil Artisan' }} />
      <Stack.Screen name="mission-request" options={{ title: 'Nouvelle Mission' }} />
      <Stack.Screen name="mission-tracking" options={{ title: 'Suivi Mission' }} />
      <Stack.Screen name="quote" options={{ title: 'Devis' }} />
      <Stack.Screen name="jcode" options={{ title: 'J-Code' }} />
      <Stack.Screen name="score" options={{ title: "Score N'Zassa" }} />
      <Stack.Screen name="quote-builder" options={{ title: 'Créer un Devis' }} />
      <Stack.Screen name="transaction-confirm" options={{ title: 'Confirmation' }} />
    </Stack>
  );
}
