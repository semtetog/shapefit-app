import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'br.com.shapefit.app',
  appName: 'ShapeFit',
  webDir: 'www',
  server: {
    url: "https://www.appshapefit.com",
    cleartext: true
  },
  plugins: {
    PushNotifications: {
      presentationOptions: ["badge", "sound", "alert"],
      channels: [
        {
          id: "default_channel",
          name: "Padrão",
          importance: 5,
          visibility: 1,
          sound: "default"
        }
      ]
    }
  }
};

export default config;