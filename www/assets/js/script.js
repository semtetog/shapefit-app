// Importa o plugin de Rede do Capacitor
import { Network } from '/node_modules/@capacitor/network/dist/esm/index.js';

// Função que verifica a conexão e redireciona
const checkConnectionAndRedirect = async () => {
    try {
        const status = await Network.getStatus();

        if (status.connected) {
            // COM INTERNET: Redireciona para o seu site real
            window.location.replace("https://www.appshapefit.com");
        } else {
            // SEM INTERNET: Redireciona para a página de erro local
            window.location.replace("offline.html");
        }
    } catch (e) {
        console.error("Erro ao verificar a rede:", e);
        window.location.replace("offline.html");
    }
};

// Roda a função
checkConnectionAndRedirect();