# Sistema de Inventário Profissional v3.0

Aplicação PHP 8+ com arquitetura **MVC limpa e escalável**, refatorada a partir do código monolítico original.

---

## Estrutura de Pastas

```
inventario/
├── public/                    ← Web root (Apache/Nginx aponta aqui)
│   ├── index.php              ← Entry point único
│   ├── .htaccess              ← Rewrite rules + segurança
│   └── assets/
│       ├── css/app.css        ← Estilos globais
│       └── js/app.js          ← JS global (autocomplete, modais, dropdowns)
│
├── src/
│   ├── Config/
│   │   └── Database.php       ← Singleton de conexão MySQL
│   ├── Core/
│   │   ├── Router.php         ← Roteador baseado em ?pagina=
│   │   └── Security.php       ← CSRF, sanitização, sessão, guards
│   ├── Database/
│   │   └── Migrations.php     ← Criação e evolução idempotente do schema
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── DashboardController.php
│   │   ├── ContagemController.php
│   │   ├── CadastrosController.php
│   │   ├── ExportController.php
│   │   └── AjaxController.php
│   ├── Models/
│   │   ├── Inventario.php
│   │   ├── Contagem.php
│   │   ├── Deposito.php
│   │   ├── Partnumber.php
│   │   └── Usuario.php
│   ├── Services/
│   │   └── ExportService.php  ← Geração de XLSX, CSV, TXT
│   └── Views/
│       ├── layout/
│       │   ├── header.php
│       │   └── footer.php
│       ├── auth/login.php
│       ├── dashboard/
│       │   ├── index.php
│       │   └── concluidos.php
│       ├── contagem/index.php
│       └── cadastros/index.php
│
├── .env                       ← Credenciais (NÃO versionar!)
├── .env.example               ← Modelo público de variáveis
└── .htaccess                  ← Redireciona raiz → public/
```

---

---

## Funcionalidade QR Code Scanner

O sistema possui um scanner de QR Code integrado que permite leitura automática de etiquetas usando a câmera do dispositivo.

### Formato do QR Code

```
[3 caracteres: Depósito][Restante: Part Number]
```

**Exemplo:**
- QR Code: `B9M555119496R`
- Depósito: `B9M`
- Part Number: `555119496R`

### Como usar

1. Na tela de **Contagem**, clique no botão **"Scan QR"** (ícone de QR Code)
2. Autorize o acesso à câmera quando solicitado
3. Posicione o QR Code dentro da área marcada
4. O sistema automaticamente:
   - Extrai os 3 primeiros caracteres como **Depósito**
   - Extrai o restante como **Part Number**
   - Preenche os campos do formulário
   - Foca no campo de **Quantidade** para entrada rápida

### Tecnologia

- Biblioteca: **html5-qrcode v2.3.8** (carregada via CDN)
- Funciona em navegadores modernos com suporte à câmera
- Compatível com dispositivos móveis (smartphones/tablets)

---

## Instalação

### 1. Configurar variáveis de ambiente

### 1. Configurar variáveis de ambiente

```bash
cp .env.example .env
```

Edite `.env` com suas credenciais reais:

```
DB_HOST=localhost
DB_USER=seu_usuario
DB_PASS=sua_senha_segura
DB_NAME=inventario_profissional
ADMIN_PASSWORD=SuaSenhaAdmin@123
```

> ⚠️ **NUNCA versione o arquivo `.env`** — adicione-o ao `.gitignore`.

### 2. Configurar servidor web

**Apache** — aponte o `DocumentRoot` para `public/`:
```apache
DocumentRoot /caminho/para/inventario/public
<Directory /caminho/para/inventario/public>
    AllowOverride All
</Directory>
```

**Nginx**:
```nginx
root /caminho/para/inventario/public;
index index.php;
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 3. Banco de dados

O sistema **cria e evolui o banco automaticamente** na primeira requisição via `Migrations::run()`.  
Nenhuma SQL manual é necessária.

### 4. Requisitos

- PHP 8.1+
- Extensão `mysqli`
- Extensão `zip` (para exportação XLSX)
- MySQL/MariaDB 5.7+
- Módulo `mod_rewrite` habilitado (Apache)

---

## Melhorias em relação ao código original

| Antes | Depois |
|-------|--------|
| 1 arquivo com 4.536 linhas | 29 arquivos organizados em camadas |
| Credenciais hardcoded no código | Variáveis de ambiente via `.env` |
| HTML, CSS, JS e SQL misturados | Separação total (Views, Controllers, Models) |
| Funções globais soltas | Classes com responsabilidades claras |
| `SHOW COLUMNS` em toda query | Schema fixo com migrations idempotentes |
| XML gerado manualmente (XLSX) | Lógica isolada em `ExportService` |
| Sem autoloader | PSR-4 nativo sem Composer |
| Sem router | `Router` simples e extensível |
| Roteamento por `switch/case` | Controllers com métodos separados |

---

## Adicionar novas funcionalidades

1. **Nova rota**: adicione `$router->get('minha_rota', [MeuController::class, 'index'])` em `public/index.php`
2. **Novo controller**: crie `src/Controllers/MeuController.php` com namespace `App\Controllers`
3. **Nova view**: crie `src/Views/minha_secao/index.php`
4. **Alteração de banco**: adicione `self::addColumnIfNotExists(...)` em `Migrations::runAlterations()`

---

## Segurança

- CSRF token em todos os formulários POST
- Proteção de sessão (httponly, samesite, timeout configurável)
- `session_regenerate_id()` no login
- Prepared statements em todas as queries
- `htmlspecialchars()` em todas as saídas
- Headers de segurança via `.htaccess`
- Credenciais fora do código-fonte
