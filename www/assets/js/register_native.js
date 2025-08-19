document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('register-form');
    const errorMessageElement = document.getElementById('form-error-message');

    if (registerForm) {
        registerForm.addEventListener('submit', (event) => {
            event.preventDefault(); // Impede o envio padrão do formulário
            errorMessageElement.textContent = ''; // Limpa erros antigos

            // Pega os dados de todos os campos do formulário
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirm_password = document.getElementById('confirm_password').value;

            // Validações no lado do cliente (melhora a experiência do usuário)
            if (!name || !email || !password || !confirm_password) {
                errorMessageElement.textContent = 'Todos os campos são obrigatórios.';
                return;
            }
            if (password.length < 6) {
                errorMessageElement.textContent = 'A senha deve ter pelo menos 6 caracteres.';
                return;
            }
            if (password !== confirm_password) {
                errorMessageElement.textContent = 'As senhas não coincidem.';
                return;
            }

            // Mostra o loader
            const loader = document.getElementById('loader-overlay');
            if (loader) loader.style.display = 'flex';

            // Define a URL da API de registro
            const apiRegisterUrl = BASE_APP_URL + '/auth/register.php';

            fetch(apiRegisterUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                // Envia todos os campos necessários, incluindo o 'source'
                body: new URLSearchParams({
                    'name': name,
                    'email': email,
                    'password': password,
                    'confirm_password': confirm_password,
                    'source': 'mobile_app'
                })
            })
            .then(response => {
                if (!response.ok) { // Captura erros como "email já existe" (erro 400)
                    return response.json().then(err => { throw new Error(err.message) });
                }
                return response.json();
            })
            .then(data => {
                if (loader) loader.style.display = 'none';

                if (data.success) {
                    // SUCESSO!
                    alert(data.message); // Mostra "Cadastro realizado com sucesso!"
                    // Redireciona o usuário para a tela de login para que ele possa entrar
                    window.location.href = 'index.html';
                }
            })
            .catch(error => {
                if (loader) loader.style.display = 'none';
                console.error('Erro de cadastro:', error);
                // Exibe a mensagem de erro específica vinda do servidor
                errorMessageElement.textContent = error.message;
            });
        });
    }
});