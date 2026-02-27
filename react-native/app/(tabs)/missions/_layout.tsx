import { Stack } from 'expo-router';
import { Colors } from '@/constants/colors';

export default function MissionsLayout() {
  return (
    <Stack
      screenOptions={{
        headerStyle: { backgroundColor: Colors.white },
        headerTintColor: Colors.textPrimary,
        headerTitleStyle: { fontWeight: '600' },
        headerShadowVisible: false,
      }}
    >
      <Stack.Screen name="index" options={{ title: 'Missions' }} />
    </Stack>
  );
}
