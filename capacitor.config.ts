import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'br.com.shapefit.app',
  appName: 'ShapeFit',
  webDir: 'www',
  server: {
    url: "https://www.appshapefit.com",
    cleartext: true
  },
  // ======================================================
  // INÍCIO DA SOLUÇÃO - DEFININDO O CANAL DE NOTIFICAÇÃO
  // ======================================================
  plugins: {
    PushNotifications: {
      presentationOptions: ["badge", "sound", "alert"],
      // A parte abaixo cria o canal "Padrão" no Android
      // sem isso, o Android 8+ pode ignorar a notificação
      channels: [
        {
          id: "default_channel", // ID interno do canal
          name: "Padrão",        // Nome que o usuário vê nas configurações
          importance: 5,         // Importância máxima (5) para garantir que apareça
          visibility: 1,         // Torna a notificação pública
          sound: "default"       // Som padrão
        }
      ]
    }
  }
  // ======================================================
};

export default config;